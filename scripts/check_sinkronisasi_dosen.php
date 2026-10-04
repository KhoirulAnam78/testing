<?php

require __DIR__.'/../vendor/autoload.php';

use App\Models\Dosen;
use App\Support\Akademik\AkademikClient;
use App\Support\Akademik\Sync\SinkronisasiDosen;

$service = new SinkronisasiDosen(new AkademikClient);
$map = new ReflectionMethod($service, 'map');
$mapStatus = new ReflectionMethod($service, 'mapStatus');
$identifier = new ReflectionMethod($service, 'identifier');
$emailEfektif = new ReflectionMethod($service, 'emailEfektif');
$usernameAkun = new ReflectionMethod($service, 'usernameAkun');
$emailAkunBaru = new ReflectionMethod($service, 'emailAkunBaru');

if (Dosen::STATUS_SYNC_PENDING !== 'pending' || Dosen::STATUS_SYNC_SYNCED !== 'synced') {
    throw new RuntimeException('Konstanta status sinkronisasi dosen tidak valid.');
}

if ($emailEfektif->invoke($service, 'lokal@example.com', 'user@example.com', 'api@example.com') !== 'api@example.com'
    || $emailEfektif->invoke($service, 'lokal@example.com', 'user@example.com', null) !== 'user@example.com'
    || $emailEfektif->invoke($service, 'lokal@example.com', null, null) !== 'lokal@example.com'
    || $emailEfektif->invoke($service, null, null, null) !== null
) {
    throw new RuntimeException('Prioritas email API, user, dan dosen lokal tidak valid.');
}

if ($usernameAkun->invoke($service, ' DOSEN-1 ') !== 'dosen-1'
    || $emailAkunBaru->invoke($service, null, 'dosen-1') !== 'dosen-1@example.com'
    || $emailAkunBaru->invoke($service, 'api@example.com', 'dosen-1') !== 'api@example.com'
) {
    throw new RuntimeException('Username, password awal, atau email fallback akun dosen tidak valid.');
}

$hasil = $map->invoke($service, [
    'kd_dosen' => ' 198001012006041001 ',
    'nip' => ' 198001012006041001 ',
    'nidn' => ' 1234567890 ',
    'nm_dosen' => 'Nama Dosen',
    'email' => ' DOSEN@EXAMPLE.COM ',
    'hp_dosen' => '08123456789',
    'gelar_depan' => 'dr.',
    'gelar_belakang' => 'M.Kes.',
    'bidang_keahlian' => 'Biomedik',
    'kd_prodi' => '111',
    'status' => 'A',
], ['111' => 7]);

if ($hasil !== [
    'kd_dosen' => '198001012006041001',
    'nip' => '198001012006041001',
    'nidn' => '1234567890',
    'nama' => 'Nama Dosen',
    'email' => 'dosen@example.com',
    'no_hp' => '08123456789',
    'gelar_depan' => 'dr.',
    'gelar_belakang' => 'M.Kes.',
    'bidang_keahlian' => 'Biomedik',
    'status' => 'aktif',
    'prodi_id' => 7,
    'kode_prodi' => '111',
]) {
    throw new RuntimeException('Mapping dosen API tidak sesuai kontrak.');
}

$hasilApiNyata = $map->invoke($service, [
    'kd_dosen' => '199602012025052007',
    'nip' => '199602012025052007',
    'nidn' => '2533774675230272',
    'nm_dosen' => 'Putri Rahmadhanita',
    'nm_dosen_f' => 'dr. Putri Rahmadhanita, M.Biomed',
    'email' => 'putri@example.com',
    'mobile' => '081234567890',
    'bidang' => 'Biomedik',
    'kd_prodi' => '111',
    'status' => 'A',
], ['111' => 7]);

if ($hasilApiNyata['gelar_depan'] !== 'dr.'
    || $hasilApiNyata['gelar_belakang'] !== 'M.Biomed'
    || $hasilApiNyata['no_hp'] !== '081234567890'
    || $hasilApiNyata['bidang_keahlian'] !== 'Biomedik'
) {
    throw new RuntimeException('Mapping nama berformat, mobile, atau bidang dosen API tidak valid.');
}

