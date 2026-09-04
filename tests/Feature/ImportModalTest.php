<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Tests\TestCase;

class ImportModalTest extends TestCase
{
    public function test_form_dan_template_import_data_master_berada_di_modal(): void
    {
        foreach ([
            'pages::dosen.index' => 'dosen',
            'pages::mahasiswa.index' => 'mahasiswa',
            'pages::mata-kuliah.index' => 'mata-kuliah',
        ] as $komponen => $id) {
            Livewire::test($komponen)
                ->assertSeeHtml('data-bs-target="#modal-import-'.$id.'"')
                ->assertSeeHtml('id="modal-import-'.$id.'"')
                ->assertSeeHtml('wire:submit="import"')
                ->assertSee('Template Import');
        }
    }

    public function test_form_dan_template_import_nilai_pertemuan_berada_di_modal_unik(): void
    {
        $blade = file_get_contents(resource_path('views/components/blok-operasional/nilai-pertemuan.blade.php'));

        $this->assertStringContainsString('@teleport(\'body\')', $blade);
        $this->assertStringContainsString('$dispatch(\'buka-import-nilai\'', $blade);
        $this->assertStringContainsString('id="modal-import-nilai-{{ $pertemuan_blok_id }}"', $blade);
        $this->assertStringContainsString('wire:submit="importNilai"', $blade);
        $this->assertStringContainsString('wire:model="importFile"', $blade);
        $this->assertStringContainsString('Template Import', $blade);
    }
}
