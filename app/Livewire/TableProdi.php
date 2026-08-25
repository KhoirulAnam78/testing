<?php

namespace App\Livewire;

use App\Models\Prodi;
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

final class TableProdi extends PowerGridComponent
{
    public string $tableName = 'tableProdiTable';

    public string $primaryKey = 'id_prodi';

    public string $sortField = 'id_prodi';

    public int $rowNumber = 0;

    public bool $showFilters = true;

    public function setUp(): array
    {
        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage(10)
                ->showRecordCount('min'),
        ];
    }

    public function datasource(): ?Builder
    {
        $this->rowNumber = 0;

        return Prodi::query();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id_prodi')
            ->add('no', function () {
                $page = $this->paginators['page'] ?? 1;
                $footer = $this->setUp['footer'];
                $perPage = is_array($footer) && array_key_exists('perPage', $footer)
                    ? $footer['perPage']
                    : $footer->perPage;

                return ($page - 1) * $perPage + (++$this->rowNumber);
            })
            ->add('kode')
            ->add('nama')
            ->add('jenjang', function ($row) {
                if (! $row->jenjang) {
                    return '-';
                }

                return config('akademik.jenjang_pendidikan', [])[$row->jenjang] ?? e($row->jenjang);
            })
            ->add('status', fn ($row) => $row->status === 'aktif'
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-danger">Nonaktif</span>')
            ->add('created_at')
            ->add('updated_at');
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

    public function columns(): array
    {
        return [
            Column::make('No', 'no'),
            Column::make('Kode', 'kode')
                ->searchable()
                ->sortable(),
            Column::make('Nama Prodi', 'nama')
                ->searchable()
                ->sortable(),
            Column::make('Jenjang', 'jenjang')
                ->searchable()
                ->sortable(),
            Column::make('Status', 'status')
                ->sortable(),
            Column::make('Created at', 'created_at')
                ->sortable(),
            Column::make('Updated at', 'updated_at')
                ->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('kode')->operators(['contains', 'contains_not'])->placeholder('Kode'),
            Filter::inputText('nama')->operators(['contains', 'contains_not'])->placeholder('Nama Prodi'),
            Filter::select('jenjang', 'jenjang')
                ->dataSource(collect(config('akademik.jenjang_pendidikan', []))
                    ->map(fn ($nama, $kode) => ['id' => $kode, 'name' => $nama])
                    ->values()
                    ->all())
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('status', 'status')
                ->dataSource([
                    ['id' => 'aktif', 'name' => 'Aktif'],
                    ['id' => 'nonaktif', 'name' => 'Nonaktif'],
                ])
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    public function confirmDeleteProdi(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-prodi-confirmed',
            title: 'Hapus prodi?',
            text: 'Prodi yang sudah dipakai data akademik tidak dapat dihapus.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-prodi-confirmed')]
    public function deleteProdi($id): void
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404);
        }

        Prodi::findOrFail($decrypted)->delete();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Data berhasil dihapus !',
        ]);
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-prodi')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('prodi.add_edit', ['id' => Crypt::encrypt($row->id_prodi)])
                ->tooltip('Edit Prodi')
                ->attributes([
                    'wire:navigate' => true,
                ]),
            Button::add('delete-prodi')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Prodi')
                ->attributes([
                    'wire:click' => "confirmDeleteProdi('".Crypt::encrypt($row->id_prodi)."')",
                ]),
        ];
    }
}
