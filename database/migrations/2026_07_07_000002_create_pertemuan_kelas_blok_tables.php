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
        Schema::create('pertemuan_kelas_blok', function (Blueprint $table) {
            $table->id('id_pertemuan_kelas_blok');
            $table->foreignId('kelas_id')->constrained('kelas', 'id_kelas')->cascadeOnDelete();
            $table->foreignId('aturan_kegiatan_blok_id')->constrained('aturan_kegiatan_blok')->restrictOnDelete();
            $table->foreignId('materi_rinci_blok_id')->nullable()->constrained('materi_rinci_blok', 'id_materi_rinci_blok')->nullOnDelete();
            $table->foreignId('kelompok_kelas_blok_id')->nullable()->constrained('kelompok_kelas_blok', 'id_kelompok_kelas_blok')->nullOnDelete();
            $table->date('tanggal')->nullable()->index();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('ruangan')->nullable();
            $table->string('topik')->nullable();
            $table->unsignedTinyInteger('jumlah_sesi')->default(1);
            $table->unsignedSmallInteger('durasi_menit_per_sesi')->nullable();
            $table->enum('status', ['draft', 'terjadwal', 'berlangsung', 'selesai', 'batal'])->default('terjadwal')->index();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('kelas_id');
            $table->index('aturan_kegiatan_blok_id');
            $table->index('materi_rinci_blok_id');
            $table->index('kelompok_kelas_blok_id');
        });

        Schema::create('dosen_pertemuan_kelas_blok', function (Blueprint $table) {
            $table->id('id_dosen_pertemuan_kelas_blok');
            $table->foreignId('pertemuan_kelas_blok_id')
                ->constrained('pertemuan_kelas_blok', 'id_pertemuan_kelas_blok')
                ->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosen', 'id_dosen')->restrictOnDelete();
            $table->enum('peran', ['pengampu', 'tutor', 'fasilitator'])->default('tutor');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['pertemuan_kelas_blok_id', 'dosen_id'], 'dosen_pertemuan_kelas_blok_unique');
            $table->index('dosen_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen_pertemuan_kelas_blok');
        Schema::dropIfExists('pertemuan_kelas_blok');
    }
};
