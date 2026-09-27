<?php

namespace App\Support;

use App\Models\Blok;
use App\Models\Mahasiswa;
use App\Models\PesertaBlok;
use App\Models\Semester;
use App\Models\SkalaNilaiDetail;
use App\Models\SnapshotDpnaPeserta;
use Illuminate\Support\Collection;

class KelayakanKontrakBlok
{
    /**
     * @return array{layak: bool, status: string, alasan: array<int, string>}
     */
    public function evaluasi(Mahasiswa $mahasiswa, Blok $blok, Semester $semester): array
    {
        $alasan = [];

        if (! $semester->kontrakSedangDibuka()) {
            $alasan[] = 'Masa kontrak blok belum dibuka atau sudah berakhir.';
        }

        if ((int) $blok->semester_id !== (int) $semester->id_semester || $blok->status !== 'aktif') {
            $alasan[] = 'Blok tidak aktif pada semester aktif.';
        }

        if ($mahasiswa->status !== 'aktif') {
            $alasan[] = 'Status umum mahasiswa tidak aktif.';
        }

        if (! $mahasiswa->memilikiRegistrasiAktifPada((int) $semester->id_semester)) {
            $alasan[] = 'Registrasi mahasiswa pada semester aktif belum berstatus aktif.';
        }

        $mataKuliahKurikulum = $blok->kurikulum_mata_kuliah;

        if (! $mahasiswa->kurikulum_id) {
            $alasan[] = 'Kurikulum mahasiswa belum ditetapkan.';
        } elseif (! $mataKuliahKurikulum
            || (int) $mataKuliahKurikulum->kurikulum_id !== (int) $mahasiswa->kurikulum_id
            || (int) $blok->prodi_id !== (int) $mahasiswa->prodi_id) {
            $alasan[] = 'Blok tidak termasuk kurikulum mahasiswa.';
        }

        if (! $mataKuliahKurikulum || $alasan !== []) {
            return ['layak' => false, 'status' => 'aktif', 'alasan' => array_values(array_unique($alasan))];
        }

        $mataKuliahKurikulum->loadMissing(['aturan', 'prasyarat.mata_kuliah_prasyarat.mata_kuliah', 'prasyarat.nilai_minimum']);
        $skala = $mahasiswa->kurikulum?->skala_nilai;
        $detailSkala = $skala?->detail ?? collect();

        if (! $mataKuliahKurikulum->aturan?->aktif) {
            $alasan[] = 'Aturan pengambilan mata kuliah tidak aktif.';
        }

        if ($detailSkala->isEmpty()) {
            $alasan[] = 'Skala nilai kurikulum belum lengkap.';
        }

        $nilaiTarget = $this->nilaiTerbaik(
            $mahasiswa,
            (int) $mataKuliahKurikulum->mata_kuliah_id,
            $detailSkala
        );
        $pernahMengontrak = $nilaiTarget !== null || PesertaBlok::query()
            ->where('mahasiswa_id', $mahasiswa->id_mahasiswa)
            ->where('blok_id', '!=', $blok->id)
            ->whereIn('status', ['aktif', 'mengulang', 'selesai'])
            ->whereHas('blok', fn ($query) => $query->where('mata_kuliah_id', $mataKuliahKurikulum->mata_kuliah_id))
            ->exists();
        $status = $pernahMengontrak ? 'mengulang' : 'aktif';

        if ($pernahMengontrak) {
            if (! $nilaiTarget) {
                $alasan[] = 'Nilai DPNA final untuk pengambilan sebelumnya belum tersedia.';
            } elseif (! $nilaiTarget['boleh_perbaikan']) {
                $alasan[] = 'Nilai terbaik '.$nilaiTarget['nilai_huruf'].' tidak diizinkan untuk diperbaiki.';
            }

            if (! $mataKuliahKurikulum->aturan?->bolehDiambilUlangPada($semester->nama)) {
                $alasan[] = 'Pengambilan ulang tidak diizinkan pada semester '.ucfirst($semester->nama).'.';
            }
        } elseif ($this->semesterBerjalan($mahasiswa, $semester) !== (int) $mataKuliahKurikulum->semester_urutan) {
            $alasan[] = 'Mata kuliah dijadwalkan untuk semester '.$mataKuliahKurikulum->semester_urutan.'.';
        }

        foreach ($mataKuliahKurikulum->prasyarat->where('aktif', true) as $prasyarat) {
            $nilai = $this->nilaiTerbaik(
                $mahasiswa,
                (int) $prasyarat->mata_kuliah_prasyarat->mata_kuliah_id,
                $detailSkala
            );
            $minimum = $prasyarat->nilai_minimum;

            if (! $nilai || ! $minimum || $nilai['nilai_indeks'] < (float) $minimum->nilai_indeks) {
                $nama = $prasyarat->mata_kuliah_prasyarat?->mata_kuliah?->nama ?? 'Mata kuliah prasyarat';
                $alasan[] = $nama.' wajib lulus minimal '.($minimum?->nilai_huruf ?? 'sesuai aturan').'.';
            }
        }

        return ['layak' => $alasan === [], 'status' => $status, 'alasan' => array_values(array_unique($alasan))];
    }

