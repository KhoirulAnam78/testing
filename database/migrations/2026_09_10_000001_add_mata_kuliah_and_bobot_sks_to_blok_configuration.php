<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $blokDenganBanyakMataKuliah = DB::table('mata_kuliah')
            ->whereNotNull('blok_id')
            ->groupBy('blok_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('blok_id');

        if ($blokDenganBanyakMataKuliah->isNotEmpty()) {
            throw new RuntimeException(
                'Migrasi dibatalkan: blok berikut memiliki lebih dari satu mata kuliah: '.$blokDenganBanyakMataKuliah->implode(', ')
            );
        }

        Schema::table('blok', function (Blueprint $table) {
            $table->foreignId('mata_kuliah_id')
                ->nullable()
                ->after('semester_id')
                ->constrained('mata_kuliah')
                ->nullOnDelete();
        });

        Schema::table('aturan_kegiatan_blok', function (Blueprint $table) {
            $table->decimal('bobot_sks', 8, 4)
                ->unsigned()
                ->default(0)
                ->after('durasi_menit');
        });

        DB::table('mata_kuliah')
            ->whereNotNull('blok_id')
            ->orderBy('id')
            ->each(function (object $mataKuliah): void {
                DB::table('blok')
                    ->where('id', $mataKuliah->blok_id)
                    ->update([
                        'mata_kuliah_id' => $mataKuliah->id,
                        'sks' => $mataKuliah->sks,
                    ]);
            });

        DB::table('aturan_kegiatan_blok')
            ->orderBy('id')
            ->each(function (object $aturan): void {
                $jumlahPertemuan = DB::table('materi_rinci_blok')
                    ->join('materi_blok', 'materi_blok.id_materi_blok', '=', 'materi_rinci_blok.materi_blok_id')
                    ->where('materi_blok.aturan_kegiatan_blok_id', $aturan->id)
                    ->whereNull('materi_blok.deleted_at')
                    ->whereNull('materi_rinci_blok.deleted_at')
                    ->where('materi_rinci_blok.status', 'aktif')
                    ->count();
                $bobotDefault = (string) DB::table('jenis_kegiatan')
                    ->where('id', $aturan->jenis_kegiatan_id)
                    ->value('bobot_sks_per_pertemuan');
                [$bulat, $desimal] = array_pad(explode('.', $bobotDefault, 2), 2, '');
                $bobotSkala = (((int) $bulat * 10000) + (int) str_pad($desimal, 4, '0')) * $jumlahPertemuan;

                DB::table('aturan_kegiatan_blok')
                    ->where('id', $aturan->id)
                    ->update(['bobot_sks' => intdiv($bobotSkala, 10000).'.'.str_pad((string) ($bobotSkala % 10000), 4, '0', STR_PAD_LEFT)]);
            });
    }

    public function down(): void
    {
        Schema::table('aturan_kegiatan_blok', function (Blueprint $table) {
            $table->dropColumn('bobot_sks');
        });

        Schema::table('blok', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mata_kuliah_id');
        });
    }
};
