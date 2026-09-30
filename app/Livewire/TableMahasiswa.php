<?php

namespace App\Livewire;

use App\Models\Mahasiswa;
use App\Support\Akademik\Sync\SinkronisasiMahasiswa;
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
            PowerGrid::footer()->showPerPage(10)->showRecordCount('full'),
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
            })
            ->add('status_sync', fn ($row) => $row->status_sync === Mahasiswa::STATUS_SYNC_SYNCED
                ? '<span class="badge bg-success-subtle text-success">Tersinkron</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Belum sinkron</span>')
            ->add('synced_at_formatted', fn ($row) => $row->synced_at?->format('d-m-Y H:i:s') ?? '-');
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
            Filter::select('status_sync', 'status_sync')
                ->dataSource([
                    ['id' => Mahasiswa::STATUS_SYNC_PENDING, 'name' => 'Belum sinkron'],
                    ['id' => Mahasiswa::STATUS_SYNC_SYNCED, 'name' => 'Tersinkron'],
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
        $id = Crypt::encrypt($row->id_mahasiswa);
        $target = "sinkronkanSatu('{$id}')";

        return [
            Button::add('sync-mahasiswa')
                ->slot(
                    '<span wire:loading.remove wire:target="'.$target.'"><i class="ri-refresh-line"></i> Sync</span>'.
                    '<span wire:loading wire:target="'.$target.'"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Menyinkronkan...</span>'
                )
                ->class('btn btn-primary btn-sm mb-2')
                ->tooltip('Sinkronkan Mahasiswa dari API')
                ->attributes([
                    'wire:click' => $target,
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => $target,
                ])
                ->can(
                    (auth()->user()?->can('mahasiswa:tambah') ?? false)
                    && (auth()->user()?->can('mahasiswa:edit') ?? false)
                ),
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

    public function sinkronkanSatu(string $id, SinkronisasiMahasiswa $sinkronisasi): void
    {
        abort_unless(
            auth()->user()?->can('mahasiswa:tambah') && auth()->user()?->can('mahasiswa:edit'),
            403
        );

        try {
            $mahasiswaId = Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404);
        }

        $mahasiswa = Mahasiswa::query()->findOrFail($mahasiswaId);

        try {
            $hasil = $sinkronisasi->handleSatu($mahasiswa->nim);
            $this->dispatch('mahasiswa-disinkronkan');
            $this->dispatch('notify', message: ['status' => 'success', 'message' => $hasil['pesan']]);
        } catch (DomainException $e) {
            $this->simpanHasilSinkronisasiGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilSinkronisasiGagal('Sinkronisasi mahasiswa gagal. Periksa koneksi, data lokal, dan format API.');
        }
    }

    private function simpanHasilSinkronisasiGagal(string $pesan): void
    {
        Cache::put(SinkronisasiMahasiswa::cacheKey(), [
            'status' => 'error',
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => 0,
            'unik' => 0,
            'dibuat' => 0,
            'diubah' => 0,
            'dipulihkan' => 0,
            'tetap' => 0,
            'dilewati' => 0,
            'foto_disimpan' => 0,
            'foto_tidak_tersedia' => 0,
            'foto_dipertahankan' => 0,
            'foto_gagal' => 0,
            'rincian' => [],
            'pesan' => $pesan,
        ], now()->addDays(30));
        $this->dispatch('mahasiswa-disinkronkan');
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }

    #[On('mahasiswa-disinkronkan')]
    public function refreshTable(): void {}
}
