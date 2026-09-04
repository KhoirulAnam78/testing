<?php

namespace Tests\Feature;

use App\Exports\ArraySheetExport;
use App\Exports\NilaiPertemuanTemplateExport;
use App\Imports\NilaiPertemuanImport;
use App\Models\KomponenPenilaianBlok;
use App\Models\NilaiPertemuanBlok;
use App\Models\PesertaBlok;
use App\Models\RekapNilaiPertemuanBlok;
use App\Models\User;
use App\Support\AksesPertemuanBlok;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as FormatExcel;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Template dan import nilai satu pertemuan.
 *
 * Sebagian besar aturan diuji langsung ke kelas import, bukan lewat Livewire, supaya yang
 * diuji adalah pembacaan dan validasinya — bukan plumbing unggah berkas. Dua uji terakhir
 * menutup jalur ujung-ke-ujung dan memastikan dosen pengampu maupun pengelola diperlakukan
 * sama, sesuai `AksesPertemuanBlok::bolehIsiNilai()`.
 */
class ImportNilaiPertemuanBlokTest extends TestCase
{
    use RefreshDatabase;

    private const KOMPONEN = 'blok-operasional.nilai-pertemuan';

    public function test_template_memuat_seluruh_anggota_dan_nilai_yang_sudah_tersimpan(): void
    {
        $data = $this->fixture();
        $komponen = $this->komponen($data);
        $anggota = $this->anggota($data);

        $export = new NilaiPertemuanTemplateExport($anggota, $komponen, [
            $data['peserta_a'] => [$komponen[0]->id => '8', $komponen[1]->id => '15'],
        ]);

        $sheets = $export->sheets();
        $nilai = $sheets[0]->array();

        $this->assertSame(['nim', 'nama', 'kog', 'afk'], $nilai[0]);
        $this->assertCount(3, $nilai, 'Satu baris judul dan dua baris mahasiswa.');

        // Baris peserta A sudah memuat nilai tersimpan; peserta B dibiarkan kosong (null)
        // supaya di Excel benar-benar kosong dan bisa langsung diketik.
        $this->assertSame($data['nim_a'], $nilai[1][0]);
        $this->assertSame(8.0, $nilai[1][2]);
        $this->assertSame(15.0, $nilai[1][3]);
        $this->assertNull($nilai[2][2]);
        $this->assertNull($nilai[2][3]);

        // Sheet kedua hanya keterangan dan tidak pernah dibaca saat import.
        $this->assertSame('Nilai', $sheets[0]->title());
        $this->assertSame('Petunjuk', $sheets[1]->title());
    }

    public function test_pembacaan_menerima_desimal_koma_dan_menganggap_sel_kosong_sebagai_penghapusan(): void
    {
        $data = $this->fixture();
        $komponen = $this->komponen($data);
        $import = new NilaiPertemuanImport($this->anggota($data), $komponen);

        Excel::import($import, $this->berkas([
            ['nim', 'nama', 'kog', 'afk'],
            [$data['nim_a'], 'A', '7,5', ''],
        ]));

        $this->assertSame([
            $data['peserta_a'] => [
                $komponen[0]->id => '7.5',
                $komponen[1]->id => '',
            ],
        ], $import->nilai());
    }

    public function test_pembacaan_menolak_nim_yang_bukan_anggota_kelompok_pertemuan(): void
    {
        $data = $this->fixture();
        $import = new NilaiPertemuanImport($this->anggota($data), $this->komponen($data));

        $this->assertPenolakan(
            fn () => Excel::import($import, $this->berkas([
                ['nim', 'nama', 'kog', 'afk'],
                ['99999999', 'Orang Luar', '5', '5'],
            ])),
            'bukan anggota aktif kelompok pertemuan ini',
        );
    }

