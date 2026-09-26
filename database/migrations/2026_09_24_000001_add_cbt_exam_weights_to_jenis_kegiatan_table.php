<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->decimal('bobot_ujian_pertama', 5, 2)
                ->default(100)
                ->after('sumber_nilai');
            $table->decimal('bobot_remedial', 5, 2)
                ->default(0)
                ->after('bobot_ujian_pertama');
        });
    }

    public function down(): void
    {
        Schema::table('jenis_kegiatan', function (Blueprint $table) {
            $table->dropColumn(['bobot_ujian_pertama', 'bobot_remedial']);
        });
    }
};
