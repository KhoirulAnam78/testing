<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id('id_kelas');
            $table->foreignId('prodi_id')->constrained('prodi', 'id_prodi')->restrictOnDelete();
            $table->foreignId('semester_id')->constrained('semester', 'id_semester')->restrictOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->restrictOnDelete();
            $table->foreignId('blok_id')->constrained('blok')->restrictOnDelete();
            $table->string('kode');
            $table->string('nama')->nullable();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->enum('status', ['draft', 'aktif', 'selesai', 'arsip'])->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['semester_id', 'mata_kuliah_id', 'kode']);
            $table->index('prodi_id');
            $table->index('semester_id');
            $table->index('blok_id');
        });

        Schema::create('peserta_kelas', function (Blueprint $table) {
            $table->id('id_peserta_kelas');
            $table->foreignId('kelas_id')->constrained('kelas', 'id_kelas')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa', 'id_mahasiswa')->restrictOnDelete();
            $table->enum('status', ['aktif', 'mengulang', 'batal', 'selesai'])->default('aktif')->index();
            $table->date('tanggal_masuk')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kelas_id', 'mahasiswa_id']);
        });

        Schema::create('kelompok_kelas_blok', function (Blueprint $table) {
            $table->id('id_kelompok_kelas_blok');
            $table->foreignId('kelas_id')->constrained('kelas', 'id_kelas')->cascadeOnDelete();
            $table->foreignId('aturan_kegiatan_blok_id')->constrained('aturan_kegiatan_blok')->restrictOnDelete();
            $table->string('kode');
            $table->string('nama');
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kelas_id', 'aturan_kegiatan_blok_id', 'kode']);
        });

        Schema::create('anggota_kelompok_kelas_blok', function (Blueprint $table) {
            $table->id('id_anggota_kelompok_kelas_blok');
            $table->foreignId('kelompok_kelas_blok_id')
                ->constrained('kelompok_kelas_blok', 'id_kelompok_kelas_blok')
                ->cascadeOnDelete();
            $table->foreignId('peserta_kelas_id')->constrained('peserta_kelas', 'id_peserta_kelas')->cascadeOnDelete();
            $table->enum('peran', ['anggota', 'ketua'])->default('anggota');
            $table->timestamps();

            $table->unique(['kelompok_kelas_blok_id', 'peserta_kelas_id'], 'anggota_kelompok_kelas_blok_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggota_kelompok_kelas_blok');
        Schema::dropIfExists('kelompok_kelas_blok');
        Schema::dropIfExists('peserta_kelas');
        Schema::dropIfExists('kelas');
    }
};
