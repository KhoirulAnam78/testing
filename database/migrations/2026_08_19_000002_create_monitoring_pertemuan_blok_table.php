<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jurnal pelaksanaan pertemuan (monitoring), satu baris per pertemuan.
 *
 * Dipisah dari `pertemuan_blok` dengan sengaja: kolom `catatan` di tabel itu sudah
 * dipakai perencana lewat modal pemetaan, dan `savePertemuan()` menulis ulang baris
 * pertemuan lewat `withTrashed()->firstOrNew()`. Data pelaksanaan yang menumpang di
 * sana bisa tertimpa saat rencana disimpan ulang.
 *
 * `divalidasi_pada` yang terisi berarti jurnal dan presensi pertemuan itu terkunci.
 * Field ini juga yang akan dibaca fitur logbook mahasiswa (`task/readme_first.md:20`).
 *
 * Tanpa soft delete karena `pertemuan_blok_id` unik dan baris ditulis lewat
 * `updateOrCreate`; baris soft-deleted akan tetap menempati unique index.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monitoring_pertemuan_blok', function (Blueprint $table) {
            $table->id('id_monitoring_pertemuan_blok');
            $table->foreignId('pertemuan_blok_id')->unique()->constrained('pertemuan_blok', 'id_pertemuan_blok')->cascadeOnDelete();
            $table->enum('status_pelaksanaan', ['terlaksana', 'ditunda', 'batal'])->default('terlaksana')->index();
            $table->date('tanggal_realisasi')->nullable();
            $table->time('jam_mulai_realisasi')->nullable();
            $table->time('jam_selesai_realisasi')->nullable();
            $table->string('topik_realisasi')->nullable();
            $table->text('catatan_pelaksanaan')->nullable();
            $table->text('kendala')->nullable();
            $table->foreignId('diisi_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('divalidasi_pada')->nullable()->index();
            $table->foreignId('divalidasi_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_pertemuan_blok');
    }
};
