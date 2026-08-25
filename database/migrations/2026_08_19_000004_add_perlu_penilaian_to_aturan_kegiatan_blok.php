<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda kegiatan blok yang dinilai, sejajar `perlu_presensi` dan `perlu_logbook`.
 *
 * Default `false` supaya blok yang sudah ada tidak berubah perilaku: form nilai baru
 * muncul setelah pengelola menyalakan penanda ini dan menyusun rubriknya di form Blok.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('aturan_kegiatan_blok', function (Blueprint $table) {
            if (! Schema::hasColumn('aturan_kegiatan_blok', 'perlu_penilaian')) {
                $table->boolean('perlu_penilaian')->default(false)->after('perlu_logbook');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aturan_kegiatan_blok', function (Blueprint $table) {
            if (Schema::hasColumn('aturan_kegiatan_blok', 'perlu_penilaian')) {
                $table->dropColumn('perlu_penilaian');
            }
        });
    }
};
