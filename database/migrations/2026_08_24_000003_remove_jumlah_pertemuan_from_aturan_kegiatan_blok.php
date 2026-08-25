<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aturan_kegiatan_blok', function (Blueprint $table) {
            if (Schema::hasColumn('aturan_kegiatan_blok', 'jumlah_pertemuan')) {
                $table->dropColumn('jumlah_pertemuan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('aturan_kegiatan_blok', function (Blueprint $table) {
            if (! Schema::hasColumn('aturan_kegiatan_blok', 'jumlah_pertemuan')) {
                $table->unsignedSmallInteger('jumlah_pertemuan')->default(1)->after('jenis_kegiatan_id');
            }
        });
    }
};
