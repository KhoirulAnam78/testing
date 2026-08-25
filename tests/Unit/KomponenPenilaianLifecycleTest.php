<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class KomponenPenilaianLifecycleTest extends TestCase
{
    public function test_komponen_dikelola_langsung_melalui_jenis_kegiatan_tanpa_pivot_dan_route_master(): void
    {
        $jenisKegiatanSource = file_get_contents(__DIR__.'/../../resources/views/pages/jenis-kegiatan/add_edit.blade.php');
        $blokSource = file_get_contents(__DIR__.'/../../resources/views/pages/blok/add_edit.blade.php');
        $tableJenisKegiatanSource = file_get_contents(__DIR__.'/../../app/Livewire/TableJenisKegiatan.php');
        $modelSource = file_get_contents(__DIR__.'/../../app/Models/KomponenPenilaian.php');
        $routeSource = file_get_contents(__DIR__.'/../../routes/web.php');
        $menuMigrationSource = file_get_contents(__DIR__.'/../../database/migrations/2026_08_20_000002_remove_komponen_penilaian_menu.php');
        $relationMigrationSource = file_get_contents(__DIR__.'/../../database/migrations/2026_08_20_000003_move_komponen_penilaian_kegiatan_to_komponen_penilaian.php');

        $this->assertStringContainsString('public function addStandar(): void', $jenisKegiatanSource);
        $this->assertStringContainsString("'jenis_kegiatan_id' => \$jenis->id", $jenisKegiatanSource);
        $this->assertStringContainsString('new KomponenPenilaian([\'kode\' => $kode])', $jenisKegiatanSource);
        $this->assertStringContainsString("'komponen_penilaian_blok as pernah_digunakan'", $jenisKegiatanSource);
        $this->assertStringContainsString("->withTrashed()->exists()", $jenisKegiatanSource);
        $this->assertStringContainsString(
            "Komponen sudah digunakan oleh blok. Ubah status menjadi nonaktif.",
            $jenisKegiatanSource
        );
        $this->assertStringContainsString("->update(['status' => 'nonaktif']);", $jenisKegiatanSource);
        $this->assertStringContainsString("title=\"Komponen sudah digunakan oleh blok\"", $jenisKegiatanSource);
        $this->assertStringContainsString('public function updatedPakaiCbt(bool $pakaiCbt): void', $jenisKegiatanSource);
        $this->assertStringContainsString("'nama' => 'Nilai'", $jenisKegiatanSource);
        $this->assertStringContainsString("'nilai_min' => 1", $jenisKegiatanSource);
        $this->assertStringContainsString("'nilai_maks' => 100", $jenisKegiatanSource);
        $this->assertStringContainsString('$this->updatedPakaiCbt($this->pakai_cbt);', $jenisKegiatanSource);
        $this->assertStringContainsString(
            'Komponen penilaian Nilai dengan rentang 1–100 otomatis ditambahkan untuk CBT.',
            $jenisKegiatanSource
        );
        $this->assertStringContainsString("wire:model.live=\"pakai_cbt\"", $jenisKegiatanSource);
        $this->assertStringContainsString('@if (! $pakai_cbt)', $jenisKegiatanSource);
        $this->assertStringNotContainsString('KomponenPenilaianKegiatan', $jenisKegiatanSource);
        $this->assertStringContainsString("->where('jenis_kegiatan_id', \$jenisId)", $blokSource);
        $this->assertStringContainsString(
            "->whereHas('komponen_penilaian', fn (\$master) => \$master->aktif())",
            $blokSource
        );
        $this->assertStringNotContainsString('KomponenPenilaianKegiatan', $blokSource);
        $this->assertStringContainsString("if (str_ends_with(\$key, 'perlu_penilaian'))", $blokSource);
        $this->assertStringContainsString(
            "if ((bool) \$value && empty(\$this->aturan[\$index]['komponen']))",
            $blokSource
        );
        $this->assertStringContainsString(
            "if (empty(\$this->aturan[\$index]['komponen'])) {\n                \$this->ambilStandarPenilaian((int) \$index);",
            str_replace("\r\n", "\n", $blokSource)
        );
        $this->assertStringContainsString(
            "'komponen_penilaian as komponen_penilaian_aktif_count' => fn (\$query) => \$query->aktif()",
            $tableJenisKegiatanSource
        );
        $this->assertStringContainsString("Column::make('CBT', 'pakai_cbt_label', 'pakai_cbt')->sortable()", $tableJenisKegiatanSource);
        $this->assertStringContainsString("Column::make('Komponen Penilaian', 'komponen_penilaian')", $tableJenisKegiatanSource);
        $this->assertStringContainsString('Ada (\'.$row->komponen_penilaian_aktif_count.\' komponen)', $tableJenisKegiatanSource);
        $this->assertStringContainsString('public function jenis_kegiatan(): BelongsTo', $modelSource);
        $this->assertStringContainsString("Schema::drop('komponen_penilaian_kegiatan');", $relationMigrationSource);
        $this->assertStringNotContainsString("Route::livewire('komponen-penilaian'", $routeSource);
        $this->assertStringContainsString("Permission::where('name', 'komponen-penilaian:')->delete();", $menuMigrationSource);
        $this->assertStringContainsString("Menu::where('route', 'komponen-penilaian.index')->delete();", $menuMigrationSource);
    }

    public function test_kegiatan_blok_menolak_jenis_duplikat_dan_tidak_memakai_kapasitas(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/pages/blok/add_edit.blade.php');

        $this->assertStringContainsString('Jenis kegiatan sudah dipilih pada kegiatan lain.', $source);
        $this->assertStringContainsString('@disabled(collect($aturan)->except($index)', $source);
        $this->assertStringContainsString("'jumlah_mahasiswa_per_kelompok' => null,", $source);
        $this->assertStringNotContainsString('<label class="form-label">Kapasitas</label>', $source);
        $this->assertStringNotContainsString("'aturan.*.jumlah_mahasiswa_per_kelompok' =>", $source);
    }

    public function test_materi_baru_selalu_memakai_model_baru_bukan_menimpa_baris_pertama(): void
    {
        $source = str_replace(
            "\r\n",
            "\n",
            file_get_contents(__DIR__.'/../../resources/views/pages/blok/add_edit.blade.php')
        );

        $this->assertStringNotContainsString("->when(\$materi['id']", $source);
        $this->assertStringNotContainsString("->when(\$rinci['id']", $source);
        $this->assertSame(2, substr_count($source, "\$materi['id']\n"));
        $this->assertSame(2, substr_count($source, "\$rinci['id']\n"));
        $this->assertSame(4, substr_count($source, ': new Materi'));
    }
}