    public function test_pembacaan_menolak_nilai_di_luar_batas_komponen(): void
    {
        $data = $this->fixture();
        $import = new NilaiPertemuanImport($this->anggota($data), $this->komponen($data));

        // Batas kog adalah 0-10, jadi 11 harus ditolak walau kolom lain benar.
        $this->assertPenolakan(
            fn () => Excel::import($import, $this->berkas([
                ['nim', 'nama', 'kog', 'afk'],
                [$data['nim_a'], 'A', '11', '5'],
            ])),
            'harus di antara 0 dan 10',
        );
    }

    public function test_pembacaan_menolak_nim_ganda_dan_kolom_komponen_yang_hilang(): void
    {
        $data = $this->fixture();

        $this->assertPenolakan(
            fn () => Excel::import(
                new NilaiPertemuanImport($this->anggota($data), $this->komponen($data)),
                $this->berkas([
                    ['nim', 'nama', 'kog', 'afk'],
                    [$data['nim_a'], 'A', '5', '5'],
                    [$data['nim_a'], 'A', '6', '6'],
                ]),
            ),
            'ditulis lebih dari sekali',
        );

        $this->assertPenolakan(
            fn () => Excel::import(
                new NilaiPertemuanImport($this->anggota($data), $this->komponen($data)),
                $this->berkas([
                    ['nim', 'nama', 'kog'],
                    [$data['nim_a'], 'A', '5'],
                ]),
            ),
            'Kolom afk tidak ada di file',
        );
    }

    public function test_nama_kolom_tetap_unik_walau_slug_kode_komponen_bertabrakan(): void
    {
        $data = $this->fixture();

        $bertabrakan = DB::table('komponen_penilaian')->insertGetId([
            'kode' => 'K.O.G',
            'nama' => 'Kognitif Tabrakan',
            'nilai_min_default' => 0,
            'nilai_maks_default' => 10,
        ]);
        $idBlok = DB::table('komponen_penilaian_blok')->insertGetId([
            'aturan_kegiatan_blok_id' => $data['aturan'],
            'komponen_penilaian_id' => $bertabrakan,
            'nilai_min' => 0,
            'nilai_maks' => 10,
            'urutan' => 3,
        ]);

        $kunci = NilaiPertemuanImport::kunciKomponen($this->komponen($data));

        // "KOG" dan "K.O.G" sama-sama menjadi slug "kog", jadi yang kedua harus dibedakan
        // dengan id komponen per blok yang selalu unik.
        $this->assertCount(3, $kunci);
        $this->assertArrayHasKey('kog', $kunci);
        $this->assertArrayHasKey('kog_'.$idBlok, $kunci);
        $this->assertSame($idBlok, $kunci['kog_'.$idBlok]);
    }

    public function test_dosen_pengampu_import_menulis_nilai_dan_rekap(): void
    {
        $data = $this->fixture();
        $komponen = $this->komponen($data);

        $this->actingAs($data['dosen']);

        Livewire::test(self::KOMPONEN, ['pertemuan_blok_id' => $data['pertemuan']])
            ->set('importFile', $this->unggahan([
                ['nim', 'nama', 'kog', 'afk'],
                [$data['nim_a'], 'A', '8', '15'],
            ]))
            ->call('importNilai')
            ->assertHasNoErrors();

        $this->assertSame(2, NilaiPertemuanBlok::where('pertemuan_blok_id', $data['pertemuan'])->count());
        $this->assertEqualsWithDelta(8.0, (float) NilaiPertemuanBlok::where([
            'pertemuan_blok_id' => $data['pertemuan'],
            'peserta_blok_id' => $data['peserta_a'],
            'komponen_penilaian_blok_id' => $komponen[0]->id,
        ])->value('nilai'), 0.01);

        // Nilai maksimum rubrik 10 + 20 = 30, jadi total 23 dinormalkan menjadi 76,67.
        $rekap = RekapNilaiPertemuanBlok::where([
            'pertemuan_blok_id' => $data['pertemuan'],
            'peserta_blok_id' => $data['peserta_a'],
        ])->firstOrFail();

        $this->assertEqualsWithDelta(23.0, (float) $rekap->total, 0.01);
        $this->assertEqualsWithDelta(76.67, (float) $rekap->nilai_akhir, 0.01);

        // Peserta B tidak ada di berkas, jadi tidak boleh tersentuh sama sekali.
        $this->assertSame(0, NilaiPertemuanBlok::where('peserta_blok_id', $data['peserta_b'])->count());
        $this->assertSame(0, RekapNilaiPertemuanBlok::where('peserta_blok_id', $data['peserta_b'])->count());
    }

