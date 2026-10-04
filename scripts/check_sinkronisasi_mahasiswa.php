<?php

require __DIR__.'/../vendor/autoload.php';

use App\Models\Mahasiswa;
use App\Support\Akademik\AkademikClient;
use App\Support\Akademik\Sync\SinkronisasiMahasiswa;

$service = new SinkronisasiMahasiswa(new AkademikClient);
$map = new ReflectionMethod($service, 'map');
$mapStatus = new ReflectionMethod($service, 'mapStatus');
$handleSatu = new ReflectionMethod($service, 'handleSatu');
$emailEfektif = new ReflectionMethod($service, 'emailEfektif');

if (Mahasiswa::STATUS_SYNC_PENDING !== 'pending' || Mahasiswa::STATUS_SYNC_SYNCED !== 'synced') {
    throw new RuntimeException('Konstanta status sinkronisasi mahasiswa tidak valid.');
}

if (! $handleSatu->isPublic()) {
    throw new RuntimeException('Sinkronisasi satu mahasiswa tidak tersedia.');
}

if ($emailEfektif->invoke($service, 'lokal@example.com', 'user@example.com', 'api@example.com') !== 'api@example.com'
    || $emailEfektif->invoke($service, 'lokal@example.com', 'user@example.com', null) !== 'user@example.com'
    || $emailEfektif->invoke($service, 'lokal@example.com', null, null) !== 'lokal@example.com'
    || $emailEfektif->invoke($service, null, null, null) !== null
) {
    throw new RuntimeException('Prioritas email API, user, dan mahasiswa lokal tidak valid.');
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
    ['email_mhs' => 'email-tidak-valid', 'message' => 'Email tidak valid.'],
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

$tanpaEmailApi = $map->invoke($service, [
    'nim' => '20260001',
    'nama' => 'Nama Mahasiswa',
    'email_mhs' => '',
    'hp_mhs' => null,
    'angkatan' => '2026',
    'kd_prodi' => '111',
    'kd_kur' => 'S1DOK2025',
    'status' => 'A',
], ['111' => 7]);

if ($tanpaEmailApi['email'] !== null) {
    throw new RuntimeException('Email API kosong tidak dipetakan menjadi null.');
}

if (! str_contains($source, "->where('prodi_id', \$data['prodi_id'])")
    || ! str_contains($source, "->where('kode', \$data['kode_kurikulum'])")
    || ! str_contains($source, "\$data['email'] = \$this->emailEfektif(\$mahasiswa?->email, \$user?->email, \$data['email'])")
    || ! str_contains($source, "throw new DomainException('Email API dan email lokal tidak tersedia.')")
    || strpos($source, '$kurikulum = Kurikulum::query()') > strpos($source, '$mahasiswa = Mahasiswa::withTrashed()')
) {
    throw new RuntimeException('Kontrak email lokal atau pemetaan kurikulum mahasiswa tidak terpenuhi.');
}

echo "Mapping mahasiswa, fallback email lokal, status, prodi, kurikulum exact, dan metadata sinkronisasi valid.\n";
