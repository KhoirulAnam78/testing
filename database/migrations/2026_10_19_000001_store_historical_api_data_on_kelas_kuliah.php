<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->string('kode_prodi_sumber')->nullable()->after('id_sumber');
            $table->string('kode_mata_kuliah_sumber')->nullable()->after('kode_prodi_sumber');
            $table->json('sumber_json')->nullable()->after('kode_mata_kuliah_sumber');
            $table->foreignId('mata_kuliah_id')->nullable()->change();
            $table->index(['semester_id', 'kode_prodi_sumber', 'kode_mata_kuliah_sumber'], 'kelas_kuliah_identitas_sumber_index');
        });

        Schema::table('anggota_kelas_kuliah', function (Blueprint $table) {
            $table->string('nim_sumber')->nullable()->after('mahasiswa_id');
            $table->string('status_sumber', 100)->nullable()->after('nim_sumber');
            $table->string('sumber', 20)->default('lokal')->after('status_sumber')->index();
            $table->json('sumber_json')->nullable()->after('sumber');
            $table->timestamp('last_synced_at')->nullable()->after('sumber_json');
            $table->foreignId('mahasiswa_id')->nullable()->change();
        });

        DB::table('anggota_kelas_kuliah')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'anggota_kelas_kuliah.mahasiswa_id')
            ->update(['anggota_kelas_kuliah.nim_sumber' => DB::raw('UPPER(TRIM(mahasiswa.nim))')]);

        Schema::table('anggota_kelas_kuliah', function (Blueprint $table) {
            $table->unique(['kelas_kuliah_id', 'nim_sumber'], 'anggota_kelas_nim_sumber_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: payload historis API tidak boleh dihapus otomatis.');
    }
};
