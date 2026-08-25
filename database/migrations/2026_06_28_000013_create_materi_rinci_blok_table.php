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
        Schema::create('materi_rinci_blok', function (Blueprint $table) {
            $table->id('id_materi_rinci_blok');
            $table->foreignId('materi_blok_id')->constrained('materi_blok', 'id_materi_blok')->cascadeOnDelete();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->text('capaian_pembelajaran')->nullable();
            $table->text('referensi')->nullable();
            $table->unsignedSmallInteger('pertemuan_ke')->nullable()->index();
            $table->unsignedSmallInteger('urutan')->default(1)->index();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materi_rinci_blok');
    }
};
