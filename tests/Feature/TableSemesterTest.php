<?php

namespace Tests\Feature;

use App\Livewire\TableSemester;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TableSemesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_ditampilkan_sebagai_label_bukan_angka(): void
    {
        Semester::create([
            'nama' => 'ganjil',
            'tahun' => 2025,
            'kode' => '20251',
            'tanggal_mulai' => '2025-08-01',
            'tanggal_selesai' => '2025-12-31',
            'is_aktif' => true,
        ]);
        Semester::create([
            'nama' => 'genap',
            'tahun' => 2025,
            'kode' => '20252',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-06-30',
            'is_aktif' => false,
        ]);

        Livewire::test(TableSemester::class)
            ->assertSeeHtml('<span class="badge bg-success">Aktif</span>')
            ->assertSeeHtml('<span class="badge bg-secondary">Tidak Aktif</span>');
    }
}
