<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_registrasi_mahasiswa', function (Blueprint $table) {
            $table->id('id_status_registrasi_mahasiswa');
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa', 'id_mahasiswa')->restrictOnDelete();
            $table->foreignId('semester_id')->constrained('semester', 'id_semester')->restrictOnDelete();
            $table->enum('status', ['aktif', 'cuti', 'nonaktif']);
            $table->timestamps();

            $table->unique(['mahasiswa_id', 'semester_id'], 'status_registrasi_mahasiswa_unik');
            $table->index(['semester_id', 'status']);
        });

        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->index(['prodi_id', 'angkatan'], 'mahasiswa_prodi_angkatan_index');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: histori status registrasi mahasiswa tidak boleh dihapus otomatis.');
    }
};