    public function test_pengelola_mendapat_import_yang_sama_dan_pengguna_lain_ditolak(): void
    {
        $data = $this->fixture();

        Permission::findOrCreate(AksesPertemuanBlok::IZIN_PENGELOLA);
        $pengelola = User::factory()->create();
        $pengelola->givePermissionTo(AksesPertemuanBlok::IZIN_PENGELOLA);

        $this->assertTrue(AksesPertemuanBlok::bolehIsiNilai($pengelola, $data['pertemuan']));

        $this->actingAs($pengelola);
        Livewire::test(self::KOMPONEN, ['pertemuan_blok_id' => $data['pertemuan']])
            ->set('importFile', $this->unggahan([
                ['nim', 'nama', 'kog', 'afk'],
                [$data['nim_b'], 'B', '3', '4'],
            ]))
            ->call('importNilai')
            ->assertHasNoErrors();

        $this->assertSame(2, NilaiPertemuanBlok::where('peserta_blok_id', $data['peserta_b'])->count());

        $this->actingAs(User::factory()->create());
        Livewire::test(self::KOMPONEN, ['pertemuan_blok_id' => $data['pertemuan']])
            ->assertForbidden();
    }

    /**
     * @param  callable(): mixed  $aksi
     */
    private function assertPenolakan(callable $aksi, string $potonganPesan): void
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            $this->assertStringContainsString($potonganPesan, $e->errors()['import_nilai'][0] ?? '');

