<?php

namespace App\Livewire;

use App\Models\Dosen;
use App\Support\Akademik\Sync\SinkronisasiDosen;
use DomainException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use Throwable;

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
            PowerGrid::footer()->showPerPage(10)->showRecordCount('full'),
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
                : '<span class="badge bg-danger">Nonaktif</span>')
            ->add('status_sync', fn ($row) => $row->status_sync === Dosen::STATUS_SYNC_SYNCED
                ? '<span class="badge bg-success-subtle text-success">Tersinkron</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Belum sinkron</span>')
            ->add('synced_at_formatted', fn ($row) => $row->synced_at?->format('d-m-Y H:i:s') ?? '-');
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
            Column::make('Status Sync', 'status_sync')->sortable(),
            Column::make('Synced At', 'synced_at_formatted'),
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
            Filter::select('status_sync', 'status_sync')
                ->dataSource([
                    ['id' => Dosen::STATUS_SYNC_PENDING, 'name' => 'Belum sinkron'],
                    ['id' => Dosen::STATUS_SYNC_SYNCED, 'name' => 'Tersinkron'],
                ])
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    public function confirmDeleteDosen(string $id): void
    {
        abort_unless(auth()->user()?->can('dosen:hapus'), 403);

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
        abort_unless(auth()->user()?->can('dosen:hapus'), 403);

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
        $id = Crypt::encrypt($row->id_dosen);
        $target = "sinkronkanSatu('{$id}')";

        return [
            Button::add('sync-dosen')
                ->slot(
                    '<span wire:loading.remove wire:target="'.$target.'"><i class="ri-refresh-line"></i> Sync</span>'.
                    '<span wire:loading wire:target="'.$target.'"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Menyinkronkan...</span>'
                )
                ->class('btn btn-primary btn-sm mb-2')
                ->tooltip('Sinkronkan Dosen dari API')
                ->attributes([
                    'wire:click' => $target,
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => $target,
                ])
                ->can(
                    (auth()->user()?->can('dosen:tambah') ?? false)
                    && (auth()->user()?->can('dosen:edit') ?? false)
                ),
            Button::add('edit-dosen')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('dosen.add_edit', ['id' => Crypt::encrypt($row->id_dosen)])
                ->tooltip('Edit Dosen')
                ->attributes(['wire:navigate' => true])
                ->can(auth()->user()?->can('dosen:edit') ?? false),
            Button::add('delete-dosen')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Dosen')
                ->attributes(['wire:click' => "confirmDeleteDosen('".Crypt::encrypt($row->id_dosen)."')"])
                ->can(auth()->user()?->can('dosen:hapus') ?? false),
        ];
    }

    public function sinkronkanSatu(string $id, SinkronisasiDosen $sinkronisasi): void
    {
        abort_unless(
            auth()->user()?->can('dosen:tambah') && auth()->user()?->can('dosen:edit'),
            403
        );

        try {
            $dosenId = Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404);
        }

        $dosen = Dosen::query()->findOrFail($dosenId);

        try {
            $hasil = $sinkronisasi->handleSatu($dosen);
            $this->dispatch('dosen-disinkronkan');
            $this->dispatch('notify', message: ['status' => 'success', 'message' => $hasil['pesan']]);
        } catch (DomainException $e) {
            $this->simpanHasilSinkronisasiGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilSinkronisasiGagal('Sinkronisasi dosen gagal. Periksa koneksi dan format data API.');
        }
    }

    private function simpanHasilSinkronisasiGagal(string $pesan): void
    {
        Cache::put(SinkronisasiDosen::cacheKey(), [
            'status' => 'error',
            'prodi' => null,
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => 0,
            'unik' => 0,
            'dibuat' => 0,
            'diubah' => 0,
            'dipulihkan' => 0,
            'tetap' => 0,
            'dilewati' => 0,
            'rincian' => [],
            'pesan' => $pesan,
        ], now()->addDays(30));
        $this->dispatch('dosen-disinkronkan');
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }

    #[On('dosen-disinkronkan')]
    public function refreshTable(): void {}
}
