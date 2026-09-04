<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->dropColumn('perlu_logbook');
        });
    }

    public function down(): void
    {
        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->boolean('perlu_logbook')->default(false)->after('durasi_menit_default');
        });
    }
};
