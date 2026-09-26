<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Dosen;
use App\Models\Kurikulum;
use App\Models\KurikulumMataKuliah;
use App\Models\MataKuliah;
use App\Models\PengelolaBlok;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\SkalaNilai;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class PengelolaBlokTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_create_tidak_meminta_kode_blok(): void
    {
        Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->assertDontSee('Kode Blok')
            ->assertDontSeeHtml('wire:model="kode"');
    }

    public function test_form_menyimpan_dan_menyinkronkan_banyak_kontributor(): void
    {
        [$prodi, $semester] = $this->akademik();
        $mapping = $this->mapping($prodi);
        [$koordinator, $asisten, $kontributorA, $kontributorB] = $this->dosen(4);

        $component = Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('prodi_id', (string) $prodi->id_prodi)
            ->set('kurikulum_mata_kuliah_id', (string) $mapping->id_kurikulum_mata_kuliah)
            ->set('semester_id', (string) $semester->id_semester)
            ->set('koordinator_id', (string) $koordinator->id_dosen)
            ->set('asisten_koordinator_id', (string) $asisten->id_dosen)
            ->set('selected_kontributor_ids', [
                (string) $kontributorA->id_dosen,
                (string) $kontributorB->id_dosen,
            ])
            ->set('nama', 'Blok Pengelola')
            ->set('sks', 4)
            ->call('saveCurrentTab')
            ->assertHasNoErrors();

        $blok = Blok::where('nama', 'Blok Pengelola')->sole();
        $this->assertSame($mapping->id_kurikulum_mata_kuliah, $blok->kurikulum_mata_kuliah_id);
        $this->assertSame($mapping->mata_kuliah_id, $blok->mata_kuliah_id);
        $this->assertEqualsCanonicalizing([
            [$koordinator->id_dosen, 'koordinator'],
            [$asisten->id_dosen, 'asisten_koordinator'],
            [$kontributorA->id_dosen, 'kontributor'],
            [$kontributorB->id_dosen, 'kontributor'],
        ], $blok->pengelola_blok()
            ->get(['dosen_id', 'jabatan'])
            ->map(fn (PengelolaBlok $pengelola) => [$pengelola->dosen_id, $pengelola->jabatan])
            ->all());

        $component
            ->set('selected_kontributor_ids', [(string) $kontributorB->id_dosen])
            ->call('saveCurrentTab')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('pengelola_blok', [
            'blok_id' => $blok->id,
            'dosen_id' => $kontributorA->id_dosen,
        ]);
        $this->assertSame(3, PengelolaBlok::where('blok_id', $blok->id)->count());
    }

    public function test_form_menolak_dosen_yang_merangkap_jabatan(): void
    {
        [$prodi, $semester] = $this->akademik();
        [$koordinator, $asisten] = $this->dosen(2);

        Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('prodi_id', (string) $prodi->id_prodi)
            ->set('semester_id', (string) $semester->id_semester)
            ->set('koordinator_id', (string) $koordinator->id_dosen)
            ->set('asisten_koordinator_id', (string) $asisten->id_dosen)
            ->set('selected_kontributor_ids', [(string) $koordinator->id_dosen])
            ->set('nama', 'Blok Duplikat')
            ->set('sks', 4)
            ->call('saveCurrentTab')
            ->assertHasErrors(['selected_kontributor_ids.0']);

        $this->assertDatabaseMissing('blok', ['nama' => 'Blok Duplikat']);
    }

    public function test_mapping_mata_kuliah_harus_sesuai_prodi_dan_menyinkronkan_kedua_id(): void
    {
        [$prodi, $semester] = $this->akademik();
        $prodiLain = Prodi::create(['kode' => 'PL', 'nama' => 'Prodi Lain']);
        $mappingLain = $this->mapping($prodiLain, 'L');
        $mapping = $this->mapping($prodi);
        [$koordinator, $asisten] = $this->dosen(2);

        $component = Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('prodi_id', (string) $prodi->id_prodi)
            ->set('semester_id', (string) $semester->id_semester)
            ->set('koordinator_id', (string) $koordinator->id_dosen)
            ->set('asisten_koordinator_id', (string) $asisten->id_dosen)
            ->set('nama', 'Blok Kurikulum')
            ->set('kurikulum_mata_kuliah_id', (string) $mappingLain->id_kurikulum_mata_kuliah)
            ->call('saveCurrentTab')
            ->assertHasErrors(['kurikulum_mata_kuliah_id']);

        $component
            ->set('kurikulum_mata_kuliah_id', (string) $mapping->id_kurikulum_mata_kuliah)
            ->call('saveCurrentTab')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('blok', [
            'nama' => 'Blok Kurikulum',
            'prodi_id' => $prodi->id_prodi,
            'kurikulum_mata_kuliah_id' => $mapping->id_kurikulum_mata_kuliah,
            'mata_kuliah_id' => $mapping->mata_kuliah_id,
        ]);
    }

    public function test_database_menolak_dosen_duplikat_dalam_blok_yang_sama(): void
    {
        $blok = $this->blok();
        [$dosen] = $this->dosen(1);
        PengelolaBlok::create(['blok_id' => $blok->id, 'dosen_id' => $dosen->id_dosen, 'jabatan' => 'koordinator']);

        $this->expectException(QueryException::class);
        PengelolaBlok::create(['blok_id' => $blok->id, 'dosen_id' => $dosen->id_dosen, 'jabatan' => 'kontributor']);
    }

    public function test_migration_memindahkan_data_lama_dan_menghapus_kolom_lama(): void
    {
        $migration = require database_path('migrations/2026_08_29_000002_create_pengelola_blok_table.php');
        $migration->down();

        $blok = $this->blok();
        [$koordinator, $asisten] = $this->dosen(2);
        DB::table('blok')->where('id', $blok->id)->update([
            'koordinator_id' => $koordinator->id_dosen,
            'asisten_koordinator_id' => $asisten->id_dosen,
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('blok', 'koordinator_id'));
        $this->assertFalse(Schema::hasColumn('blok', 'asisten_koordinator_id'));
        $this->assertDatabaseHas('pengelola_blok', [
            'blok_id' => $blok->id,
            'dosen_id' => $koordinator->id_dosen,
            'jabatan' => 'koordinator',
        ]);
        $this->assertDatabaseHas('pengelola_blok', [
            'blok_id' => $blok->id,
            'dosen_id' => $asisten->id_dosen,
            'jabatan' => 'asisten_koordinator',
        ]);
    }

    private function akademik(): array
    {
        return [
            Prodi::firstOrCreate(['kode' => 'PB'], ['nama' => 'Prodi Blok']),
            Semester::firstOrCreate(
                ['nama' => 'ganjil', 'tahun' => 2026],
                ['kode' => '2026-PB']
            ),
        ];
    }

    private function blok(): Blok
    {
        [$prodi, $semester] = $this->akademik();

        return Blok::create([
            'prodi_id' => $prodi->id_prodi,
            'semester_id' => $semester->id_semester,
            'nama' => 'Blok Pengelola',
            'sks' => 4,
        ]);
    }

    private function mapping(Prodi $prodi, string $suffix = 'A'): KurikulumMataKuliah
    {
        $skala = SkalaNilai::create([
            'nama' => "Skala {$suffix}",
            'versi' => 1,
            'aktif' => true,
        ]);
        $kurikulum = Kurikulum::create([
            'prodi_id' => $prodi->id_prodi,
            'skala_nilai_id' => $skala->id_skala_nilai,
            'kode' => "KUR-{$suffix}",
            'nama' => "Kurikulum {$suffix}",
            'tahun_berlaku' => 2026,
            'sks_lulus' => 144,
            'semester_normal' => 8,
            'status' => 'aktif',
        ]);
        $mataKuliah = MataKuliah::create([
            'prodi_id' => $prodi->id_prodi,
            'kode' => "MK-{$suffix}",
            'nama' => "Mata Kuliah {$suffix}",
            'sks' => 4,
            'status' => 'aktif',
        ]);

        return KurikulumMataKuliah::create([
            'kurikulum_id' => $kurikulum->id_kurikulum,
            'mata_kuliah_id' => $mataKuliah->id,
            'semester_urutan' => 1,
        ]);
    }

    private function dosen(int $jumlah): array
    {
        return collect(range(1, $jumlah))
            ->map(fn (int $nomor) => Dosen::create(['nama' => "Dosen Pengelola {$nomor} ".str()->random(5)]))
            ->all();
    }
}