            return;
        }

        $this->fail("Import seharusnya ditolak dengan pesan yang memuat: {$potonganPesan}");
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function berkas(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'uji-nilai').'.xlsx';
        file_put_contents($path, Excel::raw(new ArraySheetExport('Nilai', $rows), FormatExcel::XLSX));

        return $path;
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function unggahan(array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('nilai.xlsx', file_get_contents($this->berkas($rows)));
    }

    /**
     * @return EloquentCollection<int, KomponenPenilaianBlok>
     */
    private function komponen(array $data): EloquentCollection
    {
        return KomponenPenilaianBlok::query()
            ->where('aturan_kegiatan_blok_id', $data['aturan'])
            ->with('komponen_penilaian:id,kode,nama')
            ->orderBy('urutan')
            ->get();
    }

    /**
     * @return EloquentCollection<int, PesertaBlok>
     */
    private function anggota(array $data): EloquentCollection
    {
        return PesertaBlok::query()
            ->whereIn('id_peserta_blok', [$data['peserta_a'], $data['peserta_b']])
            ->with('mahasiswa:id_mahasiswa,nim,nama')
            ->orderBy('id_peserta_blok')
            ->get();
    }

    /**
     * Dua mahasiswa dalam satu kelompok, rubrik dua komponen (kog 0-10, afk 0-20).
     *
     * `aturan_kegiatan_blok.jumlah_pertemuan` sengaja tidak diisi: kolom itu sudah dihapus
     * oleh migrasi 2026_08_24_000003 dan jumlah pertemuan kini diturunkan dari materi rinci.
     */
    private function fixture(): array
    {
        $dosenUser = User::factory()->create();

        $prodi = DB::table('prodi')->insertGetId([
            'kode' => fake()->unique()->lexify('P???'),
            'nama' => 'Prodi Uji',
        ], 'id_prodi');
        $semester = DB::table('semester')
            ->where('nama', 'ganjil')
            ->where('tahun', 2026)
            ->value('id_semester')
            ?? DB::table('semester')->insertGetId([
                'kode' => fake()->unique()->lexify('S???'),
                'nama' => 'ganjil',
                'tahun' => 2026,
            ], 'id_semester');
        $dosen = DB::table('dosen')->insertGetId([
            'user_id' => $dosenUser->id,
            'nama' => 'Pengampu Uji',
        ], 'id_dosen');
        $blok = DB::table('blok')->insertGetId([
            'prodi_id' => $prodi,
            'semester_id' => $semester,
            'kode' => fake()->unique()->lexify('B???'),
            'nama' => 'Blok Uji',
            'sks' => 4,
        ]);
        $jenis = DB::table('jenis_kegiatan')->insertGetId([
            'kode' => fake()->unique()->lexify('J???'),
            'nama' => 'Tutorial Uji',
            'jumlah_pertemuan_default' => 1,
            'durasi_menit_default' => 100,
        ]);
        $aturan = DB::table('aturan_kegiatan_blok')->insertGetId([
            'blok_id' => $blok,
            'jenis_kegiatan_id' => $jenis,
            'durasi_menit' => 100,
            'perlu_kelompok' => true,
            'perlu_penilaian' => true,
        ]);
        $materi = DB::table('materi_blok')->insertGetId([
            'aturan_kegiatan_blok_id' => $aturan,
            'judul' => 'Materi Uji',
        ], 'id_materi_blok');
        $materiRinci = DB::table('materi_rinci_blok')->insertGetId([
            'materi_blok_id' => $materi,
            'judul' => 'Pertemuan Uji',
        ], 'id_materi_rinci_blok');
        $kelompok = DB::table('kelompok_blok')->insertGetId([
            'blok_id' => $blok,
            'aturan_kegiatan_blok_id' => $aturan,
            'kode' => 'K1',
            'nama' => 'Kelompok 1',
        ], 'id_kelompok_blok');

        $peserta = [];
        $nim = [];

        foreach (['a', 'b'] as $tanda) {
            $nim[$tanda] = fake()->unique()->numerify('########');
            $mahasiswa = DB::table('mahasiswa')->insertGetId([
                'user_id' => User::factory()->create()->id,
                'prodi_id' => $prodi,
                'nim' => $nim[$tanda],
                'nama' => 'Mahasiswa '.strtoupper($tanda),
                'angkatan' => 2026,
            ], 'id_mahasiswa');
            $peserta[$tanda] = DB::table('peserta_blok')->insertGetId([
                'blok_id' => $blok,
                'mahasiswa_id' => $mahasiswa,
            ], 'id_peserta_blok');
            DB::table('anggota_kelompok_blok')->insert([
                'kelompok_blok_id' => $kelompok,
                'peserta_blok_id' => $peserta[$tanda],
            ]);
        }

        $pertemuan = DB::table('pertemuan_blok')->insertGetId([
            'blok_id' => $blok,
            'aturan_kegiatan_blok_id' => $aturan,
            'materi_rinci_blok_id' => $materiRinci,
            'kelompok_blok_id' => $kelompok,
        ], 'id_pertemuan_blok');
        DB::table('dosen_pertemuan_blok')->insert([
            'pertemuan_blok_id' => $pertemuan,
            'dosen_id' => $dosen,
        ]);

        foreach ([['KOG', 'Kognitif', 10, 1], ['AFK', 'Afektif', 20, 2]] as [$kode, $nama, $maks, $urutan]) {
            $master = DB::table('komponen_penilaian')->insertGetId([
                'kode' => $kode,
                'nama' => $nama,
                'nilai_min_default' => 0,
                'nilai_maks_default' => $maks,
            ]);
            DB::table('komponen_penilaian_blok')->insert([
                'aturan_kegiatan_blok_id' => $aturan,
                'komponen_penilaian_id' => $master,
                'nilai_min' => 0,
                'nilai_maks' => $maks,
                'urutan' => $urutan,
            ]);
        }

        return [
            'dosen' => $dosenUser,
            'aturan' => $aturan,
            'pertemuan' => $pertemuan,
            'peserta_a' => $peserta['a'],
            'peserta_b' => $peserta['b'],
            'nim_a' => $nim['a'],
            'nim_b' => $nim['b'],
        ];
    }
}
