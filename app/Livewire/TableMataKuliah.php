<?php

namespace App\Livewire;

use App\Models\MataKuliah;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class TableMataKuliah extends PowerGridComponent
{
    use WithExport {
        prepareToExport as powerGridPrepareToExport;
    }

    public string $tableName = 'tableMataKuliahTable';

    public int $rowNumber = 0;

    public bool $showFilters = true;

    public function setUp(): array
    {
        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::exportable('data-mata-kuliah')
                ->type('xlsx', 'csv')
                ->stripTags(true),
            PowerGrid::footer()->showPerPage(10)->showRecordCount('min'),
        ];
    }

    public function datasource(): ?Builder
    {
        $this->rowNumber = 0;

        return MataKuliah::query()->with(['prodi', 'blok']);
    }

    public function prepareToExport(bool $selected = false): EloquentCollection|Collection
    {
        $this->rowNumber = 0;

        return $this->powerGridPrepareToExport($selected);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('no', function () {
                $page = $this->paginators['page'] ?? 1;
                $footer = $this->setUp['footer'];
                $perPage = is_array($footer) && array_key_exists('perPage', $footer) ? $footer['perPage'] : $footer->perPage;

                return ($page - 1) * $perPage + (++$this->rowNumber);
            })
            ->add('kode')
            ->add('nama')
            ->add('prodi_nama', fn ($row) => $row->prodi?->nama ?: '-')
            ->add('blok_nama', fn ($row) => $row->blok ? $row->blok->kode.' - '.$row->blok->nama : '-')
            ->add('sks')
            ->add('status', fn ($row) => $row->status === 'aktif'
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-danger">Nonaktif</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'no'),
            Column::make('Kode', 'kode')->searchable()->sortable(),
            Column::make('Nama', 'nama')->searchable()->sortable(),
            Column::make('Prodi', 'prodi_nama'),
            Column::make('Blok', 'blok_nama'),
            Column::make('SKS', 'sks')->sortable(),
            Column::make('Status', 'status')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function placeholder()
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

    public function filters(): array
    {
        return [
            Filter::inputText('kode')->operators(['contains', 'contains_not'])->placeholder('Kode'),
            Filter::inputText('nama')->operators(['contains', 'contains_not'])->placeholder('Nama'),
            Filter::select('status', 'status')
                ->dataSource([
                    ['id' => 'aktif', 'name' => 'Aktif'],
                    ['id' => 'nonaktif', 'name' => 'Nonaktif'],
                ])
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    public function confirmDeleteMataKuliah(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-mata-kuliah-confirmed',
            title: 'Hapus mata kuliah?',
            text: 'Mata kuliah yang sudah dipakai kelas nantinya tidak dapat dihapus.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-mata-kuliah-confirmed')]
    public function deleteMataKuliah($id): void
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404);
        }

        MataKuliah::findOrFail($decrypted)->delete();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Data berhasil dihapus !',
        ]);
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-mata-kuliah')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('mata-kuliah.add_edit', ['id' => Crypt::encrypt($row->id)])
                ->tooltip('Edit Mata Kuliah')
                ->attributes(['wire:navigate' => true]),
            Button::add('delete-mata-kuliah')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Mata Kuliah')
                ->attributes(['wire:click' => "confirmDeleteMataKuliah('".Crypt::encrypt($row->id)."')"]),
        ];
    }
}
