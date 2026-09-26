<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi_pertemuan_blok', function (Blueprint $table) {
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa', 'lain_lain', 'dispensasi'])
                ->default('hadir')
                ->change();
        });
    }

    public function down(): void
    {
        if (DB::table('presensi_pertemuan_blok')->whereIn('status', ['lain_lain', 'dispensasi'])->exists()) {
            throw new RuntimeException('Rollback ditolak: status Lain-lain atau Dispensasi masih dipakai.');
        }

        Schema::table('presensi_pertemuan_blok', function (Blueprint $table) {
            $table->enum('status', ['hadir', 'sakit', 'izin', 'alpa'])
                ->default('hadir')
                ->change();
        });
    }
};