<?php

namespace App\Imports;

use App\Models\KomponenPenilaianBlok;
use App\Models\PesertaBlok;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import nilai satu pertemuan dari file hasil `NilaiPertemuanTemplateExport`.
 *
 * Kelas ini hanya membaca dan memvalidasi; penulisan tetap dilakukan komponen
 * `blok-operasional.nilai-pertemuan`. Alasannya matematika total dan `rekap_nilai_pertemuan_blok`
 * harus tetap punya satu implementasi, dan pemeriksaan izin harus tetap terkumpul di
 * `AksesPertemuanBlok`, bukan tersebar ke kelas import.
 *
 * `WithMultipleSheets` wajib walau hanya satu sheet yang dipakai: tanpa itu
 * `Reader::buildSheetImports()` memasang handler ke SEMUA sheet
 * (`array_fill(0, getSheetCount(), $import)`), sehingga sheet "Petunjuk" pada template ikut
 * diproses dan gagal validasi. Sheet dipetakan lewat indeks 0, bukan nama, supaya file yang
 * disimpan ulang sebagai CSV — yang hanya punya satu sheet tanpa nama — tetap terbaca.
 */
class NilaiPertemuanImport implements WithMultipleSheets
{
    private readonly NilaiPertemuanSheetImport $sheet;

    /**
     * @param  Collection<int, PesertaBlok>  $anggota  anggota aktif kelompok pertemuan ini
     * @param  Collection<int, KomponenPenilaianBlok>  $komponen  rubrik milik aturan kegiatan pertemuan ini
     */
    public function __construct(Collection $anggota, Collection $komponen)
    {
        $this->sheet = new NilaiPertemuanSheetImport($anggota, $komponen);
    }

    public function sheets(): array
    {
        return [0 => $this->sheet];
    }

    /**
     * Hasil baca: [peserta_blok_id => [komponen_penilaian_blok_id => nilai]].
     *
     * Hanya memuat peserta yang barisnya benar-benar ada di file. Peserta yang tidak ditulis
     * di file sengaja tidak muncul, supaya import sebagian tidak menghapus nilai peserta lain.
     *
     * @return array<int, array<int, string>>
     */
    public function nilai(): array
    {
        return $this->sheet->nilai();
    }

    /**
     * Peta nama kolom komponen -> id `komponen_penilaian_blok`.
     *
     * Dipakai export template DAN import, jadi nama kolom yang ditulis tidak mungkin
     * melenceng dari yang dibaca. Kunci sudah berbentuk slug seperti heading formatter bawaan
     * Maatwebsite (`Str::slug($nilai, '_')`), dan bentuk itu idempoten, sehingga kunci yang
     * sama bisa dipakai sebagai teks header di file maupun sebagai indeks baris hasil
     * `WithHeadingRow`.
     *
     * Batas nilai sengaja TIDAK ikut jadi nama kolom: kalau rubrik diedit setelah template
     * diunduh, file lama harus tetap bisa diimport.
     *
     * @param  Collection<int, KomponenPenilaianBlok>  $komponen
     * @return array<string, int>
     */
    public static function kunciKomponen(Collection $komponen): array
    {
        $peta = [];

        foreach ($komponen as $item) {
            $kunci = Str::slug((string) ($item->komponen_penilaian?->kode ?? ''), '_');

            // `komponen_penilaian.kode` boleh kosong dan tidak dijamin unik antar komponen
            // (dua kode berbeda bisa menghasilkan slug sama), sedangkan id komponen per blok
            // selalu unik. Jadi id dipakai sebagai pembeda terakhir.
            if ($kunci === '' || isset($peta[$kunci])) {
                $kunci = ($kunci === '' ? 'komponen' : $kunci).'_'.$item->id;
            }

            $peta[$kunci] = (int) $item->id;
        }

        return $peta;
    }
}
