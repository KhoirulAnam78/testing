<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_nilai_pertemuan_blok', function (Blueprint $table) {
            $table->id('id_rekap_nilai_pertemuan_blok');
            $table->foreignId('pertemuan_blok_id')
                ->constrained('pertemuan_blok', 'id_pertemuan_blok')
                ->cascadeOnDelete();
            $table->foreignId('peserta_blok_id')
                ->constrained('peserta_blok', 'id_peserta_blok')
                ->cascadeOnDelete();
            $table->decimal('total', 8, 2)->default(0);
            $table->decimal('nilai_akhir', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['pertemuan_blok_id', 'peserta_blok_id'],
                'rekap_nilai_pertemuan_blok_unique'
            );
            $table->index('peserta_blok_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_nilai_pertemuan_blok');
    }
};
