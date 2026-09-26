<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skala_nilai_detail', function (Blueprint $table) {
            $table->dropUnique('skala_nilai_detail_skala_nilai_id_urutan_mutu_unique');
            $table->dropColumn('urutan_mutu');
        });
    }

    public function down(): void
    {
        Schema::table('skala_nilai_detail', function (Blueprint $table) {
            $table->unsignedSmallInteger('urutan_mutu')->nullable()->after('nilai_indeks');
        });

        DB::table('skala_nilai_detail')
            ->orderBy('skala_nilai_id')
            ->orderBy('nilai_indeks')
            ->orderBy('nilai_angka_min')
            ->get(['id_skala_nilai_detail', 'skala_nilai_id'])
            ->groupBy('skala_nilai_id')
            ->each(function ($detail) {
                foreach ($detail->values() as $index => $item) {
                    DB::table('skala_nilai_detail')
                        ->where('id_skala_nilai_detail', $item->id_skala_nilai_detail)
                        ->update(['urutan_mutu' => $index + 1]);
                }
            });

        Schema::table('skala_nilai_detail', function (Blueprint $table) {
            $table->unsignedSmallInteger('urutan_mutu')->nullable(false)->change();
            $table->unique(['skala_nilai_id', 'urutan_mutu']);
        });
    }
};
