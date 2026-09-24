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
            $table->boolean('boleh_perbaikan')->default(false)->after('lulus');
        });

        DB::table('skala_nilai_detail')
            ->whereIn('nilai_huruf', ['D', 'E'])
            ->update(['boleh_perbaikan' => true]);
    }

    public function down(): void
    {
        Schema::table('skala_nilai_detail', function (Blueprint $table) {
            $table->dropColumn('boleh_perbaikan');
        });
    }
};
