<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->boolean('perlu_logbook')->default(false)->after('durasi_menit_default');
        });

        Schema::create('logbook_pertemuan_blok', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pertemuan_blok_id');
            $table->unsignedBigInteger('mahasiswa_id');
            $table->string('path_file');
            $table->string('nama_file_asli');
            $table->unsignedBigInteger('ukuran_file');
            $table->enum('status', ['menunggu', 'valid', 'ditolak'])->default('menunggu');
            $table->text('catatan_validasi')->nullable();
            $table->timestamp('diunggah_pada');
            $table->timestamp('divalidasi_pada')->nullable();
            $table->foreignId('divalidasi_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('pertemuan_blok_id')->references('id_pertemuan_blok')->on('pertemuan_blok')->cascadeOnDelete();
            $table->foreign('mahasiswa_id')->references('id_mahasiswa')->on('mahasiswa')->cascadeOnDelete();
            $table->unique(['pertemuan_blok_id', 'mahasiswa_id'], 'logbook_pertemuan_mahasiswa_unique');
            $table->index(['status', 'pertemuan_blok_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_pertemuan_blok');

        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->dropColumn('perlu_logbook');
        });
    }
};
