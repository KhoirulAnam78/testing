<?php

require __DIR__.'/../vendor/autoload.php';

use App\Models\Kurikulum;
use App\Support\Akademik\AkademikClient;
use App\Support\Akademik\Sync\SinkronisasiKurikulum;

$service = new SinkronisasiKurikulum(new AkademikClient);
$mapKurikulum = new ReflectionMethod($service, 'mapKurikulum');
$mapPemetaan = new ReflectionMethod($service, 'mapPemetaan');

if (Kurikulum::STATUS_SYNC_PENDING !== 'pending' || Kurikulum::STATUS_SYNC_SYNCED !== 'synced') {
    throw new RuntimeException('Konstanta status sinkronisasi kurikulum tidak valid.');
}

$source = file_get_contents(__DIR__.'/../app/Support/Akademik/Sync/SinkronisasiKurikulum.php');

if (! is_string($source)
    || ! str_contains($source, 'KurikulumMataKuliah::withTrashed()')
    || ! str_contains($source, "->where('status_sync', Kurikulum::STATUS_SYNC_PENDING)")
    || ! str_contains($source, "'semester_urutan' => \$data['semester_urutan']")
    || str_contains($source, 'KurikulumMataKuliah::query()->delete()')
) {
    throw new RuntimeException('Invariant rekonsiliasi atau upsert pemetaan kurikulum tidak terpenuhi.');
}

$kurikulum = $mapKurikulum->invoke($service, [
    'kd_kur' => 'S1DOK2025',
    'nm_kur' => 'KKNI 2025',
    'th_kur' => '2025',
    'status' => 'A',
    'kd_prodi' => '111',
    'min_sks_evaluasi_akhir' => 144,
    'masa_studi_ideal' => 8,
], 0, '111');

if ($kurikulum !== [
    'kode' => 'S1DOK2025',
    'nama' => 'KKNI 2025',
    'tahun_berlaku' => 2025,
    'sks_lulus' => 144.0,
    'semester_normal' => 8,
    'status' => 'aktif',
]) {
    throw new RuntimeException('Mapping header kurikulum API tidak sesuai kontrak.');
}

$wajib = $mapPemetaan->invoke($service, [
    'kd_kur' => 'S1DOK2025',
    'kd_mk' => '11016101',
    'semester_paket' => 1,
    'jenis_mk' => 'W',
    'kd_prodi' => '111',
], 0, 'S1DOK2025', '111');

if ($wajib !== [
    'kode_mata_kuliah' => '11016101',
    'semester_urutan' => 1,
    'apakah_wajib' => true,
]) {
    throw new RuntimeException('Mapping semester atau jenis mata kuliah wajib tidak sesuai kontrak.');
}

$pilihan = $mapPemetaan->invoke($service, [
    'kd_kur' => 'S1DOK2025',
    'kd_mk' => 'MKPILIHAN',
    'semester_paket' => 8,
    'jenis_mk' => 'P',
    'kd_prodi' => '111',
], 1, 'S1DOK2025', '111');

if ($pilihan['apakah_wajib'] !== false || $pilihan['semester_urutan'] !== 8) {
    throw new RuntimeException('Mapping mata kuliah pilihan tidak sesuai kontrak.');
}

try {
    $mapKurikulum->invoke($service, [
        'kd_kur' => 'S1DOK2025',
        'nm_kur' => 'KKNI 2025',
        'th_kur' => '2025',
        'status' => 'X',
        'kd_prodi' => '111',
        'min_sks_evaluasi_akhir' => 144,
        'masa_studi_ideal' => 8,
    ], 0, '111');

    throw new RuntimeException('Status kurikulum asing tidak ditolak.');
} catch (ReflectionException $e) {
    throw $e;
} catch (Throwable $e) {
    if (! $e instanceof DomainException || $e->getMessage() !== 'Status kurikulum S1DOK2025 dari API tidak dikenali.') {
        throw $e;
    }
}

echo "Mapping header, semester, jenis mata kuliah, status, dan invariant upsert kurikulum valid.\n";
