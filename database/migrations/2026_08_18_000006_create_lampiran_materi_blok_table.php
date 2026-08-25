<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repositori tautan modul dan video materi pembelajaran.
 *
 * Lampiran melekat ke materi, bukan ke dosen pengampu. `dosen_pertemuan_blok` tidak
 * memakai soft delete dan disinkronkan ulang setiap kali pemetaan disimpan, sehingga
 * lampiran yang melekat ke dosen akan hilang begitu dosen pengampu diganti.
 *
 * `pertemuan_blok_id` NULL berarti lampiran default: berlaku untuk semua kelompok pada
 * materi tersebut. Terisi berarti khusus satu pertemuan (satu kelompok). Pola yang sama
 * dengan `materi_rinci_blok.tanggal_rencana` yang menjadi default jadwal tiap kelompok.
 *
 * Sengaja tanpa unique business key: baris hanya ditambah dan dihapus, tidak pernah
 * lewat `updateOrCreate` atas kunci bisnis. Jadi jebakan "baris soft-deleted tetap
 * menempati unique index" pada tabel operasional lain tidak berlaku di sini.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lampiran_materi_blok', function (Blueprint $table) {
            $table->id('id_lampiran_materi_blok');
            $table->foreignId('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->foreignId('materi_rinci_blok_id')->constrained('materi_rinci_blok', 'id_materi_rinci_blok')->cascadeOnDelete();
            $table->foreignId('pertemuan_blok_id')->nullable()->constrained('pertemuan_blok', 'id_pertemuan_blok')->cascadeOnDelete();
            $table->enum('jenis', ['modul', 'video']);
            $table->string('judul');
            $table->string('url', 1000);
            $table->text('deskripsi')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->foreignId('dibuat_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['materi_rinci_blok_id', 'pertemuan_blok_id'], 'lampiran_materi_scope_index');
            $table->index(['blok_id', 'jenis']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lampiran_materi_blok');
    }
};
