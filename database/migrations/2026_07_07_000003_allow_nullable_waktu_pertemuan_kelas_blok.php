<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pertemuan_kelas_blok', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->change();
            $table->time('jam_mulai')->nullable()->change();
            $table->time('jam_selesai')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('UPDATE pertemuan_kelas_blok SET tanggal = CURRENT_DATE WHERE tanggal IS NULL');
        DB::statement("UPDATE pertemuan_kelas_blok SET jam_mulai = '00:00:00' WHERE jam_mulai IS NULL");
        DB::statement("UPDATE pertemuan_kelas_blok SET jam_selesai = '00:01:00' WHERE jam_selesai IS NULL");

        Schema::table('pertemuan_kelas_blok', function (Blueprint $table) {
            $table->date('tanggal')->nullable(false)->change();
            $table->time('jam_mulai')->nullable(false)->change();
            $table->time('jam_selesai')->nullable(false)->change();
        });
    }
};
