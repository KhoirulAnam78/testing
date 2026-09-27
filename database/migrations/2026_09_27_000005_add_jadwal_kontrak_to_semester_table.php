<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semester', function (Blueprint $table) {
            $table->dateTime('kontrak_mulai')->nullable()->after('tanggal_selesai');
            $table->dateTime('kontrak_selesai')->nullable()->after('kontrak_mulai');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: jadwal kontrak semester tidak boleh dihapus otomatis.');
    }
};
