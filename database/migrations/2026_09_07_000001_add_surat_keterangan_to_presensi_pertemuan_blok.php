<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migration aditif: seluruh kolom nullable agar data presensi lama tetap utuh.
        Schema::table('presensi_pertemuan_blok', function (Blueprint $table) {
            $table->string('path_surat_keterangan')->nullable()->after('keterangan');
            $table->string('nama_file_surat_keterangan')->nullable()->after('path_surat_keterangan');
            $table->unsignedBigInteger('ukuran_file_surat_keterangan')->nullable()->after('nama_file_surat_keterangan');
            $table->string('mime_surat_keterangan', 100)->nullable()->after('ukuran_file_surat_keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('presensi_pertemuan_blok', function (Blueprint $table) {
            $table->dropColumn([
                'path_surat_keterangan',
                'nama_file_surat_keterangan',
                'ukuran_file_surat_keterangan',
                'mime_surat_keterangan',
            ]);
        });
    }
};
