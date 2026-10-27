<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kelas_kuliah')) {
            Schema::create('kelas_kuliah', function (Blueprint $table) {
                $table->id('id_kelas_kuliah');
                $table->foreignId('blok_id')->constrained('blok')->restrictOnDelete();
                $table->string('kode');
                $table->string('nama')->nullable();
                $table->unsignedSmallInteger('kapasitas')->nullable();
                $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['blok_id', 'kode']);
                $table->index(['blok_id', 'status']);
            });
        }

        if (! Schema::hasTable('anggota_kelas_kuliah')) {
            Schema::create('anggota_kelas_kuliah', function (Blueprint $table) {
                $table->id('id_anggota_kelas_kuliah');
                $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah', 'id_kelas_kuliah')->cascadeOnDelete();
                $table->foreignId('peserta_blok_id')->constrained('peserta_blok', 'id_peserta_blok')->restrictOnDelete();
                $table->timestamps();

                $table->unique('peserta_blok_id');
                $table->index('kelas_kuliah_id');
            });
        }
    }

    public function down(): void
    {
        // Sengaja kosong: rollback destruktif dapat menghapus pemetaan outbound.
    }
};
