<?php

use App\Support\SinkronisasiNilaiCbtBlok;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cbt:sync-nilai-blok {--blok= : Batasi sinkronisasi ke satu ID blok}', function (SinkronisasiNilaiCbtBlok $sinkronisasi) {
    $blok = $this->option('blok');

    if ($blok !== null && (! ctype_digit((string) $blok) || (int) $blok < 1)) {
        $this->error('Opsi --blok harus berupa ID blok positif.');

        return Command::INVALID;
    }

    $hasil = $sinkronisasi->jalankan($blok === null ? null : (int) $blok);

    $this->table(['Data', 'Jumlah'], [
        ['Blok diperiksa', $hasil['blok_diperiksa']],
        ['Blok diproses', $hasil['blok_diproses']],
        ['Nilai disinkronkan', $hasil['nilai_disinkronkan']],
        ['Peserta dilewati', $hasil['peserta_dilewati']],
        ['Ujian dilewati', $hasil['ujian_dilewati']],
    ]);

    foreach ($hasil['masalah'] as $masalah) {
        $this->warn($masalah);
    }

    foreach ($hasil['error'] as $error) {
        $this->error($error);
    }

    return $hasil['error'] === [] ? Command::SUCCESS : Command::FAILURE;
})->purpose('Sinkronkan nilai ujian CBT eksternal ke nilai CBT blok');
