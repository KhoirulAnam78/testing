<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $now = now();

            DB::table('prodi')->updateOrInsert(
                ['kode' => 'PSPD'],
                [
                    'nama' => 'Pendidikan Dokter',
                    'jenjang' => 'S1',
                    'status' => 'aktif',
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $prodiId = DB::table('prodi')->where('kode', 'PSPD')->value('id_prodi');

            DB::table('semester')->updateOrInsert(
                [
                    'nama' => 'ganjil',
                    'tahun' => 2026,
                ],
                [
                    'kode' => 'GJ-2026',
                    'tanggal_mulai' => '2026-08-01',
                    'tanggal_selesai' => '2026-12-20',
                    'is_aktif' => true,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $semesterId = DB::table('semester')
                ->where('nama', 'ganjil')
                ->where('tahun', 2026)
                ->value('id_semester');

            $jenisKegiatan = [
                [
                    'kode' => 'KP',
                    'nama' => 'Kuliah Pakar',
                    'jumlah_pertemuan_default' => 6,
                    'durasi_menit_default' => 100,
                    'deskripsi' => 'Kegiatan kuliah pakar untuk penguatan konsep blok.',
                ],
                [
                    'kode' => 'TUT',
                    'nama' => 'Tutorial PBL',
                    'jumlah_pertemuan_default' => 8,
                    'durasi_menit_default' => 120,
                    'deskripsi' => 'Diskusi tutorial berbasis skenario klinis.',
                ],
                [
                    'kode' => 'PRK',
                    'nama' => 'Praktikum',
                    'jumlah_pertemuan_default' => 4,
                    'durasi_menit_default' => 150,
                    'deskripsi' => 'Praktikum laboratorium pendukung materi blok.',
                ],
                [
                    'kode' => 'SKL',
                    'nama' => 'Skills Lab',
                    'jumlah_pertemuan_default' => 4,
                    'durasi_menit_default' => 120,
                    'deskripsi' => 'Latihan keterampilan klinis dasar.',
                ],
            ];

            foreach ($jenisKegiatan as $item) {
                DB::table('jenis_kegiatan')->updateOrInsert(
                    ['kode' => $item['kode']],
                    array_merge($item, [
                        'status' => 'aktif',
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]),
                );
            }

            DB::table('blok')->updateOrInsert(
                [
                    'prodi_id' => $prodiId,
                    'semester_id' => $semesterId,
                    'kode' => 'BMD-2026',
                ],
                [
                    'nama' => 'Blok Biomedik Dasar',
                    'sks' => 6,
                    'tanggal_mulai' => '2026-08-10',
                    'tanggal_selesai' => '2026-09-18',
                    'deskripsi' => 'Contoh blok akademik untuk pengujian alur sistem blok.',
                    'status' => 'aktif',
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $blokId = DB::table('blok')
                ->where('prodi_id', $prodiId)
                ->where('semester_id', $semesterId)
                ->where('kode', 'BMD-2026')
                ->value('id');

            DB::table('mata_kuliah')->updateOrInsert(
                [
                    'prodi_id' => $prodiId,
                    'kode' => 'KDK101',
                ],
                [
                    'blok_id' => $blokId,
                    'nama' => 'Biomedik Dasar',
                    'sks' => 6,
                    'deskripsi' => 'Mata kuliah contoh yang memakai Blok Biomedik Dasar.',
                    'status' => 'aktif',
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $mataKuliahId = DB::table('mata_kuliah')
                ->where('prodi_id', $prodiId)
                ->where('kode', 'KDK101')
                ->value('id');

            $aturanIds = [];
            foreach ([
                'KP' => ['jumlah_pertemuan' => 6, 'durasi_menit' => 100, 'jumlah_mahasiswa_per_kelompok' => null, 'perlu_kelompok' => false, 'urutan' => 1],
                'TUT' => ['jumlah_pertemuan' => 8, 'durasi_menit' => 120, 'jumlah_mahasiswa_per_kelompok' => 10, 'perlu_kelompok' => true, 'urutan' => 2],
                'PRK' => ['jumlah_pertemuan' => 4, 'durasi_menit' => 150, 'jumlah_mahasiswa_per_kelompok' => 15, 'perlu_kelompok' => true, 'urutan' => 3],
                'SKL' => ['jumlah_pertemuan' => 4, 'durasi_menit' => 120, 'jumlah_mahasiswa_per_kelompok' => 8, 'perlu_kelompok' => true, 'urutan' => 4],
            ] as $kodeJenis => $aturan) {
                $jenisId = DB::table('jenis_kegiatan')->where('kode', $kodeJenis)->value('id');

                DB::table('aturan_kegiatan_blok')->updateOrInsert(
                    [
                        'blok_id' => $blokId,
                        'jenis_kegiatan_id' => $jenisId,
                    ],
                    [
                        'jumlah_pertemuan' => $aturan['jumlah_pertemuan'],
                        'durasi_menit' => $aturan['durasi_menit'],
                        'jumlah_mahasiswa_per_kelompok' => $aturan['jumlah_mahasiswa_per_kelompok'],
                        'perlu_kelompok' => $aturan['perlu_kelompok'],
                        'perlu_presensi' => true,
                        'perlu_logbook' => false,
                        'urutan' => $aturan['urutan'],
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                $aturanIds[$kodeJenis] = DB::table('aturan_kegiatan_blok')
                    ->where('blok_id', $blokId)
                    ->where('jenis_kegiatan_id', $jenisId)
                    ->value('id');
            }

            $materi = [
                'KP' => [
                    [
                        'judul' => 'Pengantar Struktur dan Fungsi Sel',
                        'deskripsi' => 'Konsep dasar sel sebagai unit kehidupan.',
                        'rincian' => [
                            ['judul' => 'Membran sel dan organel', 'pertemuan_ke' => 1],
                            ['judul' => 'Komunikasi antar sel', 'pertemuan_ke' => 2],
                        ],
                    ],
                    [
                        'judul' => 'Homeostasis dan Adaptasi',
                        'deskripsi' => 'Prinsip regulasi tubuh dan adaptasi jaringan.',
                        'rincian' => [
                            ['judul' => 'Mekanisme homeostasis', 'pertemuan_ke' => 3],
                            ['judul' => 'Respons adaptasi seluler', 'pertemuan_ke' => 4],
                        ],
                    ],
                ],
                'TUT' => [
                    [
                        'judul' => 'Skenario Demam dan Inflamasi',
                        'deskripsi' => 'Analisis kasus demam akut berbasis PBL.',
                        'rincian' => [
                            ['judul' => 'Identifikasi masalah dan hipotesis', 'pertemuan_ke' => 1],
                            ['judul' => 'Sintesis hasil belajar mandiri', 'pertemuan_ke' => 2],
                        ],
                    ],
                    [
                        'judul' => 'Skenario Gangguan Metabolik',
                        'deskripsi' => 'Diskusi mekanisme gangguan metabolik dasar.',
                        'rincian' => [
                            ['judul' => 'Klarifikasi istilah dan konsep metabolisme', 'pertemuan_ke' => 3],
                            ['judul' => 'Pembahasan learning issue', 'pertemuan_ke' => 4],
                        ],
                    ],
                ],
                'PRK' => [
                    [
                        'judul' => 'Pengenalan Mikroskop dan Preparat',
                        'deskripsi' => 'Praktik penggunaan mikroskop dasar.',
                        'rincian' => [
                            ['judul' => 'Kalibrasi dan fokus mikroskop', 'pertemuan_ke' => 1],
                            ['judul' => 'Identifikasi preparat jaringan dasar', 'pertemuan_ke' => 2],
                        ],
                    ],
                ],
                'SKL' => [
                    [
                        'judul' => 'Komunikasi Dokter Pasien Dasar',
                        'deskripsi' => 'Latihan komunikasi awal dan anamnesis singkat.',
                        'rincian' => [
                            ['judul' => 'Membuka percakapan klinis', 'pertemuan_ke' => 1],
                            ['judul' => 'Menggali keluhan utama', 'pertemuan_ke' => 2],
                        ],
                    ],
                ],
            ];

            foreach ($materi as $kodeJenis => $materiItems) {
                foreach ($materiItems as $materiIndex => $materiItem) {
                    $materiPayload = [
                        'deskripsi' => $materiItem['deskripsi'],
                        'capaian_pembelajaran' => 'Mahasiswa mampu menjelaskan '.$materiItem['judul'].'.',
                        'urutan' => $materiIndex + 1,
                        'status' => 'aktif',
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (Schema::hasColumn('materi_blok', 'blok_id')) {
                        $materiPayload['blok_id'] = $blokId;
                    }

                    DB::table('materi_blok')->updateOrInsert(
                        [
                            'aturan_kegiatan_blok_id' => $aturanIds[$kodeJenis],
                            'judul' => $materiItem['judul'],
                        ],
                        $materiPayload,
                    );

                    $materiId = DB::table('materi_blok')
                        ->where('aturan_kegiatan_blok_id', $aturanIds[$kodeJenis])
                        ->where('judul', $materiItem['judul'])
                        ->value('id_materi_blok');

                    foreach ($materiItem['rincian'] as $rinciIndex => $rinci) {
                        DB::table('materi_rinci_blok')->updateOrInsert(
                            [
                                'materi_blok_id' => $materiId,
                                'judul' => $rinci['judul'],
                            ],
                            [
                                'deskripsi' => 'Rincian contoh untuk '.$rinci['judul'].'.',
                                'capaian_pembelajaran' => 'Mahasiswa memahami '.$rinci['judul'].'.',
                                'referensi' => 'Referensi bahan ajar blok.',
                                'pertemuan_ke' => $rinci['pertemuan_ke'],
                                'urutan' => $rinciIndex + 1,
                                'status' => 'aktif',
                                'deleted_at' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ],
                        );
                    }
                }
            }

            foreach ([
                ['nidn' => '1001017601', 'nip' => '197601012006041001', 'nama' => 'dr. Andi Pratama, M.Biomed', 'email' => 'andi.pratama@example.test', 'bidang_keahlian' => 'Biomedik'],
                ['nidn' => '1002027802', 'nip' => '197802022008012002', 'nama' => 'dr. Siti Rahma, Sp.PD', 'email' => 'siti.rahma@example.test', 'bidang_keahlian' => 'Ilmu Penyakit Dalam'],
                ['nidn' => '1003038003', 'nip' => '198003032009012003', 'nama' => 'dr. Budi Santoso, M.Kes', 'email' => 'budi.santoso@example.test', 'bidang_keahlian' => 'Pendidikan Kedokteran'],
            ] as $dosen) {
                DB::table('dosen')->updateOrInsert(
                    ['nidn' => $dosen['nidn']],
                    array_merge($dosen, [
                        'user_id' => null,
                        'prodi_id' => $prodiId,
                        'no_hp' => null,
                        'gelar_depan' => null,
                        'gelar_belakang' => null,
                        'status' => 'aktif',
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]),
                );
            }

            $mahasiswaIds = [];
            foreach ([
                ['nim' => '260101001', 'nama' => 'Aulia Fitriani'],
                ['nim' => '260101002', 'nama' => 'Rafi Maulana'],
                ['nim' => '260101003', 'nama' => 'Nadia Zahra'],
                ['nim' => '260101004', 'nama' => 'Fajar Hidayat'],
                ['nim' => '260101005', 'nama' => 'Maya Lestari'],
                ['nim' => '260101006', 'nama' => 'Ilham Saputra'],
                ['nim' => '260101007', 'nama' => 'Rania Putri'],
                ['nim' => '260101008', 'nama' => 'Dimas Aditya'],
                ['nim' => '260101009', 'nama' => 'Farah Nabila'],
                ['nim' => '260101010', 'nama' => 'Yoga Prakoso'],
            ] as $mahasiswa) {
                DB::table('mahasiswa')->updateOrInsert(
                    ['nim' => $mahasiswa['nim']],
                    [
                        'user_id' => null,
                        'prodi_id' => $prodiId,
                        'nama' => $mahasiswa['nama'],
                        'email' => strtolower(str_replace(' ', '.', $mahasiswa['nama'])).'@student.example.test',
                        'no_hp' => null,
                        'angkatan' => 2026,
                        'status' => 'aktif',
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                $mahasiswaIds[] = DB::table('mahasiswa')->where('nim', $mahasiswa['nim'])->value('id_mahasiswa');
            }

            DB::table('kelas')->updateOrInsert(
                [
                    'semester_id' => $semesterId,
                    'mata_kuliah_id' => $mataKuliahId,
                    'kode' => 'R001',
                ],
                [
                    'prodi_id' => $prodiId,
                    'blok_id' => $blokId,
                    'nama' => 'Reguler 001',
                    'kapasitas' => 60,
                    'status' => 'aktif',
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $kelasId = DB::table('kelas')
                ->where('semester_id', $semesterId)
                ->where('mata_kuliah_id', $mataKuliahId)
                ->where('kode', 'R001')
                ->value('id_kelas');

            $pesertaIds = [];
            foreach ($mahasiswaIds as $mahasiswaId) {
                DB::table('peserta_kelas')->updateOrInsert(
                    [
                        'kelas_id' => $kelasId,
                        'mahasiswa_id' => $mahasiswaId,
                    ],
                    [
                        'status' => 'aktif',
                        'tanggal_masuk' => '2026-08-10',
                        'catatan' => 'Peserta contoh kelas sistem blok.',
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                $pesertaIds[] = DB::table('peserta_kelas')
                    ->where('kelas_id', $kelasId)
                    ->where('mahasiswa_id', $mahasiswaId)
                    ->value('id_peserta_kelas');
            }

            foreach ([
                ['aturan_id' => $aturanIds['TUT'], 'kode' => 'T1', 'nama' => 'Tutorial 1', 'kapasitas' => 10, 'peserta' => array_slice($pesertaIds, 0, 5)],
                ['aturan_id' => $aturanIds['TUT'], 'kode' => 'T2', 'nama' => 'Tutorial 2', 'kapasitas' => 10, 'peserta' => array_slice($pesertaIds, 5, 5)],
                ['aturan_id' => $aturanIds['PRK'], 'kode' => 'P1', 'nama' => 'Praktikum 1', 'kapasitas' => 15, 'peserta' => $pesertaIds],
            ] as $kelompok) {
                DB::table('kelompok_kelas_blok')->updateOrInsert(
                    [
                        'kelas_id' => $kelasId,
                        'aturan_kegiatan_blok_id' => $kelompok['aturan_id'],
                        'kode' => $kelompok['kode'],
                    ],
                    [
                        'nama' => $kelompok['nama'],
                        'kapasitas' => $kelompok['kapasitas'],
                        'status' => 'aktif',
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                $kelompokId = DB::table('kelompok_kelas_blok')
                    ->where('kelas_id', $kelasId)
                    ->where('aturan_kegiatan_blok_id', $kelompok['aturan_id'])
                    ->where('kode', $kelompok['kode'])
                    ->value('id_kelompok_kelas_blok');

                foreach ($kelompok['peserta'] as $index => $pesertaId) {
                    DB::table('anggota_kelompok_kelas_blok')->updateOrInsert(
                        [
                            'kelompok_kelas_blok_id' => $kelompokId,
                            'peserta_kelas_id' => $pesertaId,
                        ],
                        [
                            'peran' => $index === 0 ? 'ketua' : 'anggota',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
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
            $prodiId = DB::table('prodi')->where('kode', 'PSPD')->value('id_prodi');
            $semesterId = DB::table('semester')
                ->where('nama', 'ganjil')
                ->where('tahun', 2026)
                ->value('id_semester');
            $blokId = DB::table('blok')->where('kode', 'BMD-2026')->value('id');
            $mataKuliahId = DB::table('mata_kuliah')->where('kode', 'KDK101')->value('id');
            $kelasIds = DB::table('kelas')
                ->where('semester_id', $semesterId)
                ->where('mata_kuliah_id', $mataKuliahId)
                ->where('kode', 'R001')
                ->pluck('id_kelas');

            $kelompokIds = DB::table('kelompok_kelas_blok')->whereIn('kelas_id', $kelasIds)->pluck('id_kelompok_kelas_blok');
            DB::table('anggota_kelompok_kelas_blok')->whereIn('kelompok_kelas_blok_id', $kelompokIds)->delete();
            DB::table('kelompok_kelas_blok')->whereIn('id_kelompok_kelas_blok', $kelompokIds)->delete();
            DB::table('peserta_kelas')->whereIn('kelas_id', $kelasIds)->delete();
            DB::table('kelas')->whereIn('id_kelas', $kelasIds)->delete();

            $materiIds = DB::table('materi_blok')
                ->whereIn('aturan_kegiatan_blok_id', DB::table('aturan_kegiatan_blok')->where('blok_id', $blokId)->pluck('id'))
                ->pluck('id_materi_blok');
            DB::table('materi_rinci_blok')->whereIn('materi_blok_id', $materiIds)->delete();
            DB::table('materi_blok')->whereIn('id_materi_blok', $materiIds)->delete();
            DB::table('aturan_kegiatan_blok')->where('blok_id', $blokId)->delete();

            DB::table('mata_kuliah')->where('prodi_id', $prodiId)->where('kode', 'KDK101')->delete();
            DB::table('blok')->where('id', $blokId)->delete();
            DB::table('mahasiswa')->whereIn('nim', [
                '260101001',
                '260101002',
                '260101003',
                '260101004',
                '260101005',
                '260101006',
                '260101007',
                '260101008',
                '260101009',
                '260101010',
            ])->delete();
            DB::table('dosen')->whereIn('nidn', ['1001017601', '1002027802', '1003038003'])->delete();
            DB::table('jenis_kegiatan')->whereIn('kode', ['KP', 'TUT', 'PRK', 'SKL'])->delete();
            DB::table('semester')->where('kode', 'GJ-2026')->delete();
            DB::table('prodi')->where('kode', 'PSPD')->delete();
        });
    }
};
