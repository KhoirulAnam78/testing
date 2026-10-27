<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $anggotaTidakValid = DB::table('anggota_kelas_kuliah')
            ->leftJoin('peserta_blok', 'peserta_blok.id_peserta_blok', '=', 'anggota_kelas_kuliah.peserta_blok_id')
            ->whereNull('peserta_blok.mahasiswa_id')
            ->exists();

        if ($anggotaTidakValid) {
            throw new RuntimeException('Backfill anggota kelas kuliah gagal: mahasiswa peserta blok tidak lengkap.');
        }

        $duplikat = DB::table('anggota_kelas_kuliah')
            ->join('peserta_blok', 'peserta_blok.id_peserta_blok', '=', 'anggota_kelas_kuliah.peserta_blok_id')
            ->select('anggota_kelas_kuliah.kelas_kuliah_id', 'peserta_blok.mahasiswa_id')
            ->groupBy('anggota_kelas_kuliah.kelas_kuliah_id', 'peserta_blok.mahasiswa_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplikat) {
            throw new RuntimeException('Backfill anggota kelas kuliah gagal: mahasiswa terduplikasi dalam kelas yang sama.');
        }

        Schema::table('anggota_kelas_kuliah', function (Blueprint $table) {
            $table->foreignId('mahasiswa_id')->nullable()->after('kelas_kuliah_id')
                ->constrained('mahasiswa', 'id_mahasiswa')->restrictOnDelete();
        });

        DB::table('anggota_kelas_kuliah')
            ->join('peserta_blok', 'peserta_blok.id_peserta_blok', '=', 'anggota_kelas_kuliah.peserta_blok_id')
            ->update(['anggota_kelas_kuliah.mahasiswa_id' => DB::raw('peserta_blok.mahasiswa_id')]);

        if (DB::table('anggota_kelas_kuliah')->whereNull('mahasiswa_id')->exists()) {
            throw new RuntimeException('Backfill anggota kelas kuliah gagal: mahasiswa peserta blok tidak lengkap.');
        }

        Schema::table('anggota_kelas_kuliah', function (Blueprint $table) {
            $table->foreignId('mahasiswa_id')->nullable(false)->change();
            $table->foreignId('peserta_blok_id')->nullable()->change();
            $table->index('peserta_blok_id', 'anggota_kelas_peserta_blok_index');
            $table->dropUnique('anggota_kelas_kuliah_peserta_blok_id_unique');
            $table->unique(['kelas_kuliah_id', 'mahasiswa_id'], 'anggota_kelas_mahasiswa_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: identitas mahasiswa anggota kelas tidak boleh dihapus otomatis.');
    }
};
