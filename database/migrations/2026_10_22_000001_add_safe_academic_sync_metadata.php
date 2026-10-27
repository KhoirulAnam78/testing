<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggota_kelas_kuliah', function (Blueprint $table) {
            $table->string('id_sumber_krs')->nullable()->after('nim_sumber');
            $table->boolean('aktif_sumber')->default(true)->after('sumber');
            $table->timestamp('missing_since')->nullable()->after('last_synced_at');
            $table->index(['kelas_kuliah_id', 'aktif_sumber'], 'anggota_kelas_aktif_sumber_index');
        });

        Schema::table('snapshot_dpna_peserta', function (Blueprint $table) {
            $table->foreignId('skala_nilai_id')->nullable()->after('nilai_akhir')
                ->constrained('skala_nilai', 'id_skala_nilai')->restrictOnDelete();
            $table->string('nilai_huruf', 10)->nullable()->after('skala_nilai_id');
            $table->decimal('nilai_indeks', 4, 2)->nullable()->after('nilai_huruf');
            $table->boolean('lulus')->nullable()->after('nilai_indeks');
            $table->boolean('boleh_perbaikan')->nullable()->after('lulus');
        });

        Schema::create('pengampu_kelas_kuliah', function (Blueprint $table) {
            $table->id('id_pengampu_kelas_kuliah');
            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah', 'id_kelas_kuliah')->cascadeOnDelete();
            $table->foreignId('dosen_id')->nullable()->constrained('dosen', 'id_dosen')->restrictOnDelete();
            $table->string('kd_dosen_sumber')->nullable();
            $table->string('peran', 50)->default('pengampu');
            $table->boolean('aktif_sumber')->default(true);
            $table->json('sumber_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('missing_since')->nullable();
            $table->timestamps();

            $table->unique(['kelas_kuliah_id', 'kd_dosen_sumber'], 'pengampu_kelas_kd_dosen_unique');
            $table->unique(['kelas_kuliah_id', 'dosen_id'], 'pengampu_kelas_dosen_unique');
        });

        Schema::create('pertemuan_kelas_kuliah', function (Blueprint $table) {
            $table->id('id_pertemuan_kelas_kuliah');
            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah', 'id_kelas_kuliah')->cascadeOnDelete();
            $table->foreignId('materi_rinci_blok_id')->constrained('materi_rinci_blok', 'id_materi_rinci_blok')->restrictOnDelete();
            $table->unsignedSmallInteger('kuliah_ke');
            $table->enum('status_sinkronisasi', ['pending', 'unknown', 'synced', 'failed'])->default('pending')->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['kelas_kuliah_id', 'materi_rinci_blok_id'], 'pertemuan_kelas_materi_unique');
            $table->unique(['kelas_kuliah_id', 'kuliah_ke'], 'pertemuan_kelas_kuliah_ke_unique');
        });

        Schema::create('akademik_outbox', function (Blueprint $table) {
            $table->id('id_akademik_outbox');
            $table->string('aggregate_type', 100);
            $table->string('aggregate_id');
            $table->string('event_type', 100);
            $table->char('idempotency_key', 64)->unique();
            $table->string('method', 10);
            $table->string('path');
            $table->json('payload');
            $table->enum('status', ['pending', 'processing', 'unknown', 'sent', 'failed'])->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: metadata rekonsiliasi dan histori outbound tidak boleh dihapus otomatis.');
    }
};