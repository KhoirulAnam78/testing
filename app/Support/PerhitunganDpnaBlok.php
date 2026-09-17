<?php

namespace App\Support;

use App\Models\AnggotaKelompokBlok;
use App\Models\Blok;
use App\Models\NilaiCbtBlok;
use App\Models\NilaiPertemuanBlok;
use App\Models\PresensiPertemuanBlok;
use App\Models\RekapNilaiPertemuanBlok;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PerhitunganDpnaBlok
{
    public static function normalisasi(float $total, float $maksimum): float
    {
        return $maksimum > 0 ? round(($total / $maksimum) * 100, 2) : 0.0;
    }

    /** @param  Collection<int, float|int>  $nilai */
    public static function rataRata(Collection $nilai): ?float
    {
        return $nilai->isEmpty() ? null : round((float) $nilai->avg(), 2);
    }

    /** @param  Collection<int, float|int|null>  $nilai */
    public static function rataRataGrup(Collection $nilai): ?float
    {
        return $nilai->isEmpty() || $nilai->containsStrict(null)
            ? null
            : round((float) $nilai->avg(), 2);
    }

    public static function nilaiAkhir(array $sumber): ?float
    {
        $aktif = collect($sumber)->where('aktif', true);

        if ($aktif->isEmpty() || $aktif->contains(fn (array $item) => $item['nilai'] === null)) {
            return null;
        }

        return round((float) $aktif->sum(
            fn (array $item) => $item['nilai'] * $item['bobot'] / 100
        ), 2);
    }

    /**
     * @return array{peserta: Collection, kegiatan: Collection, grup: Collection, anggota_grup_ids: Collection, menggunakan_grup: bool, baris: Collection}
     */
    public function rekap(Blok $blok): array
    {
        $menggunakanGrup = $blok->grup_dpna_blok()->exists();
        $blok->load([
            'aturan_kegiatan_blok' => fn ($query) => $query
                ->with('jenis_kegiatan:id,kode,nama,sumber_nilai')
                ->withCount(['komponen_penilaian_blok', 'pertemuan_blok', 'materi_rinci_blok'])
                ->orderBy('urutan'),
            'grup_dpna_blok' => fn ($query) => $query
                ->where('aktif', true)
                ->with(['anggota_grup_dpna_blok' => fn ($query) => $query->where('aktif', true)])
                ->orderBy('urutan'),
        ]);

        $grup = $blok->grup_dpna_blok;
        $anggotaGrupIds = $grup->flatMap(fn ($item) => $item->anggota_grup_dpna_blok->pluck('aturan_kegiatan_blok_id'))->unique();
        $peserta = $blok->peserta_blok()
            ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
            ->with('mahasiswa:id_mahasiswa,nim,nama')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
            ->orderBy('mahasiswa.nama')
            ->select('peserta_blok.*')
            ->get();

        $pertemuan = $blok->pertemuan_blok()
            ->with([
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
                'kelompok_blok:id_kelompok_blok,kode,nama',
            ])
            ->get(['id_pertemuan_blok', 'aturan_kegiatan_blok_id', 'materi_rinci_blok_id', 'kelompok_blok_id']);
        $pertemuanIds = $pertemuan->pluck('id_pertemuan_blok');
        $pesertaIds = $peserta->pluck('id_peserta_blok');

        $kelompokPeserta = AnggotaKelompokBlok::query()
            ->whereIn('peserta_blok_id', $pesertaIds)
            ->get(['kelompok_blok_id', 'peserta_blok_id'])
            ->groupBy('peserta_blok_id');
        $rekapNilai = collect();
        $jumlahKomponenTerisi = collect();
        $presensi = collect();

        if ($pertemuanIds->isNotEmpty()) {
            $rekapNilai = RekapNilaiPertemuanBlok::query()
                ->whereIn('pertemuan_blok_id', $pertemuanIds)
                ->get(['pertemuan_blok_id', 'peserta_blok_id', 'total', 'nilai_akhir'])
                ->groupBy('peserta_blok_id');
            $jumlahKomponenTerisi = NilaiPertemuanBlok::query()
                ->whereIn('pertemuan_blok_id', $pertemuanIds)
                ->select(['pertemuan_blok_id', 'peserta_blok_id', DB::raw('COUNT(*) as total')])
                ->groupBy('pertemuan_blok_id', 'peserta_blok_id')
                ->get()
                ->keyBy(fn ($item) => $item->peserta_blok_id.'-'.$item->pertemuan_blok_id);
            $presensi = PresensiPertemuanBlok::query()
                ->whereIn('pertemuan_blok_id', $pertemuanIds)
                ->get(['pertemuan_blok_id', 'peserta_blok_id', 'status'])
                ->groupBy('peserta_blok_id');
        }

        $nilaiCbt = NilaiCbtBlok::query()
            ->whereIn('aturan_kegiatan_blok_id', $blok->aturan_kegiatan_blok->pluck('id'))
            ->whereIn('peserta_blok_id', $pesertaIds)
            ->get()
            ->keyBy(fn ($item) => $item->peserta_blok_id.'-'.$item->aturan_kegiatan_blok_id);

        $baris = $peserta->map(function ($item) use (
            $blok,
            $grup,
            $anggotaGrupIds,
            $jumlahKomponenTerisi,
            $kelompokPeserta,
            $menggunakanGrup,
            $nilaiCbt,
            $pertemuan,
            $presensi,
            $rekapNilai,
        ) {
            $pesertaId = $item->id_peserta_blok;
            $kelompokIds = $kelompokPeserta->get($pesertaId, collect())->pluck('kelompok_blok_id');
            $pertemuanPeserta = $pertemuan->whereIn('kelompok_blok_id', $kelompokIds);
            $pertemuanByKegiatan = $pertemuanPeserta->groupBy('aturan_kegiatan_blok_id');
            $rekapPeserta = $rekapNilai->get($pesertaId, collect());
            $presensiPeserta = $presensi->get($pesertaId, collect());
            $wajibPresensiIds = $blok->aturan_kegiatan_blok
                ->where('perlu_presensi', true)
                ->flatMap(fn ($aturan) => $pertemuanByKegiatan->get($aturan->id, collect())->pluck('id_pertemuan_blok'));
            $jumlahPresensiWajib = (int) $blok->aturan_kegiatan_blok
                ->where('perlu_presensi', true)
                ->sum('materi_rinci_blok_count');
            $presensiWajib = $presensiPeserta->whereIn('pertemuan_blok_id', $wajibPresensiIds);
            $kehadiran = $jumlahPresensiWajib === 0
                || $wajibPresensiIds->count() !== $jumlahPresensiWajib
                || $presensiWajib->count() !== $jumlahPresensiWajib
                ? null
                : self::normalisasi($presensiWajib->whereIn('status', PresensiPertemuanBlok::STATUS_HADIR)->count(), $jumlahPresensiWajib);

            $nilaiKegiatan = [];
            $detailKegiatan = [];
            foreach ($blok->aturan_kegiatan_blok as $aturan) {
                $sumberCbt = $aturan->jenis_kegiatan?->sumber_nilai === 'cbt';

                if ($sumberCbt) {
                    $cbt = $nilaiCbt->get($pesertaId.'-'.$aturan->id);
                    $nilaiKegiatan[$aturan->id] = $cbt?->nilai_akhir === null ? null : (float) $cbt->nilai_akhir;
                    $detailKegiatan[$aturan->id] = [
                        'id' => $aturan->id,
                        'jenis' => 'cbt',
                        'nama' => $aturan->jenis_kegiatan?->nama,
                        'lengkap' => $nilaiKegiatan[$aturan->id] !== null,
                        'nilai' => $nilaiKegiatan[$aturan->id],
                        'aktif' => (bool) $aturan->nilai_masuk_dpna,
                        'bobot' => (float) $aturan->bobot_nilai_dpna,
                        'ujian_pertama' => $cbt?->nilai_ujian_pertama === null ? null : (float) $cbt->nilai_ujian_pertama,
                        'mengikuti_remedial' => (bool) ($cbt?->mengikuti_remedial ?? false),
                        'nilai_remedial' => $cbt?->nilai_remedial === null ? null : (float) $cbt->nilai_remedial,
                        'bobot_ujian_pertama' => (float) ($cbt?->bobot_ujian_pertama ?? 100),
                        'bobot_remedial' => (float) ($cbt?->bobot_remedial ?? 0),
                        'referensi_eksternal' => $cbt?->referensi_eksternal,
                        'disinkronkan_pada' => $cbt?->disinkronkan_pada?->toIso8601String(),
                    ];

                    continue;
                }

                $ids = $pertemuanByKegiatan->get($aturan->id, collect())->pluck('id_pertemuan_blok');
                $nilai = $rekapPeserta->whereIn('pertemuan_blok_id', $ids);
                $komponenLengkap = $ids->every(
                    fn ($pertemuanId) => (int) $jumlahKomponenTerisi->get($pesertaId.'-'.$pertemuanId)?->total === $aturan->komponen_penilaian_blok_count
                );
                $lengkap = $ids->isNotEmpty()
                    && $ids->count() === (int) $aturan->materi_rinci_blok_count
                    && $aturan->komponen_penilaian_blok_count > 0
                    && $nilai->count() === $ids->count()
                    && $komponenLengkap;
                $nilaiKegiatan[$aturan->id] = $lengkap ? self::rataRata($nilai->pluck('nilai_akhir')) : null;
                $detailKegiatan[$aturan->id] = [
                    'id' => $aturan->id,
                    'jenis' => 'manual',
                    'nama' => $aturan->jenis_kegiatan?->nama,
                    'lengkap' => $lengkap,
                    'nilai' => $nilaiKegiatan[$aturan->id],
                    'aktif' => (bool) $aturan->nilai_masuk_dpna,
                    'bobot' => (float) $aturan->bobot_nilai_dpna,
                    'pertemuan' => $pertemuanByKegiatan->get($aturan->id, collect())->map(function ($sesi) use ($rekapPeserta) {
                        $rekap = $rekapPeserta->firstWhere('pertemuan_blok_id', $sesi->id_pertemuan_blok);

                        return [
                            'id' => $sesi->id_pertemuan_blok,
                            'materi' => $sesi->materi_rinci_blok?->judul,
                            'pertemuan_ke' => $sesi->materi_rinci_blok?->pertemuan_ke,
                            'kelompok' => $sesi->kelompok_blok?->kode,
                            'total' => $rekap?->total === null ? null : (float) $rekap->total,
                            'nilai' => $rekap?->nilai_akhir === null ? null : (float) $rekap->nilai_akhir,
                        ];
                    })->values()->all(),
                ];
            }

            $nilaiGrup = [];
            $detailGrup = [];
            foreach ($grup as $itemGrup) {
                $anggotaIds = $itemGrup->anggota_grup_dpna_blok->pluck('aturan_kegiatan_blok_id');
                $nilaiAnggota = $anggotaIds->map(fn ($id) => $nilaiKegiatan[$id] ?? null);
                $nilaiGrup[$itemGrup->id_grup_dpna_blok] = self::rataRataGrup($nilaiAnggota);
                $detailGrup[$itemGrup->id_grup_dpna_blok] = [
                    'id' => $itemGrup->id_grup_dpna_blok,
                    'nama' => $itemGrup->nama,
                    'bobot' => (float) $itemGrup->bobot,
                    'nilai' => $nilaiGrup[$itemGrup->id_grup_dpna_blok],
                    'anggota' => $anggotaIds->map(fn ($id) => $detailKegiatan[$id] ?? null)->filter()->values()->all(),
                ];
            }

            $sumber = [[
                'aktif' => (bool) $blok->kehadiran_masuk_dpna,
                'bobot' => (float) $blok->bobot_kehadiran_dpna,
                'nilai' => $kehadiran,
            ]];

            foreach ($blok->aturan_kegiatan_blok as $aturan) {
                $sumber[] = [
                    'aktif' => ! $anggotaGrupIds->contains($aturan->id) && (bool) $aturan->nilai_masuk_dpna,
                    'bobot' => (float) $aturan->bobot_nilai_dpna,
                    'nilai' => $nilaiKegiatan[$aturan->id],
                ];
            }
            foreach ($grup as $itemGrup) {
                $sumber[] = [
                    'aktif' => (bool) $itemGrup->aktif,
                    'bobot' => (float) $itemGrup->bobot,
                    'nilai' => $nilaiGrup[$itemGrup->id_grup_dpna_blok],
                ];
            }

            return [
                'peserta' => $item,
                'kehadiran' => $kehadiran,
                'kehadiran_detail' => [
                    'terisi' => $presensiWajib->count(),
                    'hadir' => $presensiWajib->whereIn('status', PresensiPertemuanBlok::STATUS_HADIR)->count(),
                    'wajib' => $jumlahPresensiWajib,
                    'bobot' => (float) $blok->bobot_kehadiran_dpna,
                ],
                'nilai_kegiatan' => $nilaiKegiatan,
                'nilai_grup' => $nilaiGrup,
                'sumber_detail' => [
                    'kehadiran' => [
                        'nilai' => $kehadiran,
                        'terisi' => $presensiWajib->count(),
                        'hadir' => $presensiWajib->whereIn('status', PresensiPertemuanBlok::STATUS_HADIR)->count(),
                        'wajib' => $jumlahPresensiWajib,
                        'bobot' => (float) $blok->bobot_kehadiran_dpna,
                    ],
                    'grup' => array_values($detailGrup),
                    'kegiatan' => array_values($detailKegiatan),
                ],
                'nilai_akhir' => self::nilaiAkhir($sumber),
            ];
        });

        return [
            'peserta' => $peserta,
            'kegiatan' => $blok->aturan_kegiatan_blok,
            'grup' => $grup,
            'anggota_grup_ids' => $anggotaGrupIds,
            'menggunakan_grup' => $menggunakanGrup,
            'baris' => $baris,
        ];
    }
}
