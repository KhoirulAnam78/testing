<?php

require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\Semester;
use App\Support\Akademik\Sync\SinkronisasiKelasKuliah;

$service = (new ReflectionClass(SinkronisasiKelasKuliah::class))->newInstanceWithoutConstructor();
$map = new ReflectionMethod($service, 'map');
$semester = new Semester;
$semester->setRawAttributes(['kode' => '20261']);
$payload = [
    'kd_kelas' => '111KN10516187',
    'kd_prodi' => '111',
    'kd_mk' => '11016101',
    'id_semester' => '20261',
    'kelas_pararel' => 'A',
    'max_peserta' => 100,
];
$expected = [
    'id_sumber' => '111KN10516187',
    'kode_prodi' => '111',
    'kode_mata_kuliah' => '11016101',
    'kode' => 'A',
    'nama' => 'Kelas A',
    'kapasitas' => 100,
    'sumber_json' => $payload,
];

assert($map->invoke($service, $payload, 0, $semester) === $expected);

$payload['kd_kelas'] = '';
assert(
    $map->invoke($service, $payload, 0, $semester)['id_sumber']
    === 'fallback:20261|111|11016101|A'
);

echo "Sinkronisasi kelas kuliah mapper checks passed.\n";
