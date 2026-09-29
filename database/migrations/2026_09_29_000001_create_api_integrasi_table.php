<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_integrasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('base_url');
            $table->text('login_url');
            $table->string('username');
            $table->text('password');
            $table->boolean('is_aktif')->default(false);
            $table->unsignedSmallInteger('timeout')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: konfigurasi dan kredensial API tidak boleh dihapus otomatis.');
    }
};
