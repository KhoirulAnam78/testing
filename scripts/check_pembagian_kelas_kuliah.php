<?php

require dirname(__DIR__).'/vendor/autoload.php';

use App\Support\PembagianKelasKuliah;

$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$kelas = [
    ['id' => 2, 'kode' => 'B', 'kapasitas' => 3],
    ['id' => 1, 'kode' => 'A', 'kapasitas' => 3],
];
$hasil = PembagianKelasKuliah::rencana([6, 3, 2, 1, 5, 4, 4], $kelas);
$ulang = PembagianKelasKuliah::rencana([1, 2, 3, 4, 5, 6], array_reverse($kelas));
$jumlah = array_count_values($hasil);

$check($hasil === $ulang, 'Pembagian wajib deterministik.');
$check(count($hasil) === 6, 'Peserta duplikat wajib diabaikan.');
$check(max($jumlah) - min($jumlah) <= 1, 'Pembagian wajib merata.');
$check(max($jumlah) <= 3, 'Pembagian melewati kapasitas.');

$overflow = false;
try {
    PembagianKelasKuliah::rencana([1, 2, 3], [['id' => 1, 'kode' => 'A', 'kapasitas' => 2]]);
} catch (OverflowException) {
    $overflow = true;
}
$check($overflow, 'Kapasitas kurang wajib ditolak.');

$check(PembagianKelasKuliah::kodeOtomatis(1) === 'A', 'Kode kelas pertama wajib A.');
$check(PembagianKelasKuliah::kodeOtomatis(26) === 'Z', 'Kode kelas ke-26 wajib Z.');
$kodeOverflow = false;
try {
    PembagianKelasKuliah::kodeOtomatis(27);
} catch (OutOfRangeException) {
    $kodeOverflow = true;
}
$check($kodeOverflow, 'Lebih dari 26 kelas wajib ditolak.');

echo "OK: pembagian deterministik, merata, unik, dan patuh kapasitas.\n";
