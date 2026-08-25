<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('materi_blok')) {
            return;
        }

        if (Schema::hasColumn('materi_blok', 'aturan_kegiatan_blok_id')) {
            $nullCount = DB::table('materi_blok')->whereNull('aturan_kegiatan_blok_id')->count();

            if ($nullCount > 0) {
                throw new RuntimeException("Tidak dapat menghapus materi_blok.blok_id: {$nullCount} materi belum memiliki aturan kegiatan.");
            }

            Schema::table('materi_blok', function (Blueprint $table) {
                $table->unsignedBigInteger('aturan_kegiatan_blok_id')->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('materi_blok', 'blok_id')) {
            Schema::table('materi_blok', function (Blueprint $table) {
                $table->dropConstrainedForeignId('blok_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('materi_blok') || Schema::hasColumn('materi_blok', 'blok_id')) {
            return;
        }

        Schema::table('materi_blok', function (Blueprint $table) {
            $table->foreignId('blok_id')->nullable()->after('aturan_kegiatan_blok_id')->constrained('blok')->cascadeOnDelete();
        });

        DB::table('materi_blok')
            ->join('aturan_kegiatan_blok', 'aturan_kegiatan_blok.id', '=', 'materi_blok.aturan_kegiatan_blok_id')
            ->update(['materi_blok.blok_id' => DB::raw('aturan_kegiatan_blok.blok_id')]);

        Schema::table('materi_blok', function (Blueprint $table) {
            $table->unsignedBigInteger('blok_id')->nullable(false)->change();
        });
    }
};