$tanpaNipNidn = $map->invoke($service, [
    'kd_dosen' => '1371041911850001',
    'nip' => null,
    'nidn' => null,
    'nm_dosen' => 'Alpino Maulana Azmy',
    'kd_prodi' => '111',
    'status' => 'A',
], ['111' => 7]);

if ($tanpaNipNidn['kd_dosen'] !== '1371041911850001'
    || $tanpaNipNidn['nip'] !== '1371041911850001'
    || $tanpaNipNidn['nidn'] !== null
) {
    throw new RuntimeException('Mapping kode dosen sebagai NIP tidak valid.');
}

try {
    $map->invoke($service, [
        'kd_dosen' => 'NIP-1',
        'nip' => 'NIP-2',
        'nm_dosen' => 'Identifier Konflik',
    ], []);
    throw new RuntimeException('Kode dosen dan NIP berbeda diterima mapper.');
} catch (ReflectionException $e) {
    throw $e;
} catch (Throwable $e) {
    if (! $e instanceof DomainException || $e->getMessage() !== 'Kode dosen dan NIP API berbeda.') {
        throw $e;
    }
}

$tanpaNamaPersis = $map->invoke($service, [
    'nip' => 'NIP-2',
    'nm_dosen' => 'Nama Dasar',
    'nm_dosen_f' => 'dr. Nama Berbeda, M.Kes.',
], []);

if ($tanpaNamaPersis['kd_dosen'] !== null
    || $tanpaNamaPersis['nip'] !== 'NIP-2'
    || $tanpaNamaPersis['gelar_depan'] !== null
    || $tanpaNamaPersis['gelar_belakang'] !== null
) {
    throw new RuntimeException('Fallback NIP atau pemetaan gelar tidak valid.');
}

if ($identifier->invoke($service, 'KODE-1', 'NIP-1', 'NIDN-1') !== 'KODE-1') {
    throw new RuntimeException('Prioritas pencocokan kode dosen tidak valid.');
}

foreach (['A' => 'aktif', 'aktif' => 'aktif', 'N' => 'nonaktif', '0' => 'nonaktif'] as $api => $lokal) {
    if ($mapStatus->invoke($service, $api) !== $lokal) {
        throw new RuntimeException("Mapping status {$api} tidak valid.");
    }
}

try {
    $map->invoke($service, ['nm_dosen' => 'Tanpa Identifier'], []);
    throw new RuntimeException('Dosen tanpa kode dosen/NIP/NIDN diterima mapper.');
} catch (ReflectionException $e) {
    throw $e;
} catch (Throwable $e) {
    if (! $e instanceof DomainException || $e->getMessage() !== 'Kode dosen, NIP, atau NIDN wajib tersedia dan valid.') {
        throw $e;
    }
}

$source = file_get_contents(__DIR__.'/../app/Support/Akademik/Sync/SinkronisasiDosen.php');

if (! is_string($source)
    || ! str_contains($source, "'/api/data/dosen'")
    || ! str_contains($source, "'limit' => self::LIMIT")
    || ! str_contains($source, "'include_pengampu_fk' => 'false'")
    || ! str_contains($source, "'include_pengampu_fk' => 'true'")
    || ! str_contains($source, 'Dosen::withTrashed()')
    || ! str_contains($source, "'password' => Hash::make(\$username)")
    || ! str_contains($source, "'username' => \$username")
    || ! str_contains($source, "where('username', \$username)->when(\$user")
    || ! str_contains($source, "throw new DomainException('NIP sudah digunakan sebagai username akun lain.')")
    || substr_count($source, "'password' =>") !== 1
    || ! str_contains($source, "\$user->assignRole('dosen')")
    || ! str_contains($source, "\$payload['user_id'] = \$user->id")
    || ! str_contains($source, "...(\$emailApi !== null ? ['email' => \$emailApi] : [])")
    || ! str_contains($source, "\$username.'@example.com'")
    || ! str_contains($source, "throw new DomainException('Email API sudah digunakan akun lain.')")
    || ! str_contains($source, "\$payload['status'] = 'aktif'")
    || str_contains($source, 'Dosen::query()->delete()')
    || str_contains($source, "->update(['status' => 'nonaktif'])")
) {
    throw new RuntimeException('Kontrak pagination, upsert aman, atau larangan hapus data dosen tidak terpenuhi.');
}

echo "Mapping dosen, akun login, status, identifier, pagination, dan upsert tanpa penghapusan valid.\n";
