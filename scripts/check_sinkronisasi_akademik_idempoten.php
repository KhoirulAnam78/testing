<?php

require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\AkademikOutbox;
use App\Models\MateriRinciBlok;
use App\Models\PertemuanKelasKuliah;

$check = static fn (bool $condition, string $message) => $condition ?: throw new RuntimeException($message);

$materi = [
    ['rinci' => [['pertemuan_ke' => 1], ['pertemuan_ke' => 2]]],
    ['rinci' => [['pertemuan_ke' => 3], ['pertemuan_ke' => 4]]],
];
$check(MateriRinciBlok::pertemuanBerikutnya($materi) === 5, 'Nomor lokal harus lanjut antar materi dalam kegiatan sama.');
$check(MateriRinciBlok::pertemuanDuplikat($materi) === [], 'Urutan unik tidak boleh ditandai duplikat.');
$check(MateriRinciBlok::pertemuanDuplikat([
    ['rinci' => [['pertemuan_ke' => 1]]],
    ['rinci' => [['pertemuan_ke' => 1]]],
]) === [1], 'Duplikat lintas materi dalam kegiatan sama wajib ditolak.');
$check(PertemuanKelasKuliah::kuliahKe([10 => 1, 20 => 2], 20) === 2, 'Mapping lama harus idempoten.');
$check(PertemuanKelasKuliah::kuliahKe([10 => 1, 20 => 2], 30) === 3, 'Mapping baru harus memakai urutan global kelas.');

$payloadA = ['kd_kelas' => 'KLS-1', 'presensi' => [['nim' => '1', 'status_hadir' => 'H']]];
$payloadB = ['presensi' => [['status_hadir' => 'H', 'nim' => '1']], 'kd_kelas' => 'KLS-1'];
$check(
    AkademikOutbox::idempotencyKey('pertemuan.store', 10, $payloadA) === AkademikOutbox::idempotencyKey('pertemuan.store', 10, $payloadB),
    'Payload setara harus menghasilkan idempotency key sama.'
);

echo "OK: penomoran lokal, mapping kelas, dan idempotency key stabil.\n";