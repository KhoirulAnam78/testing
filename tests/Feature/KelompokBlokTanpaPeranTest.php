<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KelompokBlokTanpaPeranTest extends TestCase
{
    use RefreshDatabase;

    public function test_kelompok_manual_menyimpan_anggota_tanpa_peran(): void
    {
        $data = $this->fixture(2);

        Livewire::test('blok-operasional.kelompok', ['blok_id' => $data['blok']])
            ->set('kode', 'K1')
            ->set('nama', 'Kelompok 1')
            ->set('anggota_ids', array_map('strval', $data['peserta']))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(Schema::hasColumn('anggota_kelompok_blok', 'peran'));
        $this->assertSame(2, DB::table('anggota_kelompok_blok')
            ->join('kelompok_blok', 'kelompok_blok.id_kelompok_blok', '=', 'anggota_kelompok_blok.kelompok_blok_id')
            ->where('kelompok_blok.blok_id', $data['blok'])
            ->count());
    }

    public function test_bagi_merata_membagi_semua_peserta_tanpa_peran(): void
    {
        $data = $this->fixture(4);

        Livewire::test('blok-operasional.kelompok', ['blok_id' => $data['blok']])
            ->set('gen_jumlah', 2)
            ->set('gen_prefix', 'U')
            ->call('generateKelompok')
            ->assertHasNoErrors();

        $jumlahAnggota = DB::table('kelompok_blok')
            ->leftJoin('anggota_kelompok_blok', 'anggota_kelompok_blok.kelompok_blok_id', '=', 'kelompok_blok.id_kelompok_blok')
            ->where('kelompok_blok.blok_id', $data['blok'])
            ->groupBy('kelompok_blok.id_kelompok_blok')
            ->orderBy('kelompok_blok.kode')
            ->selectRaw('count(anggota_kelompok_blok.id_anggota_kelompok_blok) as jumlah')
            ->pluck('jumlah')
            ->map(fn ($jumlah) => (int) $jumlah)
            ->all();

        $this->assertSame([2, 2], $jumlahAnggota);
        $this->assertFalse(Schema::hasColumn('anggota_kelompok_blok', 'peran'));
    }

    /**
     * @return array{blok: int, peserta: array<int, int>}
     */
    private function fixture(int $jumlahPeserta): array
    {
        $user = User::factory()->create();
        Permission::findOrCreate('blok-operasional:');
        $user->givePermissionTo('blok-operasional:');
        $this->actingAs($user);

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
        DB::table('aturan_kegiatan_blok')->insert([
            'blok_id' => $blok,
            'jenis_kegiatan_id' => $jenis,
            'durasi_menit' => 100,
            'perlu_kelompok' => true,
        ]);

        $peserta = [];

        foreach (range(1, $jumlahPeserta) as $nomor) {
            $mahasiswa = DB::table('mahasiswa')->insertGetId([
                'prodi_id' => $prodi,
                'nim' => fake()->unique()->numerify('########'),
                'nama' => "Mahasiswa {$nomor}",
                'angkatan' => 2026,
            ], 'id_mahasiswa');
            $peserta[] = DB::table('peserta_blok')->insertGetId([
                'blok_id' => $blok,
                'mahasiswa_id' => $mahasiswa,
            ], 'id_peserta_blok');
        }

        return compact('blok', 'peserta');
    }
}
