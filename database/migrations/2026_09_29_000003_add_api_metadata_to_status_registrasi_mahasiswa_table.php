<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `status_registrasi_mahasiswa` MODIFY `status` ENUM('aktif', 'belum_aktif', 'cuti', 'nonaktif') NOT NULL");

        Schema::table('status_registrasi_mahasiswa', function (Blueprint $table) {
            $table->string('jenis_registrasi')->nullable()->after('status');
            $table->string('status_bayar')->nullable()->after('jenis_registrasi');
            $table->timestamp('last_synced_at')->nullable()->after('status_bayar');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: metadata dan status registrasi mahasiswa tidak boleh dihapus otomatis.');
    }
};
