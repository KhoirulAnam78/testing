<?php

namespace App\Livewire;

use App\Models\Semester;
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

final class TableSemester extends PowerGridComponent
{
    public string $tableName = 'tableSemesterTable';

    public string $primaryKey = 'id_semester';

    public string $sortField = 'id_semester';

    public int $rowNumber = 0;

    public bool $showFilters = true;

    public function setUp(): array
    {
        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage(10)->showRecordCount('min'),
        ];
    }

    public function datasource(): ?Builder
    {
        $this->rowNumber = 0;

        return Semester::query();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id_semester')
            ->add('no', function () {
                $page = $this->paginators['page'] ?? 1;
                $footer = $this->setUp['footer'];
                $perPage = is_array($footer) && array_key_exists('perPage', $footer) ? $footer['perPage'] : $footer->perPage;

                return ($page - 1) * $perPage + (++$this->rowNumber);
            })
            ->add('nama', fn ($row) => ucfirst($row->nama))
            ->add('tahun')
            ->add('kode')
            ->add('tanggal_mulai', fn ($row) => $row->tanggal_mulai?->format('d/m/Y') ?: '-')
            ->add('tanggal_selesai', fn ($row) => $row->tanggal_selesai?->format('d/m/Y') ?: '-')
            ->add('is_aktif', fn ($row) => $row->is_aktif
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Tidak Aktif</span>')
            ->add('created_at')
            ->add('updated_at');
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div class="d-flex justify-content-center align-items-center" style="height: 300px;">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                <div class="ms-3">Memuat data tabel...</div>
            </div>
        HTML;
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'no'),
            Column::make('Nama', 'nama')->searchable()->sortable(),
            Column::make('Tahun', 'tahun')->searchable()->sortable(),
            Column::make('Kode', 'kode')->searchable()->sortable(),
            Column::make('Mulai', 'tanggal_mulai'),
            Column::make('Selesai', 'tanggal_selesai'),
            Column::make('Status', 'is_aktif')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('kode')->operators(['contains', 'contains_not'])->placeholder('Kode'),
            Filter::inputText('tahun')->operators(['contains'])->placeholder('Tahun'),
            Filter::select('nama', 'nama')
                ->dataSource([
                    ['id' => 'ganjil', 'name' => 'Ganjil'],
                    ['id' => 'genap', 'name' => 'Genap'],
                    ['id' => 'pendek', 'name' => 'Pendek'],
                ])
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    public function confirmDeleteSemester(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-semester-confirmed',
            title: 'Hapus semester?',
            text: 'Semester yang sudah dipakai blok tidak dapat dihapus.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-semester-confirmed')]
    public function deleteSemester($id): void
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404);
        }

        Semester::findOrFail($decrypted)->delete();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Data berhasil dihapus !',
        ]);
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-semester')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('semester.add_edit', ['id' => Crypt::encrypt($row->id_semester)])
                ->tooltip('Edit Semester')
                ->attributes(['wire:navigate' => true]),
            Button::add('delete-semester')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Semester')
                ->attributes(['wire:click' => "confirmDeleteSemester('".Crypt::encrypt($row->id_semester)."')"]),
        ];
    }
}
