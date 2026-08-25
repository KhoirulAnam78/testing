<?php

namespace App\Support;

use App\Models\AnggotaKelompokBlok;
use App\Models\Blok;
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
     * Rekap blok memakai query kolektif. Tidak ada query per mahasiswa atau per sel.
     *
     * @return array{peserta: Collection, kegiatan: Collection, baris: Collection}
     */
    public function rekap(Blok $blok): array
    {
        $blok->loadMissing([
            'aturan_kegiatan_blok' => fn ($query) => $query
                ->with('jenis_kegiatan:id,kode,nama')
                ->withCount(['komponen_penilaian_blok', 'pertemuan_blok'])
                ->orderBy('urutan'),
        ]);

        $peserta = $blok->peserta_blok()
            ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
            ->with('mahasiswa:id_mahasiswa,nim,nama')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
            ->orderBy('mahasiswa.nama')
            ->select('peserta_blok.*')
            ->get();

        $pertemuan = $blok->pertemuan_blok()
            ->get(['id_pertemuan_blok', 'aturan_kegiatan_blok_id', 'kelompok_blok_id']);
        $pertemuanIds = $pertemuan->pluck('id_pertemuan_blok');

        $kelompokPeserta = AnggotaKelompokBlok::query()
            ->whereIn('peserta_blok_id', $peserta->pluck('id_peserta_blok'))
            ->get(['kelompok_blok_id', 'peserta_blok_id'])
            ->groupBy('peserta_blok_id');

        $rekapNilai = collect();
        $jumlahKomponenTerisi = collect();
        $presensi = collect();
        if ($pertemuanIds->isNotEmpty()) {
            $rekapNilai = RekapNilaiPertemuanBlok::query()
                ->whereIn('pertemuan_blok_id', $pertemuanIds)
                ->get(['pertemuan_blok_id', 'peserta_blok_id', 'nilai_akhir'])
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

        $baris = $peserta->map(function ($item) use ($blok, $jumlahKomponenTerisi, $kelompokPeserta, $pertemuan, $presensi, $rekapNilai) {
            $pesertaId = $item->id_peserta_blok;
            $kelompokIds = $kelompokPeserta->get($pesertaId, collect())->pluck('kelompok_blok_id');
            $pertemuanPeserta = $pertemuan->whereIn('kelompok_blok_id', $kelompokIds);
            $pertemuanByKegiatan = $pertemuanPeserta->groupBy('aturan_kegiatan_blok_id');
            $presensiPeserta = $presensi->get($pesertaId, collect());
            $wajibPresensiIds = $blok->aturan_kegiatan_blok
                ->where('perlu_presensi', true)
                ->flatMap(fn ($aturan) => $pertemuanByKegiatan->get($aturan->id, collect())->pluck('id_pertemuan_blok'));
            $jumlahPresensiWajib = (int) $blok->aturan_kegiatan_blok
                ->where('perlu_presensi', true)
                ->sum('jumlah_pertemuan');
            $presensiWajib = $presensiPeserta->whereIn('pertemuan_blok_id', $wajibPresensiIds);
            $kehadiran = $jumlahPresensiWajib === 0
                || $wajibPresensiIds->count() !== $jumlahPresensiWajib
                || $presensiWajib->count() !== $jumlahPresensiWajib
                ? null
                : self::normalisasi($presensiWajib->whereIn('status', PresensiPertemuanBlok::STATUS_HADIR)->count(), $jumlahPresensiWajib);

            $nilaiKegiatan = [];
            foreach ($blok->aturan_kegiatan_blok as $aturan) {
                $ids = $pertemuanByKegiatan->get($aturan->id, collect())->pluck('id_pertemuan_blok');
                $nilai = $rekapNilai->get($pesertaId, collect())->whereIn('pertemuan_blok_id', $ids);
                $komponenLengkap = $ids->every(
                    fn ($pertemuanId) => (int) $jumlahKomponenTerisi->get($pesertaId.'-'.$pertemuanId)?->total === $aturan->komponen_penilaian_blok_count
                );
                $lengkap = $ids->isNotEmpty()
                    && $ids->count() === $aturan->jumlah_pertemuan
                    && $aturan->komponen_penilaian_blok_count > 0
                    && $nilai->count() === $ids->count()
                    && $komponenLengkap;
                $nilaiKegiatan[$aturan->id] = $lengkap ? self::rataRata($nilai->pluck('nilai_akhir')) : null;
            }

            $sumber = [[
                'aktif' => (bool) $blok->kehadiran_masuk_dpna,
                'bobot' => (float) $blok->bobot_kehadiran_dpna,
                'nilai' => $kehadiran,
            ]];
            foreach ($blok->aturan_kegiatan_blok as $aturan) {
                $sumber[] = [
                    'aktif' => (bool) $aturan->nilai_masuk_dpna,
                    'bobot' => (float) $aturan->bobot_nilai_dpna,
                    'nilai' => $nilaiKegiatan[$aturan->id],
                ];
            }

            return [
                'peserta' => $item,
                'kehadiran' => $kehadiran,
                'kehadiran_detail' => ['terisi' => $presensiWajib->count(), 'wajib' => $jumlahPresensiWajib],
                'nilai_kegiatan' => $nilaiKegiatan,
                'nilai_akhir' => self::nilaiAkhir($sumber),
            ];
        });

        return ['peserta' => $peserta, 'kegiatan' => $blok->aturan_kegiatan_blok, 'baris' => $baris];
    }
}
