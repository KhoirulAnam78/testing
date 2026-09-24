<?php

require __DIR__.'/../vendor/autoload.php';

use App\Models\AturanKurikulumMataKuliah;
use App\Models\KurikulumMataKuliah;

$mapping = new KurikulumMataKuliah;
$mapping->setRawAttributes(['semester_urutan' => 3]);
$aturan = new AturanKurikulumMataKuliah;
$aturan->setRawAttributes(['aktif' => true]);
$aturan->setRelation('kurikulum_mata_kuliah', $mapping);

$cases = [
    'mengikuti_semester_kurikulum' => ['ganjil' => true, 'genap' => false, 'pendek' => false],
    'ganjil' => ['ganjil' => true, 'genap' => false, 'pendek' => false],
    'genap' => ['ganjil' => false, 'genap' => true, 'pendek' => false],
    'semua' => ['ganjil' => true, 'genap' => true, 'pendek' => true],
];

foreach ($cases as $periodeAturan => $expectations) {
    $aturan->periode_pengambilan_ulang = $periodeAturan;

    foreach ($expectations as $jenisPeriode => $expected) {
        $actual = $aturan->bolehDiambilUlangPada($jenisPeriode);

        if ($actual !== $expected) {
            throw new RuntimeException("$periodeAturan pada $jenisPeriode menghasilkan nilai salah.");
        }
    }
}

echo "Aturan pengambilan ulang valid.\n";
