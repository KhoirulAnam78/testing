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
        if (Schema::hasTable('materi_blok')) {
            Schema::table('materi_blok', function (Blueprint $table) {
                if (
                    Schema::hasColumn('materi_blok', 'aturan_kegiatan_blok_id')
                    && ! $this->indexExists('materi_blok', 'materi_blok_aturan_kegiatan_blok_id_index')
                ) {
                    $table->index('aturan_kegiatan_blok_id', 'materi_blok_aturan_kegiatan_blok_id_index');
                }
            });

            $this->dropIndexIfExists('materi_blok', 'materi_blok_aturan_kegiatan_blok_id_kode_unique');

            Schema::table('materi_blok', function (Blueprint $table) {
                if (Schema::hasColumn('materi_blok', 'kode')) {
                    $table->dropColumn('kode');
                }
            });
        }

        if (Schema::hasTable('materi_rinci_blok')) {
            Schema::table('materi_rinci_blok', function (Blueprint $table) {
                if (
                    Schema::hasColumn('materi_rinci_blok', 'materi_blok_id')
                    && ! $this->indexExists('materi_rinci_blok', 'materi_rinci_blok_materi_blok_id_index')
                ) {
                    $table->index('materi_blok_id', 'materi_rinci_blok_materi_blok_id_index');
                }
            });

            $this->dropIndexIfExists('materi_rinci_blok', 'materi_rinci_blok_materi_blok_id_kode_unique');

            Schema::table('materi_rinci_blok', function (Blueprint $table) {
                if (Schema::hasColumn('materi_rinci_blok', 'kode')) {
                    $table->dropColumn('kode');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('materi_blok')) {
            Schema::table('materi_blok', function (Blueprint $table) {
                if (! Schema::hasColumn('materi_blok', 'kode')) {
                    $table->string('kode')->nullable()->after('aturan_kegiatan_blok_id');
                }
            });
        }

        if (Schema::hasTable('materi_rinci_blok')) {
            Schema::table('materi_rinci_blok', function (Blueprint $table) {
                if (! Schema::hasColumn('materi_rinci_blok', 'kode')) {
                    $table->string('kode')->nullable()->after('materi_blok_id');
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('$table')"))
                ->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        return ! empty(DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index],
        ));
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP INDEX `$index`");

            return;
        }

        DB::statement("ALTER TABLE `$table` DROP INDEX `$index`");
    }
};
