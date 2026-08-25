<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blok', function (Blueprint $table) {
            $table->unsignedBigInteger('koordinator_id')->nullable()->after('semester_id');
            $table->unsignedBigInteger('asisten_koordinator_id')->nullable()->after('koordinator_id');

            $table->foreign('koordinator_id')
                ->references('id_dosen')
                ->on('dosen')
                ->nullOnDelete();
            $table->foreign('asisten_koordinator_id')
                ->references('id_dosen')
                ->on('dosen')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blok', function (Blueprint $table) {
            $table->dropForeign(['koordinator_id']);
            $table->dropForeign(['asisten_koordinator_id']);
            $table->dropColumn(['koordinator_id', 'asisten_koordinator_id']);
        });
    }
};
