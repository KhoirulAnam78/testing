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
        Schema::dropIfExists('modul_blok');
        Schema::dropIfExists('dosen_pertemuan_blok');
        Schema::dropIfExists('pertemuan_blok');
        Schema::dropIfExists('anggota_kelompok_blok');
        Schema::dropIfExists('kelompok_blok');
        Schema::dropIfExists('peserta_blok');

        Schema::table('blok', function (Blueprint $table) {
            if (Schema::hasColumn('blok', 'mata_kuliah_id')) {
                $table->dropForeign(['mata_kuliah_id']);
                $table->dropColumn('mata_kuliah_id');
            }
        });

        Schema::table('mata_kuliah', function (Blueprint $table) {
            if (! Schema::hasColumn('mata_kuliah', 'blok_id')) {
                $table->foreignId('blok_id')
                    ->nullable()
                    ->after('prodi_id')
                    ->constrained('blok')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mata_kuliah', function (Blueprint $table) {
            if (Schema::hasColumn('mata_kuliah', 'blok_id')) {
                $table->dropForeign(['blok_id']);
                $table->dropColumn('blok_id');
            }
        });

        Schema::table('blok', function (Blueprint $table) {
            if (! Schema::hasColumn('blok', 'mata_kuliah_id')) {
                $table->foreignId('mata_kuliah_id')
                    ->nullable()
                    ->after('semester_id')
                    ->constrained('mata_kuliah')
                    ->nullOnDelete();
            }
        });
    }
};
