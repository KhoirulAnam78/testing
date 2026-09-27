<?php

require __DIR__.'/../vendor/autoload.php';

use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Support\KelayakanKontrakBlok;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

CarbonImmutable::setTestNow('2026-09-27 12:00:00');

$semester = new Semester;
$semester->setRawAttributes([
    'is_aktif' => true,
    'kontrak_mulai' => '2026-09-27 08:00:00',
    'kontrak_selesai' => '2026-09-27 16:00:00',
]);

if (! $semester->kontrakSedangDibuka()) {
    throw new RuntimeException('Masa kontrak aktif seharusnya terbuka.');
}

$semester->is_aktif = false;

if ($semester->kontrakSedangDibuka()) {
    throw new RuntimeException('Semester nonaktif tidak boleh membuka kontrak.');
}

$semester->is_aktif = true;
$semester->kontrak_selesai = '2026-09-27 11:59:59';

if ($semester->kontrakSedangDibuka()) {
    throw new RuntimeException('Masa kontrak yang berakhir harus tertutup.');
}

CarbonImmutable::setTestNow();

$mahasiswa = new Mahasiswa;
$mahasiswa->angkatan = 2024;
$semesterBerjalan = new ReflectionMethod(KelayakanKontrakBlok::class, 'semesterBerjalan');
$service = new KelayakanKontrakBlok;

foreach ([
    ['nama' => 'ganjil', 'tahun' => 2024, 'expected' => 1],
    ['nama' => 'genap', 'tahun' => 2024, 'expected' => 2],
    ['nama' => 'ganjil', 'tahun' => 2025, 'expected' => 3],
    ['nama' => 'pendek', 'tahun' => 2025, 'expected' => null],
] as $case) {
    $periode = new Semester;
    $periode->nama = $case['nama'];
    $periode->tahun = $case['tahun'];

    if ($semesterBerjalan->invoke($service, $mahasiswa, $periode) !== $case['expected']) {
        throw new RuntimeException('Perhitungan semester berjalan tidak valid.');
    }
}

echo "Aturan masa kontrak blok valid.\n";
