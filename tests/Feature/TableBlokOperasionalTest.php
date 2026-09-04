<?php

namespace Tests\Feature;

use App\Livewire\TableBlokOperasional;
use App\Models\Blok;
use App\Models\Dosen;
use App\Models\PengelolaBlok;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TableBlokOperasionalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        Permission::findOrCreate('blok-operasional:');
        $user->givePermissionTo('blok-operasional:');
        $this->actingAs($user);
    }

    public function test_daftar_dapat_dicari_difilter_dan_di_reset(): void
    {
        $prodiA = Prodi::create(['kode' => 'PA', 'nama' => 'Prodi A']);
        $prodiB = Prodi::create(['kode' => 'PB', 'nama' => 'Prodi B']);
        $semester = Semester::firstOrCreate(
            ['nama' => 'ganjil', 'tahun' => 2026],
            ['kode' => '2026-GANJIL', 'is_aktif' => true]
        );

        Blok::create([
            'prodi_id' => $prodiA->id_prodi,
            'semester_id' => $semester->id_semester,
            'kode' => 'BLOK-A',
            'nama' => 'Blok Ditampilkan',
            'sks' => 4,
        ]);
        Blok::create([
            'prodi_id' => $prodiB->id_prodi,
            'semester_id' => $semester->id_semester,
            'kode' => 'BLOK-B',
            'nama' => 'Blok Disembunyikan',
            'sks' => 4,
        ]);

        Livewire::test(TableBlokOperasional::class)
            ->assertSee('Blok Ditampilkan')
            ->assertSee('Blok Disembunyikan')
            ->assertSeeHtml('class="blok-card__header p-3"')
            ->assertSee('--blok-accent: var(--vz-primary);', false)
            ->set('search', 'BLOK-A')
            ->assertSee('Blok Ditampilkan')
            ->assertDontSee('BLOK-A')
            ->assertDontSee('Blok Disembunyikan')
            ->set('search', '')
            ->set('prodiId', (string) $prodiB->id_prodi)
            ->assertDontSee('Blok Ditampilkan')
            ->assertSee('Blok Disembunyikan')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('prodiId', '')
            ->assertSet('semesterId', (string) $semester->id_semester)
            ->assertSee('Blok Ditampilkan')
            ->assertSee('Blok Disembunyikan');
    }

    public function test_kontributor_hanya_melihat_blok_yang_dikelola(): void
    {
        $user = User::factory()->create();
        $dosen = Dosen::create([
            'user_id' => $user->id,
            'nama' => 'Koordinator Uji',
        ]);
        $prodi = Prodi::create(['kode' => 'PA', 'nama' => 'Prodi A']);
        $semester = Semester::firstOrCreate(
            ['nama' => 'ganjil', 'tahun' => 2026],
            ['kode' => '2026-GANJIL']
        );

        $blokKelola = Blok::create([
            'prodi_id' => $prodi->id_prodi,
            'semester_id' => $semester->id_semester,
            'kode' => 'BLOK-KELOLA',
            'nama' => 'Blok Dapat Dikelola',
            'sks' => 4,
        ]);
        PengelolaBlok::create([
            'blok_id' => $blokKelola->id,
            'dosen_id' => $dosen->id_dosen,
            'jabatan' => 'kontributor',
        ]);
        Blok::create([
            'prodi_id' => $prodi->id_prodi,
            'semester_id' => $semester->id_semester,
            'kode' => 'BLOK-LAIN',
            'nama' => 'Blok Dosen Lain',
            'sks' => 4,
        ]);

        $this->actingAs($user);

        Livewire::test(TableBlokOperasional::class)
            ->assertSee('Blok Dapat Dikelola')
            ->assertDontSee('Blok Dosen Lain');
    }

    public function test_daftar_memakai_pagination_sepuluh_item(): void
    {
        $prodi = Prodi::create(['kode' => 'PA', 'nama' => 'Prodi A']);
        $semester = Semester::firstOrCreate(
            ['nama' => 'ganjil', 'tahun' => 2026],
            ['kode' => '2026-GANJIL']
        );

        $jumlahAwal = Blok::count();

        foreach (range(1, 11) as $number) {
            Blok::create([
                'prodi_id' => $prodi->id_prodi,
                'semester_id' => $semester->id_semester,
                'kode' => "BLOK-{$number}",
                'nama' => "Blok {$number}",
                'sks' => 4,
                'tanggal_mulai' => now()->subDays($number),
            ]);
        }

        Livewire::test(TableBlokOperasional::class)
            ->assertViewHas(
                'bloks',
                fn ($bloks) => $bloks->count() === 10 && $bloks->total() === $jumlahAwal + 11
            );
    }
}
