<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->dropColumn('bobot_sks_per_pertemuan');
        });
    }

    public function down(): void
    {
        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->decimal('bobot_sks_per_pertemuan', 8, 4)
                ->unsigned()
                ->default(0)
                ->after('durasi_menit_default');
        });
    }
};
