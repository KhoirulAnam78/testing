<?php

namespace App\Livewire;

use App\Models\JenisKegiatan;
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

final class TableJenisKegiatan extends PowerGridComponent
{
    public string $tableName = 'tableJenisKegiatanTable';

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

        return JenisKegiatan::query()
            ->withCount([
                'komponen_penilaian as komponen_penilaian_aktif_count' => fn ($query) => $query->aktif(),
            ]);
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
            ->add('jumlah_pertemuan_default')
            ->add('durasi_menit_default')
            ->add('pakai_cbt_label', fn ($row) => $row->pakai_cbt
                ? '<span class="badge bg-primary">Ya</span>'
                : '<span class="badge bg-secondary">Tidak</span>')
            ->add('komponen_penilaian', fn ($row) => $row->komponen_penilaian_aktif_count > 0
                ? '<span class="badge bg-success">Ada ('.$row->komponen_penilaian_aktif_count.' komponen)</span>'
                : '<span class="badge bg-secondary">Tidak ada</span>')
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
            Column::make('Pertemuan Default', 'jumlah_pertemuan_default')->sortable(),
            Column::make('Durasi Default', 'durasi_menit_default')->sortable(),
            Column::make('CBT', 'pakai_cbt_label', 'pakai_cbt')->sortable(),
            Column::make('Komponen Penilaian', 'komponen_penilaian'),
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

    public function confirmDeleteJenisKegiatan(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-jenis-kegiatan-confirmed',
            title: 'Hapus jenis kegiatan?',
            text: 'Jenis kegiatan yang sudah dipakai blok tidak dapat dihapus.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-jenis-kegiatan-confirmed')]
    public function deleteJenisKegiatan($id): void
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404);
        }

        JenisKegiatan::findOrFail($decrypted)->delete();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Data berhasil dihapus !',
        ]);
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-jenis-kegiatan')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('jenis-kegiatan.add_edit', ['id' => Crypt::encrypt($row->id)])
                ->tooltip('Edit Jenis Kegiatan')
                ->attributes(['wire:navigate' => true]),
            Button::add('delete-jenis-kegiatan')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Jenis Kegiatan')
                ->attributes(['wire:click' => "confirmDeleteJenisKegiatan('".Crypt::encrypt($row->id)."')"]),
        ];
    }
}
