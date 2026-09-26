<?php

namespace App\Livewire;

use App\Models\SkalaNilai;
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

final class TableSkalaNilai extends PowerGridComponent
{
    public string $tableName = 'tableSkalaNilaiTable';

    public string $primaryKey = 'id_skala_nilai';

    public string $sortField = 'id_skala_nilai';

    public function setUp(): array
    {
        return [PowerGrid::header()->showSearchInput(), PowerGrid::footer()->showPerPage(10)->showRecordCount('full')];
    }

    public function datasource(): ?Builder
    {
        return SkalaNilai::query()->withCount(['detail', 'kurikulum']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id_skala_nilai')
            ->add('nama')
            ->add('versi_label', fn ($row) => 'v'.$row->versi)
            ->add('detail_count')
            ->add('kurikulum_count')
            ->add('aktif_label', fn ($row) => $row->aktif
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>');
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
            Column::make('Nama', 'nama')->searchable()->sortable(),
            Column::make('Versi', 'versi_label', 'versi')->sortable(),
            Column::make('Jumlah Grade', 'detail_count'),
            Column::make('Dipakai Kurikulum', 'kurikulum_count'),
            Column::make('Status', 'aktif_label', 'aktif')->sortable(),
            Column::action('Aksi'),
        ];
    }

    public function filters(): array
    {
        return [Filter::boolean('aktif')->label('Aktif', 'Nonaktif')];
    }

    public function actions($row): array
    {
        $actions = [
            Button::add('edit-skala-nilai')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('skala-nilai.add_edit', ['id' => Crypt::encrypt($row->id_skala_nilai)])
                ->attributes(['wire:navigate' => true]),
        ];

        if ((int) $row->kurikulum_count === 0) {
            $actions[] = Button::add('delete-skala-nilai')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->attributes(['wire:click' => "confirmDelete('".Crypt::encrypt($row->id_skala_nilai)."')"]);
        }

        return $actions;
    }

    public function confirmDelete(string $id): void
    {
        $this->dispatch('siakad-confirm', id: $id, confirmEvent: 'delete-skala-nilai-confirmed', title: 'Hapus skala nilai?', text: 'Skala yang sudah dipakai kurikulum tidak dapat dihapus.', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal');
    }

    #[On('delete-skala-nilai-confirmed')]
    public function deleteSkalaNilai(string $id): void
    {
        try {
            $skala = SkalaNilai::findOrFail(Crypt::decrypt($id));
        } catch (DecryptException) {
            abort(404);
        }

        if ($skala->kurikulum()->exists()) {
            $this->dispatch('notify', message: ['status' => 'error', 'message' => 'Skala nilai sudah dipakai kurikulum.']);

            return;
        }

        $skala->detail()->delete();
        $skala->delete();
        $this->dispatch('notify', message: ['status' => 'success', 'message' => 'Skala nilai berhasil dihapus.']);
    }
}
