<?php

require __DIR__.'/../vendor/autoload.php';

use App\Models\Mahasiswa;
use App\Support\Akademik\AkademikClient;
use App\Support\Akademik\Sync\SinkronisasiMahasiswa;

$service = new SinkronisasiMahasiswa(new AkademikClient);
$map = new ReflectionMethod($service, 'map');
$mapStatus = new ReflectionMethod($service, 'mapStatus');
$handleSatu = new ReflectionMethod($service, 'handleSatu');

if (Mahasiswa::STATUS_SYNC_PENDING !== 'pending' || Mahasiswa::STATUS_SYNC_SYNCED !== 'synced') {
    throw new RuntimeException('Konstanta status sinkronisasi mahasiswa tidak valid.');
}

if (! $handleSatu->isPublic()) {
    throw new RuntimeException('Sinkronisasi satu mahasiswa tidak tersedia.');
}

$source = file_get_contents(__DIR__.'/../app/Support/Akademik/Sync/SinkronisasiMahasiswa.php');

if (! is_string($source) || ! str_contains($source, "'/api/data/mahasiswa/'.rawurlencode(\$nim)")) {
    throw new RuntimeException('Sinkronisasi mahasiswa tidak memakai endpoint detail NIM exact.');
}

$hasil = $map->invoke($service, [
    'nim' => ' 20260001 ',
    'nama' => 'Nama Mahasiswa',
    'email_mhs' => ' MHS@EXAMPLE.COM ',
    'hp_mhs' => '08123456789',
    'angkatan' => '2026',
    'kd_prodi' => '111',
    'kd_kur' => 'S1DOK2025',
    'status' => 'A',
], ['111' => 7]);

if ($hasil !== [
    'nim' => '20260001',
    'nama' => 'Nama Mahasiswa',
    'email' => 'mhs@example.com',
    'no_hp' => '08123456789',
    'angkatan' => 2026,
    'status' => 'aktif',
    'prodi_id' => 7,
    'kode_kurikulum' => 'S1DOK2025',
]) {
    throw new RuntimeException('Mapping mahasiswa API tidak sesuai kontrak.');
}

$status = ['A' => 'aktif', 'cuti' => 'cuti', 'lulus' => 'lulus', 'nonaktif' => 'nonaktif'];

foreach ($status as $api => $lokal) {
    if ($mapStatus->invoke($service, $api) !== $lokal) {
        throw new RuntimeException("Mapping status {$api} tidak valid.");
    }
}

foreach ([
    ['email_mhs' => '', 'message' => 'Email kosong atau tidak valid.'],
    ['kd_prodi' => '999', 'message' => 'Kode prodi 999 tidak ditemukan pada data lokal.'],
    ['kd_kur' => '', 'message' => 'Kode kurikulum kosong atau tidak valid.'],
    ['status' => '?', 'message' => 'Status mahasiswa API tidak dikenali.'],
] as $case) {
    try {
        $message = $case['message'];
        unset($case['message']);
        $map->invoke($service, array_replace([
            'nim' => '20260001',
            'nama' => 'Nama Mahasiswa',
            'email_mhs' => 'mhs@example.com',
            'hp_mhs' => null,
            'angkatan' => '2026',
            'kd_prodi' => '111',
            'kd_kur' => 'S1DOK2025',
            'status' => 'A',
        ], $case), ['111' => 7]);
        throw new RuntimeException('Data mahasiswa tidak valid diterima mapper.');
    } catch (DomainException $e) {
        if ($e->getMessage() !== $message) {
            throw $e;
        }
    }
}

if (! str_contains($source, "->where('prodi_id', \$data['prodi_id'])")
    || ! str_contains($source, "->where('kode', \$data['kode_kurikulum'])")
    || strpos($source, '$kurikulum = Kurikulum::query()') > strpos($source, '$mahasiswa = Mahasiswa::withTrashed()')
) {
    throw new RuntimeException('Pemetaan kurikulum mahasiswa tidak exact atau dijalankan setelah perubahan lokal.');
}

echo "Mapping mahasiswa, status, email, prodi, kurikulum exact, dan metadata sinkronisasi valid.\n";
