<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PertemuanBlokHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_menampilkan_hierarki_materi_rincian_dan_dosen(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('blok-operasional:');
        $user->givePermissionTo('blok-operasional:');
        $this->actingAs($user);

        $prodi = DB::table('prodi')->insertGetId([
            'kode' => 'PUJI',
            'nama' => 'Prodi Uji',
        ], 'id_prodi');
        $semester = DB::table('semester')
            ->where('nama', 'ganjil')
            ->where('tahun', 2026)
            ->value('id_semester');
        $blok = DB::table('blok')->insertGetId([
            'prodi_id' => $prodi,
            'semester_id' => $semester,
            'kode' => 'B-UJI',
            'nama' => 'Blok Uji',
            'sks' => 4,
        ]);
        $jenis = DB::table('jenis_kegiatan')->insertGetId([
            'kode' => 'J-UJI',
            'nama' => 'Tutorial Uji',
            'jumlah_pertemuan_default' => 1,
            'durasi_menit_default' => 100,
        ]);
        $aturan = DB::table('aturan_kegiatan_blok')->insertGetId([
            'blok_id' => $blok,
            'jenis_kegiatan_id' => $jenis,
            'durasi_menit' => 100,
            'perlu_kelompok' => true,
        ]);
        $kelompok = DB::table('kelompok_blok')->insertGetId([
            'blok_id' => $blok,
            'aturan_kegiatan_blok_id' => $aturan,
            'kode' => 'K1',
            'nama' => 'Kelompok 1',
        ], 'id_kelompok_blok');
        DB::table('kelompok_blok')->insert([
            'blok_id' => $blok,
            'aturan_kegiatan_blok_id' => $aturan,
            'kode' => 'K2',
            'nama' => 'Kelompok 2',
        ]);
        $materi = DB::table('materi_blok')->insertGetId([
            'aturan_kegiatan_blok_id' => $aturan,
            'judul' => 'Sistem Kardiovaskular',
        ], 'id_materi_blok');
        $rinci = DB::table('materi_rinci_blok')->insertGetId([
            'materi_blok_id' => $materi,
            'judul' => 'Anatomi Jantung',
            'pertemuan_ke' => 1,
        ], 'id_materi_rinci_blok');
        $pertemuan = DB::table('pertemuan_blok')->insertGetId([
            'blok_id' => $blok,
            'aturan_kegiatan_blok_id' => $aturan,
            'materi_rinci_blok_id' => $rinci,
            'kelompok_blok_id' => $kelompok,
        ], 'id_pertemuan_blok');
        $dosen = DB::table('dosen')->insertGetId([
            'nama' => 'Dr. Dosen Penguji',
        ], 'id_dosen');
        DB::table('dosen_pertemuan_blok')->insert([
            'pertemuan_blok_id' => $pertemuan,
            'dosen_id' => $dosen,
        ]);
        $rinciTanpaDosen = DB::table('materi_rinci_blok')->insertGetId([
            'materi_blok_id' => $materi,
            'judul' => 'Fisiologi Jantung',
            'pertemuan_ke' => 2,
            'urutan' => 2,
        ], 'id_materi_rinci_blok');
        DB::table('pertemuan_blok')->insert([
            'blok_id' => $blok,
            'aturan_kegiatan_blok_id' => $aturan,
            'materi_rinci_blok_id' => $rinciTanpaDosen,
            'kelompok_blok_id' => $kelompok,
        ]);

        Livewire::test('blok-operasional.pertemuan', ['blok_id' => $blok])
            ->assertSeeInOrder([
                '2 kelompok aktif',
                'Kelompok 1 dari 2',
                'K1 - Kelompok 1',
                'Materi Pokok 1',
                'Sistem Kardiovaskular',
                'Rincian Pertemuan',
                'Pertemuan 1',
                'Anatomi Jantung',
                'Dosen Pengampu',
                'Dr. Dosen Penguji',
                'Rincian Pertemuan',
                'Pertemuan 2',
                'Fisiologi Jantung',
                'Dosen Pengampu',
                'Belum ditentukan',
                'Kelompok 2 dari 2',
                'K2 - Kelompok 2',
                'Materi Pokok 1',
                'Sistem Kardiovaskular',
                'Rincian Pertemuan',
                'Pertemuan 1',
                'Anatomi Jantung',
                'Dosen Pengampu',
                'Belum ditentukan',
            ]);
    }
}
