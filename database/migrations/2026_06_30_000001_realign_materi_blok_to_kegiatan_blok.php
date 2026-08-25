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
        if (! Schema::hasTable('materi_blok')) {
            return;
        }

        Schema::table('materi_blok', function (Blueprint $table) {
            if (! Schema::hasColumn('materi_blok', 'aturan_kegiatan_blok_id')) {
                $table->foreignId('aturan_kegiatan_blok_id')
                    ->nullable()
                    ->after('id_materi_blok')
                    ->constrained('aturan_kegiatan_blok')
                    ->cascadeOnDelete();
            }
        });

        if (Schema::hasColumn('materi_blok', 'blok_id')) {
            DB::statement(
                'UPDATE materi_blok mb
                INNER JOIN (
                    SELECT blok_id, MIN(id) AS aturan_id
                    FROM aturan_kegiatan_blok
                    WHERE deleted_at IS NULL
                    GROUP BY blok_id
                ) akb ON akb.blok_id = mb.blok_id
                SET mb.aturan_kegiatan_blok_id = akb.aturan_id
                WHERE mb.aturan_kegiatan_blok_id IS NULL'
            );

            Schema::table('materi_blok', function (Blueprint $table) {
                if (! $this->indexExists('materi_blok', 'materi_blok_blok_id_index')) {
                    $table->index('blok_id', 'materi_blok_blok_id_index');
                }
            });

            $this->dropIndexIfExists('materi_blok', 'materi_blok_blok_id_kode_unique');
        }

        Schema::table('materi_rinci_blok', function (Blueprint $table) {
            if (! Schema::hasColumn('materi_rinci_blok', 'pertemuan_ke')) {
                $table->unsignedSmallInteger('pertemuan_ke')->nullable()->after('referensi')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('materi_rinci_blok') && Schema::hasColumn('materi_rinci_blok', 'pertemuan_ke')) {
            Schema::table('materi_rinci_blok', function (Blueprint $table) {
                $table->dropColumn('pertemuan_ke');
            });
        }

        if (! Schema::hasTable('materi_blok')) {
            return;
        }

        Schema::table('materi_blok', function (Blueprint $table) {
            if (Schema::hasColumn('materi_blok', 'aturan_kegiatan_blok_id')) {
                $table->dropForeign(['aturan_kegiatan_blok_id']);
                $table->dropColumn('aturan_kegiatan_blok_id');
            }
        });
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
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `$table` DROP INDEX `$index`");
        }
    }
};
