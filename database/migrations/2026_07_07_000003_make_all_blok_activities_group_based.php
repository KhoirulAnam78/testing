<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aturan_kegiatan_blok') || ! Schema::hasColumn('aturan_kegiatan_blok', 'perlu_kelompok')) {
            return;
        }

        DB::table('aturan_kegiatan_blok')->update(['perlu_kelompok' => true]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE aturan_kegiatan_blok MODIFY perlu_kelompok TINYINT(1) NOT NULL DEFAULT 1');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('aturan_kegiatan_blok') || ! Schema::hasColumn('aturan_kegiatan_blok', 'perlu_kelompok')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE aturan_kegiatan_blok MODIFY perlu_kelompok TINYINT(1) NOT NULL DEFAULT 0');
        }
    }
};
