<?php

require __DIR__.'/../vendor/autoload.php';

use App\Support\Akademik\AkademikClient;
use App\Support\Akademik\Sync\SinkronisasiSemester;

$service = new SinkronisasiSemester(new AkademikClient);
$map = new ReflectionMethod($service, 'map');
$hasil = $map->invoke($service, [
    'id_semester' => '20261',
    'kd_ta' => '2026',
    'kd_smt' => '1',
    'is_semester_aktif' => 1,
    'tgl_mulai_kuliah1' => '2026-09-01T00:00:00',
    'tgl_akhir_kuliah1' => null,
    'tgl_mulai_krs' => '2026-08-10T00:00:00',
    'tgl_akhir_krs' => '2026-08-28T23:59:00',
], 0);

$seharusnya = [
    'nama' => 'ganjil',
    'tahun' => 2026,
    'kode' => '20261',
    'tanggal_mulai' => '2026-09-01',
    'tanggal_selesai' => null,
    'kontrak_mulai' => '2026-08-10 00:00:00',
    'kontrak_selesai' => '2026-08-28 23:59:00',
    'is_aktif' => true,
];

if ($hasil !== $seharusnya) {
    throw new RuntimeException('Mapping semester API tidak sesuai kontrak.');
}

echo "Mapping semester API valid.\n";
