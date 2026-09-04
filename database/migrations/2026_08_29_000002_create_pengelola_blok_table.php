<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('blok')
            ->whereNotNull('koordinator_id')
            ->whereColumn('koordinator_id', 'asisten_koordinator_id')
            ->exists()) {
            throw new RuntimeException('Migration pengelola_blok dibatalkan: terdapat blok dengan dosen yang sama sebagai koordinator dan asisten koordinator.');
        }

        Schema::create('pengelola_blok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosen', 'id_dosen')->restrictOnDelete();
            $table->enum('jabatan', ['koordinator', 'asisten_koordinator', 'kontributor']);
            $table->timestamps();

            $table->unique(['blok_id', 'dosen_id']);
            $table->index(['blok_id', 'jabatan']);
        });

        DB::table('blok')
            ->select(['id', 'koordinator_id', 'asisten_koordinator_id', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($bloks): void {
                $rows = [];

                foreach ($bloks as $blok) {
                    foreach ([
                        'koordinator' => $blok->koordinator_id,
                        'asisten_koordinator' => $blok->asisten_koordinator_id,
                    ] as $jabatan => $dosenId) {
                        if ($dosenId !== null) {
                            $rows[] = [
                                'blok_id' => $blok->id,
                                'dosen_id' => $dosenId,
                                'jabatan' => $jabatan,
                                'created_at' => $blok->created_at,
                                'updated_at' => $blok->updated_at,
                            ];
                        }
                    }
                }

                if ($rows !== []) {
                    DB::table('pengelola_blok')->insert($rows);
                }
            });

        Schema::table('blok', function (Blueprint $table) {
            $table->dropForeign(['koordinator_id']);
            $table->dropForeign(['asisten_koordinator_id']);
            $table->dropColumn(['koordinator_id', 'asisten_koordinator_id']);
        });
    }

    public function down(): void
    {
        Schema::table('blok', function (Blueprint $table) {
            $table->unsignedBigInteger('koordinator_id')->nullable()->after('semester_id');
            $table->unsignedBigInteger('asisten_koordinator_id')->nullable()->after('koordinator_id');
            $table->foreign('koordinator_id')->references('id_dosen')->on('dosen')->nullOnDelete();
            $table->foreign('asisten_koordinator_id')->references('id_dosen')->on('dosen')->nullOnDelete();
        });

        DB::table('pengelola_blok')
            ->whereIn('jabatan', ['koordinator', 'asisten_koordinator'])
            ->orderBy('id')
            ->get(['blok_id', 'dosen_id', 'jabatan'])
            ->each(function ($pengelola): void {
                DB::table('blok')->where('id', $pengelola->blok_id)->update([
                    $pengelola->jabatan.'_id' => $pengelola->dosen_id,
                ]);
            });

        Schema::dropIfExists('pengelola_blok');
    }
};
