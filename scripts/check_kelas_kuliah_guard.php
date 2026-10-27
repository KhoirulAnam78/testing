<?php

require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\AnggotaKelasKuliah;
use App\Models\KelasKuliah;
use App\Models\Semester;

$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$check(KelasKuliah::pemetaanDapatDiubah(1, 1, 10), 'Pemetaan sama wajib diizinkan.');
$check(KelasKuliah::pemetaanDapatDiubah(1, 2, 0), 'Kelas kosong wajib dapat dipetakan ulang.');
$check(! KelasKuliah::pemetaanDapatDiubah(1, 2, 1), 'Kelas beranggota tidak boleh pindah antarblok.');
$check(KelasKuliah::pemetaanDapatDiubah(null, 2, 10), 'Pemetaan pertama wajib diizinkan walau kelas beranggota.');
$check(KelasKuliah::dapatDihapus(0), 'Kelas kosong wajib dapat dihapus.');
$check(! KelasKuliah::dapatDihapus(1), 'Kelas beranggota tidak boleh dihapus.');
$check(AnggotaKelasKuliah::kaitanPesertaValid(2, 2, 10, 10), 'Kaitan peserta cocok wajib valid.');
$check(! AnggotaKelasKuliah::kaitanPesertaValid(null, 2, 10, 10), 'Kelas tanpa blok tidak boleh punya tautan peserta blok.');
$check(! AnggotaKelasKuliah::kaitanPesertaValid(2, 3, 10, 10), 'Blok berbeda wajib ditolak.');
$check(! AnggotaKelasKuliah::kaitanPesertaValid(2, 2, 10, 11), 'Mahasiswa berbeda wajib ditolak.');

$semesterApi = new Semester;
$semesterApi->setRawAttributes(['kode' => '20261']);
$semesterLokal = new Semester;
$semesterLokal->setRawAttributes(['kode' => '20262']);
$check($semesterApi->bersumberDariApi(), 'Semester 20261 wajib bersumber dari API.');
$check(! $semesterApi->dikelolaLokal(), 'Semester 20261 tidak boleh dikelola lokal.');
$check($semesterLokal->dikelolaLokal(), 'Semester 20262 wajib dikelola lokal.');
$check(! $semesterLokal->bersumberDariApi(), 'Semester 20262 tidak boleh menerima sinkronisasi inbound.');

echo "Kelas kuliah guard checks passed.\n";