    private function semesterBerjalan(Mahasiswa $mahasiswa, Semester $semester): ?int
    {
        if ($semester->nama === 'pendek' || ! in_array($semester->nama, ['ganjil', 'genap'], true)) {
            return null;
        }

        $selisihTahun = (int) $semester->tahun - (int) $mahasiswa->angkatan;

        if ($selisihTahun < 0) {
            return null;
        }

        return ($selisihTahun * 2) + ($semester->nama === 'ganjil' ? 1 : 2);
    }

    /**
     * @param  Collection<int, SkalaNilaiDetail>  $detailSkala
     * @return array{nilai_indeks: float, nilai_akhir: float, nilai_huruf: string, boleh_perbaikan: bool}|null
     */
    private function nilaiTerbaik(Mahasiswa $mahasiswa, int $mataKuliahId, Collection $detailSkala): ?array
    {
        $nilai = SnapshotDpnaPeserta::query()
            ->select(['snapshot_dpna_peserta.nilai_akhir', 'finalisasi_dpna_blok.difinalisasi_pada'])
            ->join('finalisasi_dpna_blok', 'finalisasi_dpna_blok.id_finalisasi_dpna_blok', '=', 'snapshot_dpna_peserta.finalisasi_dpna_blok_id')
            ->join('blok', 'blok.id', '=', 'finalisasi_dpna_blok.blok_id')
            ->where('snapshot_dpna_peserta.nim', $mahasiswa->nim)
            ->where('finalisasi_dpna_blok.status', 'final')
            ->where('blok.mata_kuliah_id', $mataKuliahId)
            ->get()
            ->map(function ($snapshot) use ($detailSkala) {
                $akhir = (float) $snapshot->nilai_akhir;
                $detail = $detailSkala->first(fn (SkalaNilaiDetail $item) => $akhir >= (float) $item->nilai_angka_min
                    && $akhir <= (float) $item->nilai_angka_max);

                return $detail ? [
                    'nilai_indeks' => (float) $detail->nilai_indeks,
                    'nilai_akhir' => $akhir,
                    'nilai_huruf' => $detail->nilai_huruf,
                    'boleh_perbaikan' => (bool) $detail->boleh_perbaikan,
                    'difinalisasi_pada' => $snapshot->difinalisasi_pada,
                ] : null;
            })
            ->filter()
            ->sort(function (array $a, array $b) {
                return $b['nilai_indeks'] <=> $a['nilai_indeks']
                    ?: $b['nilai_akhir'] <=> $a['nilai_akhir']
                    ?: strcmp((string) $b['difinalisasi_pada'], (string) $a['difinalisasi_pada']);
            })
            ->first();

        return $nilai ? array_intersect_key($nilai, array_flip(['nilai_indeks', 'nilai_akhir', 'nilai_huruf', 'boleh_perbaikan'])) : null;
    }
}
