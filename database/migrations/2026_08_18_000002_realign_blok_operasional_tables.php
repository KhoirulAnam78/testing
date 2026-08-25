<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 4 - Blok menjadi pusat operasional.
 *
 * Peserta, kelompok, dan pertemuan dipindahkan dari `kelas` ke `blok`.
 * `kelas` turun pangkat menjadi rombel opsional di dalam satu blok, sehingga
 * seluruh kode operasional hanya punya satu jalur: blok -> peserta -> kelompok -> pertemuan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Urutan drop mengikuti arah foreign key, dari tabel terluar ke induknya.
        Schema::dropIfExists('dosen_pertemuan_kelas_blok');
        Schema::dropIfExists('pertemuan_kelas_blok');
        Schema::dropIfExists('anggota_kelompok_kelas_blok');
        Schema::dropIfExists('kelompok_kelas_blok');
        Schema::dropIfExists('peserta_kelas');
        Schema::dropIfExists('kelas');

        // Rombel opsional milik satu blok. Prodi, semester, dan mata kuliah tidak
        // diduplikasi di sini karena semuanya sudah dimiliki `blok`.
        Schema::create('kelas', function (Blueprint $table) {
            $table->id('id_kelas');
            $table->foreignId('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->string('kode');
            $table->string('nama')->nullable();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['blok_id', 'kode']);
        });

        Schema::create('peserta_blok', function (Blueprint $table) {
            $table->id('id_peserta_blok');
            $table->foreignId('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa', 'id_mahasiswa')->restrictOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas', 'id_kelas')->nullOnDelete();
            $table->enum('status', ['aktif', 'mengulang', 'batal', 'selesai'])->default('aktif');
            $table->date('tanggal_masuk')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['blok_id', 'mahasiswa_id']);
            $table->index(['blok_id', 'status']);
        });

        Schema::create('kelompok_blok', function (Blueprint $table) {
            $table->id('id_kelompok_blok');
            $table->foreignId('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->foreignId('aturan_kegiatan_blok_id')->constrained('aturan_kegiatan_blok')->restrictOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas', 'id_kelas')->nullOnDelete();
            $table->string('kode');
            $table->string('nama');
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['blok_id', 'aturan_kegiatan_blok_id', 'kode'], 'kelompok_blok_unique');
            $table->index(['blok_id', 'aturan_kegiatan_blok_id']);
        });

        Schema::create('anggota_kelompok_blok', function (Blueprint $table) {
            $table->id('id_anggota_kelompok_blok');
            $table->foreignId('kelompok_blok_id')->constrained('kelompok_blok', 'id_kelompok_blok')->cascadeOnDelete();
            $table->foreignId('peserta_blok_id')->constrained('peserta_blok', 'id_peserta_blok')->cascadeOnDelete();
            $table->enum('peran', ['anggota', 'ketua'])->default('anggota');
            $table->timestamps();

            $table->unique(['kelompok_blok_id', 'peserta_blok_id'], 'anggota_kelompok_blok_unique');
        });

        // `materi_rinci_blok_id` dan `kelompok_blok_id` sengaja NOT NULL: seluruh kegiatan
        // blok sudah dipaksa berkelompok, dan MySQL mengizinkan banyak baris NULL pada
        // unique index sehingga versi nullable tidak melindungi updateOrCreate di database.
        Schema::create('pertemuan_blok', function (Blueprint $table) {
            $table->id('id_pertemuan_blok');
            $table->foreignId('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->foreignId('aturan_kegiatan_blok_id')->constrained('aturan_kegiatan_blok')->restrictOnDelete();
            $table->foreignId('materi_rinci_blok_id')->constrained('materi_rinci_blok', 'id_materi_rinci_blok')->cascadeOnDelete();
            $table->foreignId('kelompok_blok_id')->constrained('kelompok_blok', 'id_kelompok_blok')->cascadeOnDelete();
            $table->date('tanggal')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('ruangan')->nullable();
            $table->string('topik')->nullable();
            $table->unsignedTinyInteger('jumlah_sesi')->default(1);
            $table->unsignedSmallInteger('durasi_menit_per_sesi')->nullable();
            $table->enum('status', ['draft', 'terjadwal', 'berlangsung', 'selesai', 'batal'])->default('draft')->index();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['blok_id', 'materi_rinci_blok_id', 'kelompok_blok_id'], 'pertemuan_blok_unique');
            $table->index(['blok_id', 'tanggal']);
            $table->index(['blok_id', 'aturan_kegiatan_blok_id']);
        });

        Schema::create('dosen_pertemuan_blok', function (Blueprint $table) {
            $table->id('id_dosen_pertemuan_blok');
            $table->foreignId('pertemuan_blok_id')->constrained('pertemuan_blok', 'id_pertemuan_blok')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosen', 'id_dosen')->restrictOnDelete();
            $table->enum('peran', ['pengampu', 'tutor', 'fasilitator'])->default('pengampu');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['pertemuan_blok_id', 'dosen_id'], 'dosen_pertemuan_blok_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen_pertemuan_blok');
        Schema::dropIfExists('pertemuan_blok');
        Schema::dropIfExists('anggota_kelompok_blok');
        Schema::dropIfExists('kelompok_blok');
        Schema::dropIfExists('peserta_blok');
        Schema::dropIfExists('kelas');

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
        });
    }
};
