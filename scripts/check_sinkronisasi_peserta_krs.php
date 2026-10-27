<?php

require dirname(__DIR__).'/vendor/autoload.php';

use App\Support\Akademik\Sync\SinkronisasiPesertaKrs;

$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$payloadApi = [
    'nim' => ' 12345 ',
    'id_semester' => '20251',
    'status_krs' => 'disetujui',
    'detail' => [
        ['kd_kelas' => 'KLS-1'],
        ['kd_kelas' => 'KLS-2'],
    ],
];
$mapped = SinkronisasiPesertaKrs::petakanHeader($payloadApi, '20251');

$check($mapped['diterima'] === 2, 'Jumlah detail KRS wajib menjadi jumlah data diterima.');
$check($mapped['tidak_valid'] === 0, 'Payload nested API yang valid tidak boleh ditolak.');
$check($mapped['items'] === [
    [
        'nim' => '12345',
        'id_sumber_kelas' => 'KLS-1',
        'id_sumber_krs' => null,
        'status_sumber' => 'disetujui',
        'sumber_json' => [
            'header' => ['nim' => ' 12345 ', 'id_semester' => '20251', 'status_krs' => 'disetujui'],
            'detail' => ['kd_kelas' => 'KLS-1'],
        ],
    ],
    [
        'nim' => '12345',
        'id_sumber_kelas' => 'KLS-2',
        'id_sumber_krs' => null,
        'status_sumber' => 'disetujui',
        'sumber_json' => [
            'header' => ['nim' => ' 12345 ', 'id_semester' => '20251', 'status_krs' => 'disetujui'],
            'detail' => ['kd_kelas' => 'KLS-2'],
        ],
    ],
], 'Setiap detail KRS wajib dipetakan memakai NIM header.');

$payloadApi['detail'][] = ['kd_kelas' => ''];
$mappedSebagian = SinkronisasiPesertaKrs::petakanHeader($payloadApi, '20251');
$check($mappedSebagian['diterima'] === 3, 'Detail tidak valid tetap wajib dihitung sebagai data diterima.');
$check($mappedSebagian['tidak_valid'] === 1, 'Detail tidak valid wajib dilewati tanpa menolak header penuh.');
$check(count($mappedSebagian['items']) === 2, 'Detail valid dalam header yang sama wajib tetap diproses.');

echo "OK: seluruh status dan payload nested sinkronisasi KRS sesuai.\n";
