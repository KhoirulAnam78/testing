<?php

namespace App\Livewire;

use App\Models\MataKuliah;
use App\Support\Akademik\Sync\SinkronisasiMataKuliah;
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
            PowerGrid::footer()->showPerPage(10)->showRecordCount('full'),
        ];
    }

    public function datasource(): ?Builder
    {
        $this->rowNumber = 0;

        return MataKuliah::query()->with('prodi');
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
            ->add('sks')
            ->add('status', fn ($row) => $row->status === 'aktif'
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-danger">Nonaktif</span>')
            ->add('status_sync', fn ($row) => $row->status_sync === MataKuliah::STATUS_SYNC_SYNCED
                ? '<span class="badge bg-success-subtle text-success">Tersinkron</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Belum sinkron</span>')
            ->add('synced_at_formatted', fn ($row) => $row->synced_at?->format('d-m-Y H:i:s') ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'no'),
            Column::make('Kode', 'kode')->searchable()->sortable(),
            Column::make('Nama', 'nama')->searchable()->sortable(),
            Column::make('Prodi', 'prodi_nama'),
            Column::make('SKS', 'sks')->sortable(),
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
            Filter::inputText('kode')->operators(['contains', 'contains_not'])->placeholder('Kode'),
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
                    ['id' => MataKuliah::STATUS_SYNC_PENDING, 'name' => 'Belum sinkron'],
                    ['id' => MataKuliah::STATUS_SYNC_SYNCED, 'name' => 'Tersinkron'],
                ])
                ->optionValue('id')
                ->optionLabel('name'),
        ];
    }

    public function confirmDeleteMataKuliah(string $id): void
    {
        abort_unless(auth()->user()?->can('mata-kuliah:hapus'), 403);

        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-mata-kuliah-confirmed',
            title: 'Hapus mata kuliah?',
            text: 'Mata kuliah tidak dapat dihapus selama masih dipakai blok.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-mata-kuliah-confirmed')]
    public function deleteMataKuliah($id): void
    {
        abort_unless(auth()->user()?->can('mata-kuliah:hapus'), 403);

        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404);
        }

        $mataKuliah = MataKuliah::findOrFail($decrypted);

        if ($mataKuliah->blok()->exists()) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Mata kuliah tidak dapat dihapus karena masih dipakai blok.',
            ]);

            return;
        }

        $mataKuliah->delete();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Data berhasil dihapus !',
        ]);
    }

    public function actions($row): array
    {
        $id = Crypt::encrypt($row->id);
        $target = "sinkronkanSatu('{$id}')";

        return [
            Button::add('sinkronkan-mata-kuliah')
                ->slot(
                    '<span wire:loading.remove wire:target="'.$target.'"><i class="ri-refresh-line"></i> Sync</span>'.
                    '<span wire:loading wire:target="'.$target.'"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Menyinkronkan...</span>'
                )
                ->class('btn btn-primary btn-sm mb-2')
                ->tooltip('Sinkronkan Mata Kuliah dari API')
                ->attributes([
                    'wire:click' => $target,
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => $target,
                ])
                ->can(
                    (auth()->user()?->can('mata-kuliah:tambah') ?? false)
                    && (auth()->user()?->can('mata-kuliah:edit') ?? false)
                ),
            Button::add('edit-mata-kuliah')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('mata-kuliah.add_edit', ['id' => Crypt::encrypt($row->id)])
                ->tooltip('Edit Mata Kuliah')
                ->attributes(['wire:navigate' => true])
                ->can(auth()->user()?->can('mata-kuliah:edit') ?? false),
            Button::add('delete-mata-kuliah')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Mata Kuliah')
                ->attributes(['wire:click' => "confirmDeleteMataKuliah('".Crypt::encrypt($row->id)."')"])
                ->can(auth()->user()?->can('mata-kuliah:hapus') ?? false),
        ];
    }

    public function sinkronkanSatu(string $id, SinkronisasiMataKuliah $sinkronisasi): void
    {
        abort_unless(
            auth()->user()?->can('mata-kuliah:tambah') && auth()->user()?->can('mata-kuliah:edit'),
            403
        );

        try {
            $mataKuliahId = Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404);
        }

        $mataKuliah = MataKuliah::query()->with('prodi')->findOrFail($mataKuliahId);

        try {
            $prodi = $mataKuliah->prodi ?? throw new DomainException('Program studi mata kuliah tidak ditemukan.');
            $hasil = $sinkronisasi->handleSatu($prodi, $mataKuliah->kode);
            $this->dispatch('mata-kuliah-disinkronkan');
            $this->dispatch('notify', message: ['status' => 'success', 'message' => $hasil['pesan']]);
        } catch (DomainException $e) {
            $this->simpanHasilSinkronisasiGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilSinkronisasiGagal('Sinkronisasi mata kuliah gagal. Periksa koneksi dan format data API.');
        }
    }

    private function simpanHasilSinkronisasiGagal(string $pesan): void
    {
        Cache::put(SinkronisasiMataKuliah::cacheKey(), [
            'status' => 'error',
            'prodi' => null,
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => 0,
            'unik' => 0,
            'dibuat' => 0,
            'diubah' => 0,
            'dipulihkan' => 0,
            'tetap' => 0,
            'pesan' => $pesan,
        ], now()->addDays(30));
        $this->dispatch('mata-kuliah-disinkronkan');
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }

    #[On('mata-kuliah-disinkronkan')]
    public function refreshTable(): void {}
}
