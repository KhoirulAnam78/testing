<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blok', function (Blueprint $table) {
            $table->dropUnique(['prodi_id', 'semester_id', 'kode']);
            $table->dropColumn('kode');
        });
    }

    public function down(): void
    {
        Schema::table('blok', function (Blueprint $table) {
            $table->string('kode')->nullable()->after('semester_id');
            $table->unique(['prodi_id', 'semester_id', 'kode']);
        });
    }
};
