<?php

namespace App\Livewire;

use App\Models\Dosen;
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

final class TableDosen extends PowerGridComponent
{
    use WithExport {
        prepareToExport as powerGridPrepareToExport;
    }

    public string $tableName = 'tableDosenTable';

    public string $primaryKey = 'id_dosen';

    public string $sortField = 'id_dosen';

    public int $rowNumber = 0;

    public bool $showFilters = true;

    public function setUp(): array
    {
        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::exportable('data-dosen')
                ->type('xlsx', 'csv')
                ->stripTags(true),
            PowerGrid::footer()->showPerPage(10)->showRecordCount('min'),
        ];
    }

    public function datasource(): ?Builder
    {
        $this->rowNumber = 0;

        return Dosen::query()->with('prodi');
    }

    public function prepareToExport(bool $selected = false): EloquentCollection|Collection
    {
        $this->rowNumber = 0;

        return $this->powerGridPrepareToExport($selected);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id_dosen')
            ->add('no', function () {
                $page = $this->paginators['page'] ?? 1;
                $footer = $this->setUp['footer'];
                $perPage = is_array($footer) && array_key_exists('perPage', $footer) ? $footer['perPage'] : $footer->perPage;

                return ($page - 1) * $perPage + (++$this->rowNumber);
            })
            ->add('nidn', fn ($row) => $row->nidn ?: '-')
            ->add('nip', fn ($row) => $row->nip ?: '-')
            ->add('nama_lengkap', fn ($row) => trim(($row->gelar_depan ? $row->gelar_depan.' ' : '').$row->nama.($row->gelar_belakang ? ', '.$row->gelar_belakang : '')))
            ->add('email', fn ($row) => $row->email ?: '-')
            ->add('prodi_nama', fn ($row) => $row->prodi?->nama ?: '-')
            ->add('status', fn ($row) => $row->status === 'aktif'
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-danger">Nonaktif</span>');
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'no'),
            Column::make('NIDN', 'nidn')->searchable()->sortable(),
            Column::make('NIP', 'nip')->searchable()->sortable(),
            Column::make('Nama', 'nama_lengkap', 'nama')->searchable()->sortable(),
            Column::make('Email', 'email')->searchable()->sortable(),
            Column::make('Prodi', 'prodi_nama'),
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
            Filter::inputText('nidn')->operators(['contains', 'contains_not'])->placeholder('NIDN'),
            Filter::inputText('nip')->operators(['contains', 'contains_not'])->placeholder('NIP'),
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

    public function confirmDeleteDosen(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-dosen-confirmed',
            title: 'Hapus dosen?',
            text: 'Dosen yang sudah dipakai data akademik tidak dapat dihapus.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-dosen-confirmed')]
    public function deleteDosen($id): void
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404);
        }

        Dosen::findOrFail($decrypted)->delete();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Data berhasil dihapus !',
        ]);
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-dosen')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('dosen.add_edit', ['id' => Crypt::encrypt($row->id_dosen)])
                ->tooltip('Edit Dosen')
                ->attributes(['wire:navigate' => true]),
            Button::add('delete-dosen')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Dosen')
                ->attributes(['wire:click' => "confirmDeleteDosen('".Crypt::encrypt($row->id_dosen)."')"]),
        ];
    }
}
