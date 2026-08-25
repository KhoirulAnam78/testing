<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggota_kelompok_blok', function (Blueprint $table) {
            $table->dropColumn('peran');
        });
    }

    public function down(): void
    {
        Schema::table('anggota_kelompok_blok', function (Blueprint $table) {
            $table->enum('peran', ['anggota', 'ketua'])->default('anggota')->after('peserta_blok_id');
        });
    }
};
