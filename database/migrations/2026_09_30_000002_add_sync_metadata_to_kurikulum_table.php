<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kurikulum', function (Blueprint $table) {
            $table->enum('status_sync', ['pending', 'synced'])->default('pending')->index()->after('status');
            $table->timestamp('synced_at')->nullable()->after('status_sync');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: metadata sinkronisasi kurikulum tidak boleh dihapus otomatis.');
    }
};
