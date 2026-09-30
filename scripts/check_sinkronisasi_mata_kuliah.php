<?php

require __DIR__.'/../vendor/autoload.php';

use App\Support\Akademik\AkademikClient;
use App\Support\Akademik\Sync\SinkronisasiMataKuliah;

$service = new SinkronisasiMataKuliah(new AkademikClient);
$map = new ReflectionMethod($service, 'map');
$mapItems = new ReflectionMethod($service, 'mapItems');
$hasil = $map->invoke($service, [
    'kd_mk' => '11016101',
    'nm_mk' => 'PENGANTAR ILMU KEDOKTERAN DASAR',
    'sks_mk' => 4,
    'kd_prodi' => '111',
], 0, '111');

$seharusnya = [
    'kode' => '11016101',
    'nama' => 'PENGANTAR ILMU KEDOKTERAN DASAR',
    'sks' => 4.0,
];

if ($hasil !== $seharusnya) {
    throw new RuntimeException('Mapping mata kuliah API tidak sesuai kontrak.');
}

$hasilPencarian = $mapItems->invoke($service, [
    [
        'kd_mk' => '1101610',
        'nm_mk' => 'Kode parsial',
        'sks_mk' => 2,
        'kd_prodi' => '111',
    ],
    [
        'kd_mk' => 'BIO101',
        'nm_mk' => 'BIO101 disebut dalam nama',
        'sks_mk' => 2,
        'kd_prodi' => '111',
    ],
    [
        'kd_mk' => '11016101',
        'nm_mk' => 'Prodi berbeda',
        'sks_mk' => 3,
        'kd_prodi' => '222',
    ],
    [
        'kd_mk' => ' 11016101 ',
        'nm_mk' => 'PENGANTAR ILMU KEDOKTERAN DASAR',
        'sks_mk' => 4,
        'kd_prodi' => ' 111 ',
    ],
], 0, '111', '11016101');

if ($hasilPencarian !== ['11016101' => $seharusnya]) {
    throw new RuntimeException('Pencarian mata kuliah tidak memilih kode dan prodi exact.');
}

$tidakDitemukan = $mapItems->invoke($service, [[
    'kd_mk' => '11016102',
    'nm_mk' => 'Kode lain',
    'sks_mk' => 2,
    'kd_prodi' => '111',
]], 0, '111', '11016101');

if ($tidakDitemukan !== []) {
    throw new RuntimeException('Pencarian mata kuliah menerima kode yang tidak exact.');
}

try {
    $mapItems->invoke($service, [
        [
            'kd_mk' => '11016101',
            'nm_mk' => 'Nama pertama',
            'sks_mk' => 4,
            'kd_prodi' => '111',
        ],
        [
            'kd_mk' => '11016101',
            'nm_mk' => 'Nama berbeda',
            'sks_mk' => 4,
            'kd_prodi' => '111',
        ],
    ], 0, '111', '11016101');

    throw new RuntimeException('Duplikat kode dengan nilai berbeda tidak ditolak.');
} catch (ReflectionException $e) {
    throw $e;
} catch (Throwable $e) {
    if (! $e instanceof DomainException || $e->getMessage() !== 'Data mata kuliah 11016101 duplikat dengan nilai berbeda.') {
        throw $e;
    }
}

echo "Mapping, exact match, hasil kosong, dan deteksi duplikat mata kuliah valid.\n";
