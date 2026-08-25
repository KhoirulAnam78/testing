<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen penilaian dan nilai mahasiswa per pertemuan blok.
 *
 * Tiga lapis, mengikuti pola default/override yang sudah dipakai `materi_rinci_blok`
 * (tanggal rencana) dan `lampiran_materi_blok` (tautan default vs milik pertemuan):
 *
 * 1. `komponen_penilaian`          master global, misal Keaktifan, Perilaku, MCQ, OSCE.
 * 2. `komponen_penilaian_kegiatan` standar per jenis kegiatan, misal Tutorial =
 *                                  Keaktifan 0-20 + Perilaku 0-30. Hanya template.
 * 3. `komponen_penilaian_blok`     rubrik milik satu `aturan_kegiatan_blok`, disalin
 *                                  dari standar lalu boleh disesuaikan per blok.
 *
 * Nilai menggantung ke lapis 3, bukan ke master, supaya batas `nilai_min`/`nilai_maks`
 * ikut terkunci pada blok tersebut. Mengubah standar di kemudian hari tidak menggeser
 * tafsir nilai blok yang sudah lampau.
 *
 * Skala memakai batas per komponen (`nilai_min`/`nilai_maks`), bukan bobot persen.
 * Skor mentah tiap komponen dijumlah apa adanya; tidak ada perhitungan pembobotan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->decimal('nilai_min_default', 6, 2)->default(0);
            $table->decimal('nilai_maks_default', 6, 2)->default(100);
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * Standar penilaian per jenis kegiatan. Sengaja tanpa soft delete: baris disimpan
         * lewat sync-and-prune atas kunci bisnis, dan baris soft-deleted akan tetap
         * menempati unique index. Tidak ada data operasional yang menggantung di sini,
         * jadi hapus permanen aman.
         */
        Schema::create('komponen_penilaian_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_kegiatan_id')->constrained('jenis_kegiatan')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->decimal('nilai_min', 6, 2)->default(0);
            $table->decimal('nilai_maks', 6, 2);
            $table->unsignedSmallInteger('urutan')->default(1)->index();
            $table->timestamps();

            $table->unique(['jenis_kegiatan_id', 'komponen_penilaian_id'], 'komponen_penilaian_kegiatan_unique');
        });

        /**
         * Rubrik milik satu blok. Memakai soft delete, berbeda dari tabel standar di atas,
         * karena `nilai_pertemuan_blok` menggantung ke sini: baris yang dibuang saat form
         * Blok disimpan ulang harus menyisakan nilainya, bukan meng-cascade menghapusnya.
         *
         * Konsekuensinya baris soft-deleted tetap menempati unique index, jadi simpan
         * lewat `withTrashed()->firstOrNew([...kunci bisnis...])` lalu `restore()`, sama
         * seperti `kelas` dan `peserta_blok`. Jangan pakai `updateOrCreate` di sini.
         */
        Schema::create('komponen_penilaian_blok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aturan_kegiatan_blok_id')->constrained('aturan_kegiatan_blok')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->restrictOnDelete();
            $table->decimal('nilai_min', 6, 2)->default(0);
            $table->decimal('nilai_maks', 6, 2);
            $table->unsignedSmallInteger('urutan')->default(1)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['aturan_kegiatan_blok_id', 'komponen_penilaian_id'], 'komponen_penilaian_blok_unique');
        });

        /**
         * Nilai satu mahasiswa untuk satu komponen pada satu pertemuan.
         *
         * Dikunci ke `peserta_blok_id`, bukan `mahasiswa_id`, sama seperti
         * `presensi_pertemuan_blok`: mustahil menilai mahasiswa yang bukan peserta blok.
         *
         * Sengaja tanpa soft delete. Baris ditulis lewat `updateOrCreate` atas unique key
         * dan baris soft-deleted akan tetap menempati unique index. Nilai yang dikosongkan
         * dosen dihapus permanen, sehingga "ada baris" berarti "sudah dinilai".
         *
         * `komponen_penilaian_blok_id` memakai cascade agar hapus permanen blok dan
         * `migrate:fresh` tetap jalan. Pengaman sebenarnya ada di form Blok: komponen yang
         * sudah punya nilai ditolak saat hendak dibuang dari rubrik.
         */
        Schema::create('nilai_pertemuan_blok', function (Blueprint $table) {
            $table->id('id_nilai_pertemuan_blok');
            $table->foreignId('pertemuan_blok_id')->constrained('pertemuan_blok', 'id_pertemuan_blok')->cascadeOnDelete();
            $table->foreignId('peserta_blok_id')->constrained('peserta_blok', 'id_peserta_blok')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_blok_id')->constrained('komponen_penilaian_blok')->cascadeOnDelete();
            $table->decimal('nilai', 6, 2);
            $table->foreignId('dinilai_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['pertemuan_blok_id', 'peserta_blok_id', 'komponen_penilaian_blok_id'],
                'nilai_pertemuan_blok_unique'
            );
            $table->index('peserta_blok_id');
            $table->index('komponen_penilaian_blok_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai_pertemuan_blok');
        Schema::dropIfExists('komponen_penilaian_blok');
        Schema::dropIfExists('komponen_penilaian_kegiatan');
        Schema::dropIfExists('komponen_penilaian');
    }
};
