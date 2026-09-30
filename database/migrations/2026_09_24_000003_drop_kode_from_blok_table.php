<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom kode, unique index, dan seluruh data lama sengaja dipertahankan.
    }

    public function down(): void
    {
        // Tidak ada perubahan skema untuk dibatalkan.
    }
};
