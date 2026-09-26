<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skala_nilai', function (Blueprint $table) {
            $table->id('id_skala_nilai');
            $table->string('nama');
            $table->unsignedSmallInteger('versi');
            $table->boolean('aktif')->default(false)->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['nama', 'versi']);
        });

        Schema::create('skala_nilai_detail', function (Blueprint $table) {
            $table->id('id_skala_nilai_detail');
            $table->foreignId('skala_nilai_id')->constrained('skala_nilai', 'id_skala_nilai')->restrictOnDelete();
            $table->decimal('nilai_angka_min', 5, 2);
            $table->decimal('nilai_angka_max', 5, 2);
            $table->string('nilai_huruf', 10);
            $table->decimal('nilai_indeks', 4, 2);
            $table->unsignedSmallInteger('urutan_mutu');
            $table->boolean('lulus')->default(true);
            $table->timestamps();

            $table->unique(['skala_nilai_id', 'nilai_angka_min']);
            $table->unique(['skala_nilai_id', 'nilai_huruf']);
            $table->unique(['skala_nilai_id', 'urutan_mutu']);
        });

        Schema::create('kurikulum', function (Blueprint $table) {
            $table->id('id_kurikulum');
            $table->foreignId('prodi_id')->constrained('prodi', 'id_prodi')->restrictOnDelete();
            $table->foreignId('skala_nilai_id')->constrained('skala_nilai', 'id_skala_nilai')->restrictOnDelete();
            $table->string('kode');
            $table->string('nama');
            $table->unsignedSmallInteger('tahun_berlaku');
            $table->decimal('sks_lulus', 5, 1);
            $table->unsignedSmallInteger('semester_normal');
            $table->text('deskripsi')->nullable();
            $table->enum('status', ['draft', 'aktif', 'nonaktif', 'arsip'])->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['prodi_id', 'kode']);
            $table->unique(['prodi_id', 'tahun_berlaku']);
        });

        Schema::create('kurikulum_mata_kuliah', function (Blueprint $table) {
            $table->id('id_kurikulum_mata_kuliah');
            $table->foreignId('kurikulum_id')->constrained('kurikulum', 'id_kurikulum')->cascadeOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->restrictOnDelete();
            $table->unsignedSmallInteger('semester_urutan');
            $table->boolean('apakah_wajib')->default(true);
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kurikulum_id', 'mata_kuliah_id']);
            $table->index(['kurikulum_id', 'semester_urutan']);
        });

        Schema::create('aturan_kurikulum_mata_kuliah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_mata_kuliah_id')->unique()->constrained('kurikulum_mata_kuliah', 'id_kurikulum_mata_kuliah')->cascadeOnDelete();
            $table->enum('periode_pengambilan_ulang', ['mengikuti_semester_kurikulum', 'ganjil', 'genap', 'semua'])->default('mengikuti_semester_kurikulum');
            $table->boolean('aktif')->default(true);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('prasyarat_mata_kuliah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_mata_kuliah_id')->constrained('kurikulum_mata_kuliah', 'id_kurikulum_mata_kuliah')->cascadeOnDelete();
            $table->foreignId('prasyarat_kurikulum_mata_kuliah_id')->constrained('kurikulum_mata_kuliah', 'id_kurikulum_mata_kuliah')->restrictOnDelete();
            $table->foreignId('skala_nilai_detail_id')->constrained('skala_nilai_detail', 'id_skala_nilai_detail')->restrictOnDelete();
            $table->boolean('aktif')->default(true);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['kurikulum_mata_kuliah_id', 'prasyarat_kurikulum_mata_kuliah_id'], 'prasyarat_mata_kuliah_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prasyarat_mata_kuliah');
        Schema::dropIfExists('aturan_kurikulum_mata_kuliah');
        Schema::dropIfExists('kurikulum_mata_kuliah');
        Schema::dropIfExists('kurikulum');
        Schema::dropIfExists('skala_nilai_detail');
        Schema::dropIfExists('skala_nilai');
    }
};
