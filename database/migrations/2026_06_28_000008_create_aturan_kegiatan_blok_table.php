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
        Schema::create('aturan_kegiatan_blok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->foreignId('jenis_kegiatan_id')->constrained('jenis_kegiatan')->restrictOnDelete();
            $table->unsignedSmallInteger('jumlah_pertemuan');
            $table->unsignedSmallInteger('durasi_menit');
            $table->unsignedSmallInteger('jumlah_mahasiswa_per_kelompok')->nullable();
            $table->boolean('perlu_kelompok')->default(false);
            $table->boolean('perlu_presensi')->default(true);
            $table->boolean('perlu_logbook')->default(false);
            $table->unsignedSmallInteger('urutan')->default(1)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['blok_id', 'jenis_kegiatan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aturan_kegiatan_blok');
    }
};
