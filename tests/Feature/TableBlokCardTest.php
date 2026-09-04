<?php

namespace Tests\Feature;

use App\Livewire\TableBlok;
use App\Models\Blok;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TableBlokCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_card_dapat_dicari_difilter_dan_memakai_warna_stabil(): void
    {
        Semester::query()->update(['is_aktif' => false]);
        $semester = Semester::create([
            'kode' => '2026-GANJIL-CARD',
            'nama' => 'ganjil',
            'tahun' => 2026,
            'is_aktif' => true,
        ]);
        $prodiA = Prodi::create(['kode' => 'CARD-A', 'nama' => 'Prodi Card A']);
        $prodiB = Prodi::create(['kode' => 'CARD-B', 'nama' => 'Prodi Card B']);
        $blokA = Blok::create([
            'prodi_id' => $prodiA->id_prodi,
            'semester_id' => $semester->id_semester,
            'kode' => 'CARD-BLOK-A',
            'nama' => 'Blok Card Ditampilkan',
            'sks' => 4,
            'status' => 'aktif',
        ]);
        Blok::create([
            'prodi_id' => $prodiB->id_prodi,
            'semester_id' => $semester->id_semester,
            'kode' => 'CARD-BLOK-B',
            'nama' => 'Blok Card Disembunyikan',
            'sks' => 3,
            'status' => 'draft',
        ]);

        $colors = ['blue', 'green', 'orange', 'cyan', 'red', 'magenta'];
        $expectedCardClass = 'master-blok-card--'.$colors[$blokA->id % count($colors)];

        $component = Livewire::test(TableBlok::class)
            ->assertSee('Blok Card Ditampilkan')
            ->assertSee('Blok Card Disembunyikan')
            ->assertSee('Mata Kuliah')
            ->set('search', 'CARD-BLOK-A')
            ->assertSee('Blok Card Ditampilkan')
            ->assertDontSee('Blok Card Disembunyikan')
            ->set('search', '')
            ->set('prodiId', (string) $prodiB->id_prodi)
            ->assertDontSee('Blok Card Ditampilkan')
            ->assertSee('Blok Card Disembunyikan')
            ->call('resetFilters')
            ->assertSet('prodiId', '')
            ->assertSet('semesterId', (string) $semester->id_semester);

        $this->assertStringContainsString(
            '<article class="card master-blok-card '.$expectedCardClass,
            $component->html()
        );
    }
}
