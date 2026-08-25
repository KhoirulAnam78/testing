<?php

namespace App\Livewire;

use App\Models\Mahasiswa;
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

final class TableMahasiswa extends PowerGridComponent
{
    use WithExport {
        prepareToExport as powerGridPrepareToExport;
    }

    public string $tableName = 'tableMahasiswaTable';

    public string $primaryKey = 'id_mahasiswa';

    public string $sortField = 'id_mahasiswa';

    public int $rowNumber = 0;

    public bool $showFilters = true;

    public function setUp(): array
    {
        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::exportable('data-mahasiswa')
                ->type('xlsx', 'csv')
                ->stripTags(true),
            PowerGrid::footer()->showPerPage(10)->showRecordCount('min'),
        ];
    }

    public function datasource(): ?Builder
    {
        $this->rowNumber = 0;

        return Mahasiswa::query()->with('prodi');
    }

    public function prepareToExport(bool $selected = false): EloquentCollection|Collection
    {
        $this->rowNumber = 0;

        return $this->powerGridPrepareToExport($selected);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id_mahasiswa')
            ->add('no', function () {
                $page = $this->paginators['page'] ?? 1;
                $footer = $this->setUp['footer'];
                $perPage = is_array($footer) && array_key_exists('perPage', $footer) ? $footer['perPage'] : $footer->perPage;

                return ($page - 1) * $perPage + (++$this->rowNumber);
            })
            ->add('nim')
            ->add('nama')
            ->add('prodi_nama', fn ($row) => $row->prodi?->nama ?: '-')
            ->add('angkatan')
            ->add('status', fn ($row) => match ($row->status) {
                'aktif' => '<span class="badge bg-success">Aktif</span>',
                'cuti' => '<span class="badge bg-warning">Cuti</span>',
                'lulus' => '<span class="badge bg-info">Lulus</span>',
                default => '<span class="badge bg-danger">Nonaktif</span>',
            });
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'no'),
            Column::make('NIM', 'nim')->searchable()->sortable(),
            Column::make('Nama', 'nama')->searchable()->sortable(),
            Column::make('Prodi', 'prodi_nama'),
            Column::make('Angkatan', 'angkatan')->searchable()->sortable(),
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
            Filter::inputText('nim')->operators(['contains', 'contains_not'])->placeholder('NIM'),
            Filter::inputText('nama')->operators(['contains', 'contains_not'])->placeholder('Nama'),
            Filter::inputText('angkatan')->operators(['contains'])->placeholder('Angkatan'),
            Filter::select('status', 'status')
                ->dataSource([
                    ['id' => 'aktif', 'name' => 'Aktif'],
                    ['id' => 'nonaktif', 'name' => 'Nonaktif'],
                    ['id' => 'lulus', 'name' => 'Lulus'],
                    ['id' => 'cuti', 'name' => 'Cuti'],
                ])
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    public function confirmDeleteMahasiswa(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-mahasiswa-confirmed',
            title: 'Hapus mahasiswa?',
            text: 'Mahasiswa yang sudah dipakai data akademik tidak dapat dihapus.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-mahasiswa-confirmed')]
    public function deleteMahasiswa($id): void
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404);
        }

        Mahasiswa::findOrFail($decrypted)->delete();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Data berhasil dihapus !',
        ]);
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-mahasiswa')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('mahasiswa.add_edit', ['id' => Crypt::encrypt($row->id_mahasiswa)])
                ->tooltip('Edit Mahasiswa')
                ->attributes(['wire:navigate' => true]),
            Button::add('delete-mahasiswa')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Mahasiswa')
                ->attributes(['wire:click' => "confirmDeleteMahasiswa('".Crypt::encrypt($row->id_mahasiswa)."')"]),
        ];
    }
}
