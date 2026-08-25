<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->foreignId('jenis_kegiatan_id')
                ->nullable()
                ->after('id')
                ->constrained('jenis_kegiatan')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan')->default(1)->after('nilai_maks_default')->index();
        });

        $relations = DB::table('komponen_penilaian_kegiatan')
            ->orderBy('komponen_penilaian_id')
            ->orderBy('id')
            ->get();

        foreach ($relations->groupBy('komponen_penilaian_id') as $komponenId => $rows) {
            $source = DB::table('komponen_penilaian')->where('id', $komponenId)->first();

            if (! $source) {
                continue;
            }

            foreach ($rows->values() as $index => $relation) {
                $targetId = (int) $komponenId;

                if ($index > 0) {
                    $targetId = DB::table('komponen_penilaian')->insertGetId([
                        'jenis_kegiatan_id' => $relation->jenis_kegiatan_id,
                        'kode' => $this->uniqueCode($source->kode, $relation->jenis_kegiatan_id),
                        'nama' => $source->nama,
                        'deskripsi' => $source->deskripsi,
                        'nilai_min_default' => $relation->nilai_min,
                        'nilai_maks_default' => $relation->nilai_maks,
                        'urutan' => $relation->urutan,
                        'status' => $source->status,
                        'created_at' => $source->created_at,
                        'updated_at' => $source->updated_at,
                        'deleted_at' => $source->deleted_at,
                    ]);

                    DB::table('komponen_penilaian_blok')
                        ->where('komponen_penilaian_id', $komponenId)
                        ->whereIn('aturan_kegiatan_blok_id', function ($query) use ($relation) {
                            $query->select('id')
                                ->from('aturan_kegiatan_blok')
                                ->where('jenis_kegiatan_id', $relation->jenis_kegiatan_id);
                        })
                        ->update(['komponen_penilaian_id' => $targetId]);
                } else {
                    DB::table('komponen_penilaian')->where('id', $targetId)->update([
                        'jenis_kegiatan_id' => $relation->jenis_kegiatan_id,
                        'nilai_min_default' => $relation->nilai_min,
                        'nilai_maks_default' => $relation->nilai_maks,
                        'urutan' => $relation->urutan,
                    ]);
                }
            }
        }

        Schema::drop('komponen_penilaian_kegiatan');
    }

    public function down(): void
    {
        Schema::create('komponen_penilaian_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_kegiatan_id')->constrained('jenis_kegiatan')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->decimal('nilai_min', 6, 2)->default(0);
            $table->decimal('nilai_maks', 6, 2);
            $table->unsignedSmallInteger('urutan')->default(1)->index();
            $table->timestamps();
            $table->unique(['jenis_kegiatan_id', 'komponen_penilaian_id'], 'komponen_penilaian_kegiatan_unique');
        });

        DB::table('komponen_penilaian')
            ->whereNotNull('jenis_kegiatan_id')
            ->orderBy('id')
            ->eachById(function ($komponen) {
                DB::table('komponen_penilaian_kegiatan')->insert([
                    'jenis_kegiatan_id' => $komponen->jenis_kegiatan_id,
                    'komponen_penilaian_id' => $komponen->id,
                    'nilai_min' => $komponen->nilai_min_default,
                    'nilai_maks' => $komponen->nilai_maks_default,
                    'urutan' => $komponen->urutan,
                    'created_at' => $komponen->created_at,
                    'updated_at' => $komponen->updated_at,
                ]);
            });

        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropForeign(['jenis_kegiatan_id']);
            $table->dropColumn(['jenis_kegiatan_id', 'urutan']);
        });
    }

    private function uniqueCode(string $base, int $jenisId): string
    {
        $candidate = $base.'_'.$jenisId;
        $number = 2;

        while (DB::table('komponen_penilaian')->where('kode', $candidate)->exists()) {
            $candidate = $base.'_'.$jenisId.'_'.$number++;
        }

        return $candidate;
    }
};
