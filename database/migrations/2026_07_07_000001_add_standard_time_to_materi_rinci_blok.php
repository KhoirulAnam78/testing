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
            if (! Schema::hasColumn('materi_rinci_blok', 'jumlah_sesi')) {
                $table->unsignedTinyInteger('jumlah_sesi')->default(1)->after('pertemuan_ke');
            }

            if (! Schema::hasColumn('materi_rinci_blok', 'durasi_menit_per_sesi')) {
                $table->unsignedSmallInteger('durasi_menit_per_sesi')->nullable()->after('jumlah_sesi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materi_rinci_blok', function (Blueprint $table) {
            if (Schema::hasColumn('materi_rinci_blok', 'durasi_menit_per_sesi')) {
                $table->dropColumn('durasi_menit_per_sesi');
            }

            if (Schema::hasColumn('materi_rinci_blok', 'jumlah_sesi')) {
                $table->dropColumn('jumlah_sesi');
            }
        });
    }
};
