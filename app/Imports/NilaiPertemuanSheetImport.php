<?php

namespace App\Imports;

use App\Models\KomponenPenilaianBlok;
use App\Models\PesertaBlok;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Pembaca sheet "Nilai" pada template nilai pertemuan.
 *
 * Dipisah dari `NilaiPertemuanImport` karena kelas itu harus menjadi `WithMultipleSheets`
 * agar sheet "Petunjuk" tidak ikut diproses; lihat catatan di sana.
 *
 * Seluruh pesan galat memakai nomor baris file supaya dosen bisa langsung memperbaiki.
 * Satu baris yang ditolak membatalkan seluruh berkas: penulisan baru dijalankan pemanggil
 * setelah pembacaan selesai tanpa galat, jadi tidak ada nilai yang tersimpan separuh.
 */
class NilaiPertemuanSheetImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /** @var array<int, array<int, string>> */
    private array $nilai = [];

    /**
     * @param  Collection<int, PesertaBlok>  $anggota
     * @param  Collection<int, KomponenPenilaianBlok>  $komponen
     */
    public function __construct(
        private readonly Collection $anggota,
        private readonly Collection $komponen,
    ) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function nilai(): array
    {
        return $this->nilai;
    }

    public function collection(Collection $rows): void
    {
        if ($this->komponen->isEmpty()) {
            $this->tolak('Rubrik penilaian kegiatan ini belum disusun, jadi belum ada nilai yang bisa diimport.');
        }

        $pertama = $rows->first();

        if ($pertama === null) {
            $this->tolak('File tidak memuat satu pun baris nilai. Unduh template pertemuan ini lalu isi mulai baris kedua.');
        }

        $kunciKomponen = NilaiPertemuanImport::kunciKomponen($this->komponen);

        $this->periksaKolom($pertama, $kunciKomponen);

        $petaNim = $this->petaNim();
        $perId = $this->komponen->keyBy('id');
        $sudahAda = [];

        foreach ($rows as $index => $row) {
            // Baris judul ada di baris 1, jadi indeks 0 adalah baris 2 di file.
            $line = $index + 2;
            $nim = $this->normalkanNim((string) ($row['nim'] ?? ''));

            if ($nim === '') {
                $this->tolak("Baris {$line}: kolom nim wajib diisi.");
            }

            // NIM di luar peta ditolak. Peta dibangun dari anggota kelompok pertemuan ini yang
            // sudah di-scope di database, jadi file tidak bisa dipakai menilai peserta blok
            // lain, peserta kelompok lain, maupun peserta yang sudah tidak aktif.
            if (! isset($petaNim[$nim])) {
                $this->tolak("Baris {$line}: nim {$nim} bukan anggota aktif kelompok pertemuan ini.");
            }

            if (isset($sudahAda[$nim])) {
                $this->tolak("Baris {$line}: nim {$nim} ditulis lebih dari sekali.");
            }

            $sudahAda[$nim] = true;
            $pesertaId = $petaNim[$nim];

            foreach ($kunciKomponen as $kunci => $komponenId) {
                $this->nilai[$pesertaId][$komponenId] = $this->bacaNilai(
                    $row[$kunci] ?? null,
                    $perId->get($komponenId),
                    $line,
                );
            }
        }
    }

    /**
     * Kolom diperiksa sekali dari baris data pertama, bukan per baris, karena `WithHeadingRow`
     * memetakan kunci yang sama untuk seluruh baris.
     *
     * @param  Collection<string, mixed>  $pertama
     * @param  array<string, int>  $kunciKomponen
     */
    private function periksaKolom(Collection $pertama, array $kunciKomponen): void
    {
        if (! $pertama->has('nim')) {
            $this->tolak('Kolom nim tidak ada di file. Unduh ulang template pertemuan ini dan jangan mengubah baris judul.');
        }

        foreach (array_keys($kunciKomponen) as $kunci) {
            if (! $pertama->has($kunci)) {
                $this->tolak("Kolom {$kunci} tidak ada di file. Unduh ulang template pertemuan ini dan jangan mengubah baris judul.");
            }
        }
    }

    /**
     * Peta NIM -> peserta_blok_id untuk anggota kelompok pertemuan ini.
     *
     * @return array<string, int>
     */
    private function petaNim(): array
    {
        $peta = [];

        foreach ($this->anggota as $peserta) {
            $nim = $this->normalkanNim((string) ($peserta->mahasiswa?->nim ?? ''));

            if ($nim !== '') {
                $peta[$nim] = (int) $peserta->id_peserta_blok;
            }
        }

        return $peta;
    }

    /**
     * Batas nilai selalu dibaca dari `komponen_penilaian_blok`, bukan dari isi file.
     *
     * Mengembalikan string kosong untuk sel kosong; pemanggil memperlakukan itu sebagai
     * "kosongkan nilai komponen ini", sama seperti mengosongkan isian di layar penilaian.
     */
    private function bacaNilai(mixed $isian, ?KomponenPenilaianBlok $komponen, int $line): string
    {
        $nama = $komponen?->komponen_penilaian?->nama ?: ($komponen?->komponen_penilaian?->kode ?: 'komponen');
        $teks = trim((string) $isian);

        if ($teks === '') {
            return '';
        }

        // Dosen dan Excel berlokal Indonesia menulis desimal dengan koma. Pemisah ribuan tidak
        // ditangani karena batas nilai komponen jauh di bawah seribu, jadi "7,5" tidak ambigu.
        $teks = str_replace(',', '.', $teks);

        if (! is_numeric($teks)) {
            $this->tolak("Baris {$line}: nilai {$nama} harus berupa angka.");
        }

        $nilai = (float) $teks;
        $min = (float) ($komponen?->nilai_min ?? 0);
        $maks = (float) ($komponen?->nilai_maks ?? 0);

        if ($nilai < $min || $nilai > $maks) {
            $this->tolak("Baris {$line}: nilai {$nama} harus di antara {$min} dan {$maks}.");
        }

        return (string) $nilai;
    }

    private function normalkanNim(string $nim): string
    {
        return strtolower(trim($nim));
    }

    /**
     * Kunci `import_nilai` dipakai supaya galat muncul di bawah input file pada komponen
     * penilaian, mengikuti pola `import_dosen` di halaman master data.
     */
    private function tolak(string $pesan): never
    {
        throw ValidationException::withMessages(['import_nilai' => $pesan]);
    }
}
