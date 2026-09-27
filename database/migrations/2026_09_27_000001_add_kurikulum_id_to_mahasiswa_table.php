<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->foreignId('kurikulum_id')
                ->nullable()
                ->after('prodi_id')
                ->constrained('kurikulum', 'id_kurikulum')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kurikulum_id');
        });
    }
};
