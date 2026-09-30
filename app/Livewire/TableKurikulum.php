<?php

namespace App\Livewire;

use App\Models\Kurikulum;
use App\Support\Akademik\Sync\SinkronisasiKurikulum;
use DomainException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Throwable;

final class TableKurikulum extends PowerGridComponent
{
    public string $tableName = 'tableKurikulumTable';

    public string $primaryKey = 'id_kurikulum';

    public string $sortField = 'id_kurikulum';

    public function setUp(): array
    {
        return [PowerGrid::header()->showSearchInput(), PowerGrid::footer()->showPerPage(10)->showRecordCount('full')];
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
            })
            ->add('status_sync_label', fn ($row) => $row->status_sync === Kurikulum::STATUS_SYNC_SYNCED
                ? '<span class="badge bg-success-subtle text-success">Tersinkron</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Belum sinkron</span>')
            ->add('synced_at_formatted', fn ($row) => $row->synced_at?->format('d-m-Y H:i:s') ?? '-');
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
            Column::make('Status Sync', 'status_sync_label', 'status_sync')->sortable(),
            Column::make('Sync Terakhir', 'synced_at_formatted', 'synced_at')->sortable(),
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
            Filter::select('status_sync', 'status_sync')->dataSource([
                ['id' => Kurikulum::STATUS_SYNC_PENDING, 'name' => 'Belum sinkron'],
                ['id' => Kurikulum::STATUS_SYNC_SYNCED, 'name' => 'Tersinkron'],
            ])->optionValue('id')->optionLabel('name'),
        ];
    }

    public function actions($row): array
    {
        $id = Crypt::encrypt($row->id_kurikulum);
        $target = "sinkronkanSatu('{$id}')";

        return [
            Button::add('sync-kurikulum')->slot(
                '<span wire:loading.remove wire:target="'.$target.'"><i class="ri-refresh-line"></i> Sync</span>'.
                '<span wire:loading wire:target="'.$target.'"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Menyinkronkan...</span>'
            )->class('btn btn-primary btn-sm mb-2')->tooltip('Sinkronkan Kurikulum dari API')->attributes([
                'wire:click' => $target,
                'wire:loading.attr' => 'disabled',
                'wire:target' => $target,
            ])->can(auth()->user()?->can('kurikulum:') ?? false),
            Button::add('edit-kurikulum')->slot('<i class="ri-file-edit-line"></i> Kelola')->class('btn btn-info btn-sm mb-2')
                ->route('kurikulum.add_edit', ['id' => $id])->attributes(['wire:navigate' => true]),
            Button::add('delete-kurikulum')->slot('<i class="ri-delete-bin-line"></i> Hapus')->class('btn btn-danger btn-sm mb-2')
                ->attributes(['wire:click' => "confirmDelete('{$id}')"]),
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

    public function sinkronkanSatu(string $id, SinkronisasiKurikulum $sinkronisasi): void
    {
        abort_unless(auth()->user()?->can('kurikulum:'), 403);

        try {
            $kurikulumId = Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404);
        }

        $kurikulum = Kurikulum::query()->with('prodi')->findOrFail($kurikulumId);

        try {
            $hasil = $sinkronisasi->handleSatu($kurikulum);
            $this->dispatch('kurikulum-disinkronkan');
            $this->dispatch('notify', message: ['status' => 'success', 'message' => $hasil['pesan']]);
        } catch (DomainException $e) {
            $this->simpanHasilSinkronisasiGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilSinkronisasiGagal('Sinkronisasi kurikulum gagal. Periksa koneksi, data lokal, dan format API.');
        }
    }

    private function simpanHasilSinkronisasiGagal(string $pesan): void
    {
        Cache::put(SinkronisasiKurikulum::cacheKey(), [
            'status' => 'error',
            'prodi' => null,
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => 0,
            'unik' => 0,
            'dibuat' => 0,
            'diubah' => 0,
            'direkonsiliasi' => 0,
            'dipulihkan' => 0,
            'tetap' => 0,
            'pivot_dibuat' => 0,
            'pivot_diubah' => 0,
            'pivot_dipulihkan' => 0,
            'pivot_tetap' => 0,
            'pesan' => $pesan,
        ], now()->addDays(30));
        $this->dispatch('kurikulum-disinkronkan');
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }

    #[On('kurikulum-disinkronkan')]
    public function refreshTable(): void {}
}
