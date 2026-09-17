<?php

/*
 * Description: Dilarang menghapus atau memodifikasi watermark ini
 * Author: Khoirul Anam
 * Date: 2026-01-27 13:47:43
 * LastEditTime: 2026-08-23 15:54:40
 * LastEditors: Khoirulanam
 * Copyright (c) 2026 Khoirulanam4580@gmail.com
 */

use App\Models\LogbookPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\PesertaBlok;
use App\Models\PresensiPertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Route::view('/', 'welcome')->name('welcome');

Route::middleware(['auth', 'route.permission'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')
        ->name('dashboard');

    // kelola menu
    Route::livewire('menu', 'pages::menu.index')
        ->name('menu.index');
    Route::livewire('menu/add-or-edit/{id}', 'pages::menu.add_edit')
        ->name('menu.add_edit');

    // kelola roles
    Route::livewire('roles', 'pages::roles.index')
        ->name('roles.index');
    Route::livewire('roles/add-or-edit/{id}', 'pages::roles.add_edit')
        ->name('roles.add_edit');

    // kelola users
    Route::livewire('users', 'pages::users.index')
        ->name('users.index');
    Route::livewire('users/add-or-edit/{id}', 'pages::users.add_edit')
        ->name('users.add_edit');

    // akademik - prodi
    Route::livewire('prodi', 'pages::prodi.index')
        ->name('prodi.index');
    Route::livewire('prodi/add-or-edit/{id}', 'pages::prodi.add_edit')
        ->name('prodi.add_edit');

    Route::livewire('semester', 'pages::semester.index')
        ->name('semester.index');
    Route::livewire('semester/add-or-edit/{id}', 'pages::semester.add_edit')
        ->name('semester.add_edit');

    Route::livewire('jenis-kegiatan', 'pages::jenis-kegiatan.index')
        ->name('jenis-kegiatan.index');
    Route::livewire('jenis-kegiatan/add-or-edit/{id}', 'pages::jenis-kegiatan.add_edit')
        ->name('jenis-kegiatan.add_edit');

    Route::livewire('dosen', 'pages::dosen.index')
        ->name('dosen.index');
    Route::livewire('dosen/add-or-edit/{id}', 'pages::dosen.add_edit')
        ->name('dosen.add_edit');

    Route::livewire('mahasiswa', 'pages::mahasiswa.index')
        ->name('mahasiswa.index');
    Route::livewire('mahasiswa/add-or-edit/{id}', 'pages::mahasiswa.add_edit')
        ->name('mahasiswa.add_edit');

    Route::livewire('mata-kuliah', 'pages::mata-kuliah.index')
        ->name('mata-kuliah.index');
    Route::livewire('mata-kuliah/add-or-edit/{id}', 'pages::mata-kuliah.add_edit')
        ->name('mata-kuliah.add_edit');

    Route::livewire('blok', 'pages::blok.index')
        ->name('blok.index');
    Route::livewire('blok/add-or-edit/{id}', 'pages::blok.add_edit')
        ->name('blok.add_edit');

    Route::livewire('blok-operasional', 'pages::blok-operasional.index')
        ->name('blok-operasional.index');
    Route::livewire('blok-operasional/detail/{id}', 'pages::blok-operasional.detail')
        ->name('blok-operasional.detail');

    Route::livewire('dpna-blok', 'pages::dpna-blok.index')
        ->name('dpna-blok.index');
    Route::livewire('dpna-blok/detail/{id}', 'pages::dpna-blok.detail')
        ->name('dpna-blok.detail');

    // Portal Saya: halaman yang di-scope ke user yang login, bukan CRUD master data.
    Route::livewire('pertemuan-saya', 'pages::pertemuan-saya.index')
        ->name('pertemuan-saya.index');
    Route::livewire('rekap-sks-saya', 'pages::rekap-sks-dosen.index')
        ->name('rekap-sks-saya.index');
    Route::livewire('materi-saya', 'pages::materi-saya.index')
        ->name('materi-saya.index');

    Route::livewire('rekap-sks-dosen', 'pages::rekap-sks-dosen.index')
        ->name('rekap-sks-dosen.index');

    Route::get('surat-keterangan/{presensi}/download', function (PresensiPertemuanBlok $presensi) {
        abort_unless(
            AksesPertemuanBlok::bolehKelolaPertemuan(auth()->user(), (int) $presensi->pertemuan_blok_id),
            403
        );
        abort_unless(in_array($presensi->status, ['sakit', 'izin'], true), 404);
        abort_unless($presensi->path_surat_keterangan, 404);
        abort_unless(Storage::disk('local')->exists($presensi->path_surat_keterangan), 404);

        return Storage::disk('local')->download(
            $presensi->path_surat_keterangan,
            $presensi->nama_file_surat_keterangan,
            [
                'Content-Type' => $presensi->mime_surat_keterangan,
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    })->name('surat-keterangan.download');

    Route::get('pertemuan/{pertemuan}/daftar-hadir.pdf', function (PertemuanBlok $pertemuan) {
        abort_unless(
            AksesPertemuanBlok::bolehKelolaPertemuan(auth()->user(), (int) $pertemuan->id_pertemuan_blok),
            403
        );

        $pertemuan->load([
            'blok.semester',
            'aturan_kegiatan_blok.jenis_kegiatan',
            'materi_rinci_blok',
            'kelompok_blok',
            'dosen_pertemuan_blok.dosen',
        ]);

        $peserta = PesertaBlok::query()
            ->select('peserta_blok.*')
            ->join('anggota_kelompok_blok', 'anggota_kelompok_blok.peserta_blok_id', '=', 'peserta_blok.id_peserta_blok')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
            ->where('anggota_kelompok_blok.kelompok_blok_id', $pertemuan->kelompok_blok_id)
            ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
            ->with([
                'mahasiswa:id_mahasiswa,nim,nama',
                'presensi_pertemuan_blok' => fn ($query) => $query
                    ->where('pertemuan_blok_id', $pertemuan->id_pertemuan_blok)
                    ->select('id_presensi_pertemuan_blok', 'peserta_blok_id', 'status', 'keterangan'),
            ])
            ->orderBy('mahasiswa.nama')
            ->get();

        $rekap = array_fill_keys([...PresensiPertemuanBlok::SEMUA_STATUS, 'belum_diisi'], 0);

        foreach ($peserta as $item) {
            $status = $item->presensi_pertemuan_blok->first()?->status ?? 'belum_diisi';
            $rekap[$status]++;
        }

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.daftar-hadir-pertemuan', compact('pertemuan', 'peserta', 'rekap'))->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $namaFile = Str::slug(implode('-', array_filter([
            'daftar-hadir',
            $pertemuan->blok?->kode,
            $pertemuan->aturan_kegiatan_blok?->jenis_kegiatan?->nama,
            $pertemuan->kelompok_blok?->kode,
            $pertemuan->materi_rinci_blok?->pertemuan_ke
                ? 'pertemuan-'.$pertemuan->materi_rinci_blok->pertemuan_ke
                : 'sesi-'.$pertemuan->id_pertemuan_blok,
        ]))).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$namaFile.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->name('daftar-hadir-pertemuan.download');

    Route::get('logbook/{logbook}/download', function (LogbookPertemuanBlok $logbook) {
        abort_unless(
            AksesPertemuanBlok::bolehUnduhLogbook(
                auth()->user(),
                (int) $logbook->pertemuan_blok_id,
                (int) $logbook->mahasiswa_id
            ),
            403
        );
        abort_unless(Storage::disk('local')->exists($logbook->path_file), 404);

        return Storage::disk('local')->download($logbook->path_file, $logbook->nama_file_asli, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->name('logbook.download');

});

Route::livewire('profile', 'pages::profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware('guest')->group(function () {
    Route::livewire('test', 'transaksi')
        ->name('transaksi');
});

require __DIR__.'/auth.php';
