<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $kelas = DB::table('kelas_kuliah')
            ->join('blok', 'blok.id', '=', 'kelas_kuliah.blok_id')
            ->select(
                'kelas_kuliah.id_kelas_kuliah',
                'blok.semester_id',
                'blok.mata_kuliah_id',
            )
            ->get();

        if ($kelas->count() !== DB::table('kelas_kuliah')->count()) {
            throw new RuntimeException('Backfill kelas kuliah gagal: relasi blok lama tidak valid.');
        }

        if ($kelas->contains(fn ($item) => $item->semester_id === null || $item->mata_kuliah_id === null)) {
            throw new RuntimeException('Backfill kelas kuliah gagal: semester atau mata kuliah blok belum lengkap.');
        }

        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('blok_id')
                ->constrained('semester', 'id_semester')->restrictOnDelete();
            $table->foreignId('mata_kuliah_id')->nullable()->after('semester_id')
                ->constrained('mata_kuliah')->restrictOnDelete();
            $table->string('id_sumber')->nullable()->unique()->after('mata_kuliah_id');
            $table->timestamp('last_synced_at')->nullable()->after('status');
        });

        DB::transaction(function () use ($kelas): void {
            foreach ($kelas as $item) {
                DB::table('kelas_kuliah')
                    ->where('id_kelas_kuliah', $item->id_kelas_kuliah)
                    ->update([
                        'semester_id' => $item->semester_id,
                        'mata_kuliah_id' => $item->mata_kuliah_id,
                    ]);
            }
        });

        Schema::table('kelas_kuliah', function (Blueprint $table) {
            $table->foreignId('blok_id')->nullable()->change();
            $table->foreignId('semester_id')->nullable(false)->change();
            $table->foreignId('mata_kuliah_id')->nullable(false)->change();
            $table->index(['semester_id', 'mata_kuliah_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: metadata sinkronisasi dan pemetaan kelas kuliah tidak boleh dihapus otomatis.');
    }
};
