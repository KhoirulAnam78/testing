<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('materi_rinci_blok', function (Blueprint $table) {
            if (! Schema::hasColumn('materi_rinci_blok', 'tanggal_rencana')) {
                $table->date('tanggal_rencana')->nullable()->index()->after('pertemuan_ke');
            }

            if (! Schema::hasColumn('materi_rinci_blok', 'jam_mulai_rencana')) {
                $table->time('jam_mulai_rencana')->nullable()->after('tanggal_rencana');
            }

            if (! Schema::hasColumn('materi_rinci_blok', 'jam_selesai_rencana')) {
                $table->time('jam_selesai_rencana')->nullable()->after('jam_mulai_rencana');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materi_rinci_blok', function (Blueprint $table) {
            foreach (['jam_selesai_rencana', 'jam_mulai_rencana', 'tanggal_rencana'] as $column) {
                if (Schema::hasColumn('materi_rinci_blok', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
