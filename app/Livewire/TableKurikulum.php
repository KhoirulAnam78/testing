<?php

namespace App\Livewire;

use App\Models\Kurikulum;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TableKurikulum extends PowerGridComponent
{
    public string $tableName = 'tableKurikulumTable';

    public string $primaryKey = 'id_kurikulum';

    public string $sortField = 'id_kurikulum';

    public function setUp(): array
    {
        return [PowerGrid::header()->showSearchInput(), PowerGrid::footer()->showPerPage(10)->showRecordCount('min')];
    }

    public function datasource(): ?Builder
    {
        return Kurikulum::query()->with(['prodi', 'skala_nilai'])->withCount('mata_kuliah');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id_kurikulum')
            ->add('kode')
            ->add('nama')
            ->add('prodi_nama', fn ($row) => $row->prodi?->nama ?? '-')
            ->add('skala_nilai_nama', fn ($row) => $row->skala_nilai ? $row->skala_nilai->nama.' v'.$row->skala_nilai->versi : '-')
            ->add('tahun_berlaku')
            ->add('mata_kuliah_count')
            ->add('status_label', fn ($row) => match ($row->status) {
                'aktif' => '<span class="badge bg-success">Aktif</span>',
                'draft' => '<span class="badge bg-warning">Draft</span>',
                'arsip' => '<span class="badge bg-dark">Arsip</span>',
                default => '<span class="badge bg-secondary">Nonaktif</span>',
            });
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="d-flex justify-content-center align-items-center" style="height: 300px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="ms-3">Memuat data tabel...</div>
            </div>
        HTML;
    }

    public function columns(): array
    {
        return [
            Column::make('Kode', 'kode')->searchable()->sortable(),
            Column::make('Nama', 'nama')->searchable()->sortable(),
            Column::make('Prodi', 'prodi_nama'),
            Column::make('Tahun', 'tahun_berlaku')->sortable(),
            Column::make('Skala Nilai', 'skala_nilai_nama'),
            Column::make('Mata Kuliah', 'mata_kuliah_count'),
            Column::make('Status', 'status_label', 'status')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('kode')->operators(['contains'])->placeholder('Kode'),
            Filter::select('status', 'status')->dataSource([
                ['id' => 'draft', 'name' => 'Draft'], ['id' => 'aktif', 'name' => 'Aktif'],
                ['id' => 'nonaktif', 'name' => 'Nonaktif'], ['id' => 'arsip', 'name' => 'Arsip'],
            ])->optionValue('id')->optionLabel('name'),
        ];
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-kurikulum')->slot('<i class="ri-file-edit-line"></i> Kelola')->class('btn btn-info btn-sm mb-2')
                ->route('kurikulum.add_edit', ['id' => Crypt::encrypt($row->id_kurikulum)])->attributes(['wire:navigate' => true]),
            Button::add('delete-kurikulum')->slot('<i class="ri-delete-bin-line"></i> Hapus')->class('btn btn-danger btn-sm mb-2')
                ->attributes(['wire:click' => "confirmDelete('".Crypt::encrypt($row->id_kurikulum)."')"]),
        ];
    }

    public function confirmDelete(string $id): void
    {
        $this->dispatch('siakad-confirm', id: $id, confirmEvent: 'delete-kurikulum-confirmed', title: 'Hapus kurikulum?', text: 'Kurikulum akan dinonaktifkan melalui soft delete.', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal');
    }

    #[On('delete-kurikulum-confirmed')]
    public function deleteKurikulum(string $id): void
    {
        try {
            Kurikulum::findOrFail(Crypt::decrypt($id))->delete();
        } catch (DecryptException) {
            abort(404);
        }

        $this->dispatch('notify', message: ['status' => 'success', 'message' => 'Kurikulum berhasil dihapus.']);
    }
}
