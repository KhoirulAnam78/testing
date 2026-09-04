<?php

namespace App\Exports;

use App\Imports\NilaiPertemuanImport;
use App\Models\KomponenPenilaianBlok;
use App\Models\PesertaBlok;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template pengisian nilai untuk satu pertemuan.
 *
 * Sheet "Nilai" adalah satu-satunya yang dibaca saat import, dan sengaja berada di indeks
 * 0. Sheet "Petunjuk" hanya keterangan untuk dosen: batas nilai per komponen ditaruh di
 * sana, bukan disisipkan ke teks header kolom, supaya nama kolom tetap stabil kalau rubrik
 * blok diedit setelah template diunduh.
 *
 * Baris peserta dan nilai yang sudah tersimpan ikut diisikan, jadi template ini sekaligus
 * dipakai untuk memperbaiki nilai — bukan hanya untuk pengisian pertama.
 */
class NilaiPertemuanTemplateExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, PesertaBlok>  $anggota
     * @param  Collection<int, KomponenPenilaianBlok>  $komponen
     * @param  array<int|string, array<int|string, string>>  $nilaiTersimpan  [peserta_blok_id][komponen_penilaian_blok_id]
     */
    public function __construct(
        private readonly Collection $anggota,
        private readonly Collection $komponen,
        private readonly array $nilaiTersimpan = [],
    ) {}

    public function sheets(): array
    {
        return [
            new ArraySheetExport('Nilai', $this->barisNilai(), $this->batasNilai()),
            new ArraySheetExport('Petunjuk', $this->barisPetunjuk()),
        ];
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function barisNilai(): array
    {
        $kunciKomponen = NilaiPertemuanImport::kunciKomponen($this->komponen);

        $rows = [array_merge(['nim', 'nama'], array_keys($kunciKomponen))];

        foreach ($this->anggota as $peserta) {
            $baris = [
                (string) ($peserta->mahasiswa?->nim ?? ''),
                (string) ($peserta->mahasiswa?->nama ?? ''),
            ];

            foreach ($kunciKomponen as $komponenId) {
                $isian = trim((string) ($this->nilaiTersimpan[$peserta->id_peserta_blok][$komponenId] ?? ''));

                // Sel dibiarkan null, bukan string kosong, supaya di Excel tampil benar-benar
                // kosong dan dosen bisa langsung mengetik angka di atasnya.
                $baris[] = $isian === '' ? null : (float) $isian;
            }

            $rows[] = $baris;
        }

        return $rows;
    }

    /**
     * @return array<int, array{min: float, maks: float, nama: string}>
     */
    private function batasNilai(): array
    {
        $perId = $this->komponen->keyBy('id');
        $batas = [];

        foreach (NilaiPertemuanImport::kunciKomponen($this->komponen) as $komponenId) {
            $item = $perId->get($komponenId);
            $batas[] = [
                'min' => (float) ($item?->nilai_min ?? 0),
                'maks' => (float) ($item?->nilai_maks ?? 0),
                'nama' => (string) ($item?->komponen_penilaian?->nama ?? 'Komponen nilai'),
            ];
        }

        return $batas;
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function barisPetunjuk(): array
    {
        $kunciKomponen = NilaiPertemuanImport::kunciKomponen($this->komponen);
        $perId = $this->komponen->keyBy('id');

        $rows = [
            ['Petunjuk pengisian nilai pertemuan'],
            [],
            ['1.', 'Isi hanya sheet "Nilai". Sheet ini tidak dibaca saat import.'],
            ['2.', 'Jangan mengubah baris judul dan jangan mengubah kolom nim. Nama kolom itulah yang dipakai untuk mencocokkan komponen penilaian.'],
            ['3.', 'Kolom nama hanya keterangan, isinya tidak dipakai saat import.'],
            ['4.', 'Nilai harus angka dan berada di antara nilai_min dan nilai_maks pada tabel di bawah. Desimal boleh memakai titik atau koma.'],
            ['5.', 'Sel yang dikosongkan akan MENGHAPUS nilai komponen itu, sama seperti mengosongkan isian di layar penilaian.'],
            ['6.', 'Baris nim yang dihapus dari file tidak akan disentuh sama sekali, jadi import sebagian tidak menghapus nilai mahasiswa lain.'],
            ['7.', 'Bila satu baris ditolak, seluruh file dibatalkan dan tidak ada nilai yang tersimpan.'],
            [],
            ['Komponen penilaian kegiatan ini'],
            ['kolom di sheet Nilai', 'kode', 'komponen', 'nilai_min', 'nilai_maks'],
        ];

        foreach ($kunciKomponen as $kunci => $komponenId) {
            $item = $perId->get($komponenId);

            $rows[] = [
                $kunci,
                (string) ($item?->komponen_penilaian?->kode ?? ''),
                (string) ($item?->komponen_penilaian?->nama ?? ''),
                (float) ($item?->nilai_min ?? 0),
                (float) ($item?->nilai_maks ?? 0),
            ];
        }

        return $rows;
    }
}
