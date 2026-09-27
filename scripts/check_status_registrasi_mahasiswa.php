<?php

require __DIR__.'/../vendor/autoload.php';

use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StatusRegistrasiMahasiswa;

foreach (['aktif', 'cuti', 'nonaktif'] as $status) {
    if (! in_array($status, StatusRegistrasiMahasiswa::STATUS, true)) {
        throw new RuntimeException("Status {$status} tidak tersedia.");
    }
}

if (in_array('lulus', StatusRegistrasiMahasiswa::STATUS, true)) {
    throw new RuntimeException('Lulus tidak boleh menjadi status registrasi semester.');
}

$mahasiswa = new Mahasiswa;
$semester = new Semester;
$semester->is_aktif = true;
$mahasiswa->status = 'lulus';

if (StatusRegistrasiMahasiswa::dapatDitetapkanUntuk($mahasiswa, $semester)) {
    throw new RuntimeException('Mahasiswa lulus tidak boleh ditetapkan pada semester aktif.');
}

$semester->is_aktif = false;

if (! StatusRegistrasiMahasiswa::dapatDitetapkanUntuk($mahasiswa, $semester)) {
    throw new RuntimeException('Histori semester lampau mahasiswa lulus harus dapat dicatat.');
}

echo "Aturan status registrasi mahasiswa valid.\n";
