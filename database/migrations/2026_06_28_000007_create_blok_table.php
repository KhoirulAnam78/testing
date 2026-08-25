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
        Schema::create('blok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prodi_id')->constrained('prodi', 'id_prodi')->restrictOnDelete();
            $table->foreignId('semester_id')->constrained('semester', 'id_semester')->restrictOnDelete();
            $table->string('kode');
            $table->string('nama');
            $table->decimal('sks', 4, 1);
            $table->date('tanggal_mulai')->nullable()->index();
            $table->date('tanggal_selesai')->nullable()->index();
            $table->text('deskripsi')->nullable();
            $table->enum('status', ['draft', 'aktif', 'selesai', 'arsip'])->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['prodi_id', 'semester_id', 'kode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blok');
    }
};
