<?php

namespace Tests\Feature;

use App\Livewire\TableBlok;
use App\Livewire\TableBlokOperasional;
use App\Livewire\TableDpnaBlok;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SemesterAktifFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Semester::query()->update(['is_aktif' => false]);
        $this->actingAs(User::factory()->create());
    }

    public function test_semester_aktif_menjadi_default_semua_filter_semester(): void
    {
        $this->semester('ganjil', 2025, false);
        $aktif = $this->semester('genap', 2025, true);

        Livewire::test(TableBlok::class)
            ->assertSet('semesterId', (string) $aktif->id_semester)
            ->set('semesterId', '')
            ->call('resetFilters')
            ->assertSet('semesterId', (string) $aktif->id_semester);
        Livewire::test(TableBlokOperasional::class)
            ->assertSet('semesterId', (string) $aktif->id_semester)
            ->set('semesterId', '')
            ->call('resetFilters')
            ->assertSet('semesterId', (string) $aktif->id_semester);
        Livewire::test(TableDpnaBlok::class)
            ->assertSet('semesterId', (string) $aktif->id_semester)
            ->set('semesterId', '')
            ->call('resetFilters')
            ->assertSet('semesterId', (string) $aktif->id_semester);
    }

    public function test_semester_dari_url_tidak_ditimpa_default(): void
    {
        $dipilih = $this->semester('ganjil', 2025, false);
        $this->semester('genap', 2025, true);

        Livewire::withQueryParams(['semester' => $dipilih->id_semester])
            ->test(TableBlok::class)
            ->assertSet('semesterId', (string) $dipilih->id_semester);
        Livewire::withQueryParams(['semester' => $dipilih->id_semester])
            ->test(TableBlokOperasional::class)
            ->assertSet('semesterId', (string) $dipilih->id_semester);
        Livewire::withQueryParams(['semester' => $dipilih->id_semester])
            ->test(TableDpnaBlok::class)
            ->assertSet('semesterId', (string) $dipilih->id_semester);
    }

    public function test_filter_tetap_semua_semester_jika_tidak_ada_semester_aktif(): void
    {
        $this->semester('ganjil', 2025, false);

        Livewire::test(TableBlok::class)
            ->assertSet('semesterId', '');
        Livewire::test(TableBlokOperasional::class)
            ->assertSet('semesterId', '');
        Livewire::test(TableDpnaBlok::class)
            ->assertSet('semesterId', '');
    }

    private function semester(string $nama, int $tahun, bool $aktif): Semester
    {
        return Semester::create([
            'nama' => $nama,
            'tahun' => $tahun,
            'kode' => $tahun.($nama === 'ganjil' ? '1' : '2'),
            'is_aktif' => $aktif,
        ]);
    }
}
