<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dosen', function (Blueprint $table) {
            $table->string('kd_dosen')->nullable()->unique()->after('prodi_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: identifier API dosen tidak boleh dihapus otomatis.');
    }
};