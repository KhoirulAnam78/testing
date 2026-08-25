<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presensi mahasiswa per pertemuan blok.
 *
 * Dikunci ke `peserta_blok_id`, bukan `mahasiswa_id`, sehingga mustahil mencatat
 * kehadiran mahasiswa yang tidak terdaftar sebagai peserta blok tersebut. Kelas dan
 * status kepesertaan ikut terbawa lewat relasi. Sesuai `task/task_3.md:252-255`:
 * peserta sesi berasal dari `anggota_kelompok_blok` pada kelompok pertemuan itu.
 *
 * Sengaja tanpa soft delete. Baris ditulis lewat `updateOrCreate` atas unique key,
 * dan baris soft-deleted akan tetap menempati unique index sehingga pola itu rusak.
 * Sama seperti `dosen_pertemuan_blok` dan `anggota_kelompok_blok`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('presensi_pertemuan_blok', function (Blueprint $table) {
            $table->id('id_presensi_pertemuan_blok');
            $table->foreignId('pertemuan_blok_id')->constrained('pertemuan_blok', 'id_pertemuan_blok')->cascadeOnDelete();
            $table->foreignId('peserta_blok_id')->constrained('peserta_blok', 'id_peserta_blok')->cascadeOnDelete();
            $table->enum('status', ['hadir', 'sakit', 'izin', 'alpa'])->default('hadir');
            $table->string('keterangan')->nullable();
            $table->foreignId('dicatat_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pertemuan_blok_id', 'peserta_blok_id'], 'presensi_pertemuan_blok_unique');
            $table->index(['pertemuan_blok_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi_pertemuan_blok');
    }
};
