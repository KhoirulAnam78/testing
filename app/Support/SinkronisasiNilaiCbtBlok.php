<?php

namespace App\Support;

use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\NilaiCbtBlok;
use App\Models\PesertaBlok;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class SinkronisasiNilaiCbtBlok
{
    /**
     * @return array{blok_diperiksa:int, blok_diproses:int, nilai_disinkronkan:int, peserta_dilewati:int, ujian_dilewati:int, masalah:array<int, string>, error:array<int, string>}
     */
    public function jalankan(?int $blokId = null): array
    {
        $tabel = $this->konfigurasiTabel();
        $hasil = [
            'blok_diperiksa' => 0,
            'blok_diproses' => 0,
            'nilai_disinkronkan' => 0,
            'peserta_dilewati' => 0,
            'ujian_dilewati' => 0,
            'masalah' => [],
            'error' => [],
        ];

        $blokIds = Blok::query()
            ->where('status', 'aktif')
            ->when($blokId !== null, fn ($query) => $query->whereKey($blokId))
            ->orderBy('id')
            ->pluck('id');

        if ($blokId !== null && $blokIds->isEmpty()) {
            $hasil['masalah'][] = "Blok aktif ID {$blokId} tidak ditemukan.";
        }

        foreach ($blokIds as $id) {
            $hasil['blok_diperiksa']++;
            $hasilSebelumTransaksi = $hasil;

            try {
                DB::transaction(function () use ($id, $tabel, &$hasil): void {
                    $this->sinkronkanBlok((int) $id, $tabel, $hasil);
                });
            } catch (Throwable $e) {
                $hasil = $hasilSebelumTransaksi;
                $hasil['error'][] = "Blok ID {$id}: {$e->getMessage()}";
            }
        }

        return $hasil;
    }

    public static function normalisasiNilai(?float $totalScore, ?float $totalPoin): ?float
    {
        if (
            $totalScore === null
            || $totalPoin === null
            || ! is_finite($totalScore)
            || ! is_finite($totalPoin)
            || $totalScore < 0
            || $totalPoin <= 0
        ) {
            return null;
        }

        $nilai = $totalScore / $totalPoin * 100;

        return $nilai > 100 ? null : round($nilai, 2);
    }

    /**
     * @param  array<string, string>  $tabel
     * @param  array{blok_diperiksa:int, blok_diproses:int, nilai_disinkronkan:int, peserta_dilewati:int, ujian_dilewati:int, masalah:array<int, string>, error:array<int, string>}  $hasil
     */
    private function sinkronkanBlok(int $blokId, array $tabel, array &$hasil): void
    {
        $aturan = AturanKegiatanBlok::query()
            ->with('jenis_kegiatan:id,kode,nama,sumber_nilai,bobot_ujian_pertama,bobot_remedial')
            ->where('blok_id', $blokId)
            ->whereHas('jenis_kegiatan', fn ($query) => $query->where('sumber_nilai', 'cbt'))
            ->get();

        if ($aturan->isEmpty()) {
            return;
        }

        $hasil['blok_diproses']++;

        $ujian = DB::table($tabel['ujian'].' as u')
            ->join($tabel['kategori_ujian'].' as k', 'k.id_kategori_ujian', '=', 'u.kategori_ujian_id')
            ->where('u.blok_id', $blokId)
            ->orderBy('u.tanggal_ujian')
            ->orderBy('u.id_ujian')
            ->get([
                'u.id_ujian',
                'u.paket_soal_id',
                'u.tanggal_ujian',
                'k.nama_kategori',
            ]);

        $ujianPerKategori = $ujian->groupBy(fn ($baris) => $this->kunciKategori($baris->nama_kategori));
        $poinPaket = $this->ambilPoinPaket($ujian->pluck('paket_soal_id')->unique()->values(), $tabel);
        $attempt = $this->ambilAttempt($ujian->pluck('id_ujian')->unique()->values(), $tabel);
        $peserta = PesertaBlok::query()
            ->where('blok_id', $blokId)
            ->whereIn('status', ['aktif', 'mengulang'])
            ->whereHas('mahasiswa')
            ->get(['id_peserta_blok', 'mahasiswa_id']);
        $waktuSinkronisasi = now();

        foreach ($aturan as $item) {
            $jenis = $item->jenis_kegiatan;
            $daftarUjian = $ujianPerKategori->get($this->kunciKategori($jenis->kode), collect())->values();

            if ($daftarUjian->isEmpty()) {
                $hasil['masalah'][] = "Blok ID {$blokId}, kegiatan {$jenis->kode}: ujian CBT tidak ditemukan.";

                continue;
            }

            if ($daftarUjian->count() > 2) {
                $jumlahDilewati = $daftarUjian->count() - 2;
                $hasil['ujian_dilewati'] += $jumlahDilewati;
                $hasil['masalah'][] = "Blok ID {$blokId}, kegiatan {$jenis->kode}: {$jumlahDilewati} ujian setelah remedial dilewati.";
            }

            $ujianPertama = $daftarUjian->get(0);
            $ujianRemedial = $daftarUjian->get(1);
            $poinPertama = $poinPaket->get((int) $ujianPertama->paket_soal_id);

            if (! $this->poinPaketValid($poinPertama)) {
                $hasil['masalah'][] = "Blok ID {$blokId}, kegiatan {$jenis->kode}: poin paket ujian pertama tidak lengkap atau tidak positif.";
                $hasil['peserta_dilewati'] += $peserta->count();

                continue;
            }

            $poinRemedial = $ujianRemedial === null
                ? null
                : $poinPaket->get((int) $ujianRemedial->paket_soal_id);
            $remedialPoinValid = $ujianRemedial === null || $this->poinPaketValid($poinRemedial);

            if (! $remedialPoinValid) {
                $hasil['masalah'][] = "Blok ID {$blokId}, kegiatan {$jenis->kode}: poin paket remedial tidak lengkap atau tidak positif.";
            }

            foreach ($peserta as $pesertaBlok) {
                [$punyaUjianPertama, $nilaiUjianPertama] = $this->nilaiAttempt(
                    $attempt,
                    (int) $ujianPertama->id_ujian,
                    (int) $pesertaBlok->mahasiswa_id,
                    (float) $poinPertama->total_poin,
                );

                if (! $punyaUjianPertama || $nilaiUjianPertama === null) {
                    $hasil['peserta_dilewati']++;

                    continue;
                }

                [$punyaRemedial, $nilaiRemedial] = $ujianRemedial === null
                    ? [false, null]
                    : $this->nilaiAttempt(
                        $attempt,
                        (int) $ujianRemedial->id_ujian,
                        (int) $pesertaBlok->mahasiswa_id,
                        $remedialPoinValid ? (float) $poinRemedial->total_poin : null,
                    );

                if ($punyaRemedial && $nilaiRemedial === null) {
                    $hasil['peserta_dilewati']++;

                    continue;
                }

                $bobotPertama = (float) $jenis->bobot_ujian_pertama;
                $bobotRemedial = (float) $jenis->bobot_remedial;

                if ($punyaRemedial && ! $this->bobotRemedialValid($bobotPertama, $bobotRemedial)) {
                    $hasil['peserta_dilewati']++;
                    $hasil['masalah'][] = "Blok ID {$blokId}, kegiatan {$jenis->kode}: bobot ujian pertama dan remedial tidak valid.";

                    continue;
                }

                NilaiCbtBlok::query()->updateOrCreate(
                    [
                        'aturan_kegiatan_blok_id' => $item->id,
                        'peserta_blok_id' => $pesertaBlok->id_peserta_blok,
                    ],
                    [
                        'nilai_ujian_pertama' => $nilaiUjianPertama,
                        'mengikuti_remedial' => $punyaRemedial,
                        'nilai_remedial' => $nilaiRemedial,
                        'bobot_ujian_pertama' => $bobotPertama,
                        'bobot_remedial' => $bobotRemedial,
                        'referensi_eksternal' => $this->referensiUjian(
                            (int) $ujianPertama->id_ujian,
                            $punyaRemedial ? (int) $ujianRemedial->id_ujian : null,
                        ),
                        'disinkronkan_pada' => $waktuSinkronisasi,
                    ],
                );
                $hasil['nilai_disinkronkan']++;
            }
        }
    }

    /**
     * @param  Collection<int, int|string>  $paketIds
     * @param  array<string, string>  $tabel
     * @return Collection<int, object>
     */
    private function ambilPoinPaket(Collection $paketIds, array $tabel): Collection
    {
        if ($paketIds->isEmpty()) {
            return collect();
        }

        return DB::table($tabel['paket_has_soal'])
            ->whereIn('paket_soal_id', $paketIds)
            ->select('paket_soal_id')
            ->selectRaw('SUM(poin) AS total_poin')
            ->groupBy('paket_soal_id')
            ->get()
            ->keyBy(fn ($baris) => (int) $baris->paket_soal_id);
    }

    /**
     * @param  Collection<int, int|string>  $ujianIds
     * @param  array<string, string>  $tabel
     * @return Collection<string, Collection<int, object>>
     */
    private function ambilAttempt(Collection $ujianIds, array $tabel): Collection
    {
        if ($ujianIds->isEmpty()) {
            return collect();
        }

        return DB::table($tabel['peserta_ujian'].' as pu')
            ->leftJoin($tabel['pilgan_jawab'].' as pj', 'pj.peserta_ujian_id', '=', 'pu.id_peserta_ujian')
            ->whereIn('pu.ujian_id', $ujianIds)
            ->where('pu.status_pengerjaan', 2)
            ->whereNotNull('pu.id_mhs_pt')
            ->select('pu.id_peserta_ujian', 'pu.ujian_id', 'pu.id_mhs_pt')
            ->selectRaw('COUNT(pj.id_pilgan_jawab) AS jumlah_jawaban')
            ->selectRaw('SUM(pj.score) AS total_score')
            ->groupBy('pu.id_peserta_ujian', 'pu.ujian_id', 'pu.id_mhs_pt')
            ->get()
            ->groupBy(fn ($baris) => $this->kunciAttempt((int) $baris->ujian_id, (int) $baris->id_mhs_pt));
    }

    /**
     * @param  Collection<string, Collection<int, object>>  $attempt
     * @return array{bool, ?float}
     */
    private function nilaiAttempt(Collection $attempt, int $ujianId, int $mahasiswaId, ?float $totalPoin): array
    {
        $baris = $attempt->get($this->kunciAttempt($ujianId, $mahasiswaId), collect());

        if ($baris->isEmpty()) {
            return [false, null];
        }

        $nilai = $baris->map(fn ($item) => (int) $item->jumlah_jawaban > 0
            ? self::normalisasiNilai(
                $item->total_score === null ? null : (float) $item->total_score,
                $totalPoin,
            )
            : null);

        return [true, $nilai->containsStrict(null) ? null : round($nilai->average(), 2)];
    }

    private function poinPaketValid(?object $poin): bool
    {
        return $poin !== null
            && $poin->total_poin !== null
            && (float) $poin->total_poin > 0;
    }

    private function bobotRemedialValid(float $bobotPertama, float $bobotRemedial): bool
    {
        return $bobotPertama >= 0
            && $bobotRemedial >= 0
            && abs(($bobotPertama + $bobotRemedial) - 100) <= .001;
    }

    private function referensiUjian(int $ujianPertamaId, ?int $remedialId): string
    {
        return 'ujian_pertama='.$ujianPertamaId.($remedialId === null ? '' : ';remedial='.$remedialId);
    }

    private function kunciKategori(mixed $kategori): string
    {
        return mb_strtoupper(trim((string) $kategori));
    }

    private function kunciAttempt(int $ujianId, int $mahasiswaId): string
    {
        return $ujianId.'-'.$mahasiswaId;
    }

    /**
     * @return array<string, string>
     */
    private function konfigurasiTabel(): array
    {
        $database = config('cbt.database');
        $tables = config('cbt.tables');

        if (! is_string($database) || ! preg_match('/\A[A-Za-z0-9_]+\z/', $database)) {
            throw new InvalidArgumentException('CBT_DB_DATABASE harus berupa identifier database yang valid.');
        }

        if (! is_array($tables)) {
            throw new InvalidArgumentException('Konfigurasi tabel CBT tidak valid.');
        }

        foreach ($tables as $key => $table) {
            if (! is_string($table) || ! preg_match('/\A[A-Za-z0-9_]+\z/', $table)) {
                throw new InvalidArgumentException("Konfigurasi tabel CBT {$key} tidak valid.");
            }

            $tables[$key] = $database.'.'.$table;
        }

        return $tables;
    }
}
