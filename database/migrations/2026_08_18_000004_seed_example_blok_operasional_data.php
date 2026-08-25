<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Task 4 - Data contoh operasional untuk blok contoh.
 *
 * Migration `2026_07_01_000003_seed_example_academic_block_data` mengisi peserta dan
 * kelompok ke tabel lama yang sudah dihapus, sehingga setelah realign blok contoh
 * kehilangan data operasionalnya. Migration ini mengisinya kembali ke tabel baru
 * supaya `migrate:fresh` langsung menghasilkan blok yang bisa diuji end-to-end.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $blok = DB::table('blok')->where('kode', 'BMD-2026')->first(['id', 'tanggal_mulai']);

            if (! $blok) {
                return;
            }

            $now = now();

            $aturanIds = DB::table('aturan_kegiatan_blok')
                ->join('jenis_kegiatan', 'jenis_kegiatan.id', '=', 'aturan_kegiatan_blok.jenis_kegiatan_id')
                ->where('aturan_kegiatan_blok.blok_id', $blok->id)
                ->pluck('aturan_kegiatan_blok.id', 'jenis_kegiatan.kode');

            $this->seedRencanaWaktu($blok, $aturanIds->values()->all());

            $mahasiswaIds = DB::table('mahasiswa')
                ->whereBetween('nim', ['260101001', '260101010'])
                ->orderBy('nim')
                ->pluck('id_mahasiswa')
                ->all();

            if ($mahasiswaIds === []) {
                return;
            }

            $pesertaIds = [];
            foreach ($mahasiswaIds as $mahasiswaId) {
                DB::table('peserta_blok')->updateOrInsert(
                    [
                        'blok_id' => $blok->id,
                        'mahasiswa_id' => $mahasiswaId,
                    ],
                    [
                        'kelas_id' => null,
                        'status' => 'aktif',
                        'tanggal_masuk' => $blok->tanggal_mulai,
                        'catatan' => 'Peserta contoh blok sistem blok.',
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                $pesertaIds[] = DB::table('peserta_blok')
                    ->where('blok_id', $blok->id)
                    ->where('mahasiswa_id', $mahasiswaId)
                    ->value('id_peserta_blok');
            }

            // Sesuai contoh alur: Kuliah Pakar 2 kelompok, Praktikum 4 kelompok.
            // Skills Lab sengaja dibiarkan kosong agar keadaan "belum ada kelompok" bisa diuji.
            $rencanaKelompok = [
                'KP' => [['kode' => 'KP1', 'nama' => 'Kuliah Pakar 1'], ['kode' => 'KP2', 'nama' => 'Kuliah Pakar 2']],
                'TUT' => [['kode' => 'T1', 'nama' => 'Tutorial 1'], ['kode' => 'T2', 'nama' => 'Tutorial 2']],
                'PRK' => [
                    ['kode' => 'P1', 'nama' => 'Praktikum 1'],
                    ['kode' => 'P2', 'nama' => 'Praktikum 2'],
                    ['kode' => 'P3', 'nama' => 'Praktikum 3'],
                    ['kode' => 'P4', 'nama' => 'Praktikum 4'],
                ],
            ];

            foreach ($rencanaKelompok as $kodeJenis => $kelompokList) {
                $aturanId = $aturanIds[$kodeJenis] ?? null;

                if (! $aturanId) {
                    continue;
                }

                foreach ($this->splitEvenly($pesertaIds, count($kelompokList)) as $index => $anggotaIds) {
                    $kelompok = $kelompokList[$index];

                    DB::table('kelompok_blok')->updateOrInsert(
                        [
                            'blok_id' => $blok->id,
                            'aturan_kegiatan_blok_id' => $aturanId,
                            'kode' => $kelompok['kode'],
                        ],
                        [
                            'kelas_id' => null,
                            'nama' => $kelompok['nama'],
                            'kapasitas' => null,
                            'status' => 'aktif',
                            'deleted_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );

                    $kelompokId = DB::table('kelompok_blok')
                        ->where('blok_id', $blok->id)
                        ->where('aturan_kegiatan_blok_id', $aturanId)
                        ->where('kode', $kelompok['kode'])
                        ->value('id_kelompok_blok');

                    foreach ($anggotaIds as $anggotaIndex => $pesertaId) {
                        DB::table('anggota_kelompok_blok')->updateOrInsert(
                            [
                                'kelompok_blok_id' => $kelompokId,
                                'peserta_blok_id' => $pesertaId,
                            ],
                            [
                                'peran' => $anggotaIndex === 0 ? 'ketua' : 'anggota',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ],
                        );
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            $blokId = DB::table('blok')->where('kode', 'BMD-2026')->value('id');

            if (! $blokId) {
                return;
            }

            $kelompokIds = DB::table('kelompok_blok')->where('blok_id', $blokId)->pluck('id_kelompok_blok');

            DB::table('anggota_kelompok_blok')->whereIn('kelompok_blok_id', $kelompokIds)->delete();
            DB::table('pertemuan_blok')->where('blok_id', $blokId)->delete();
            DB::table('kelompok_blok')->where('blok_id', $blokId)->delete();
            DB::table('peserta_blok')->where('blok_id', $blokId)->delete();
        });
    }

    /**
     * Isi tanggal rencana template mingguan berdasarkan `pertemuan_ke`.
     *
     * @param  array<int, int>  $aturanIds
     */
    private function seedRencanaWaktu(object $blok, array $aturanIds): void
    {
        if ($aturanIds === [] || ! $blok->tanggal_mulai) {
            return;
        }

        $mulai = Carbon::parse($blok->tanggal_mulai);

        $rincian = DB::table('materi_rinci_blok')
            ->join('materi_blok', 'materi_blok.id_materi_blok', '=', 'materi_rinci_blok.materi_blok_id')
            ->join('aturan_kegiatan_blok', 'aturan_kegiatan_blok.id', '=', 'materi_blok.aturan_kegiatan_blok_id')
            ->whereIn('aturan_kegiatan_blok.id', $aturanIds)
            ->whereNull('materi_rinci_blok.tanggal_rencana')
            ->select([
                'materi_rinci_blok.id_materi_rinci_blok',
                'materi_rinci_blok.pertemuan_ke',
                'materi_rinci_blok.jumlah_sesi',
                'aturan_kegiatan_blok.durasi_menit',
            ])
            ->get();

        foreach ($rincian as $rinci) {
            $pertemuanKe = max((int) ($rinci->pertemuan_ke ?: 1), 1);
            $durasiTotal = max((int) ($rinci->jumlah_sesi ?: 1), 1) * max((int) $rinci->durasi_menit, 1);
            $jamMulai = $mulai->copy()->setTime(8, 0);

            DB::table('materi_rinci_blok')
                ->where('id_materi_rinci_blok', $rinci->id_materi_rinci_blok)
                ->update([
                    'tanggal_rencana' => $mulai->copy()->addWeeks($pertemuanKe - 1)->toDateString(),
                    'jam_mulai_rencana' => $jamMulai->format('H:i:s'),
                    'jam_selesai_rencana' => $jamMulai->copy()->addMinutes($durasiTotal)->format('H:i:s'),
                ]);
        }
    }

    /**
     * Bagi peserta rata ke sejumlah kelompok.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, int>>
     */
    private function splitEvenly(array $ids, int $groups): array
    {
        if ($groups < 1 || $ids === []) {
            return [];
        }

        $chunks = array_fill(0, $groups, []);

        foreach ($ids as $index => $id) {
            $chunks[$index % $groups][] = $id;
        }

        return $chunks;
    }
};
