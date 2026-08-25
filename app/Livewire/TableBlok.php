<?php

namespace App\Livewire;

use App\Models\Blok;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class TableBlok extends PowerGridComponent
{
    public string $tableName = 'tableBlokTable';

    public int $rowNumber = 0;

    public bool $showFilters = true;

    public ?string $prodiId = null;

    /** @var Collection<int, object>|null */
    private static ?Collection $prodiOptions = null;

    /** @var Collection<int, object>|null */
    private static ?Collection $semesterOptions = null;

    public ?string $semesterId = null;

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

        return Blok::query()
            ->with(['prodi', 'semester'])
            ->when($this->prodiId, fn (Builder $query) => $query->where('prodi_id', $this->prodiId))
            ->when($this->semesterId, fn (Builder $query) => $query->where('semester_id', $this->semesterId));
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
            ->add('semester_nama', fn ($row) => $row->semester ? ucfirst($row->semester->nama).' '.$row->semester->tahun : '-')
            ->add('periode', fn ($row) => ($row->tanggal_mulai?->format('d/m/Y') ?: '-').' - '.($row->tanggal_selesai?->format('d/m/Y') ?: '-'));
    }

    public function columns(): array
    {
        return [
            Column::make('No', 'no'),
            Column::make('Kode', 'kode')->searchable()->sortable(),
            Column::make('Nama Blok', 'nama')->searchable()->sortable(),
            Column::make('Prodi', 'prodi_nama'),
            Column::make('Semester', 'semester_nama'),
            Column::make('Periode', 'periode'),
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
            Filter::select('prodi_id', 'prodi_id')
                ->dataSource(self::$prodiOptions ??= Prodi::orderBy('nama')->get(['id_prodi', 'nama']))
                ->optionValue('id_prodi')
                ->optionLabel('nama'),
            Filter::select('semester_id', 'semester_id')
                ->dataSource(self::$semesterOptions ??= Semester::orderByDesc('tahun')->get(['id_semester', 'kode']))
                ->optionValue('id_semester')
                ->optionLabel('kode'),
        ];
    }

    public function confirmDeleteBlok(string $id): void
    {
        $this->dispatch('siakad-confirm',
            id: $id,
            confirmEvent: 'delete-blok-confirmed',
            title: 'Hapus blok?',
            text: 'Blok hanya bisa dihapus jika belum dipakai mata kuliah.',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
        );
    }

    #[On('delete-blok-confirmed')]
    public function deleteBlok($id): void
    {
        try {
            $decrypted = Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404);
        }

        $blok = Blok::with(['aturan_kegiatan_blok.materi_blok.materi_rinci_blok'])->findOrFail($decrypted);

        if ($blok->mata_kuliah()->exists()) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Blok tidak dapat dihapus karena sudah dipakai mata kuliah.',
            ]);

            return;
        }

        if ($blok->peserta_blok()->exists() || $blok->kelompok_blok()->exists() || $blok->pertemuan_blok()->exists()) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Blok tidak dapat dihapus karena sudah memiliki peserta, kelompok, atau pertemuan.',
            ]);

            return;
        }

        DB::transaction(function () use ($blok) {
            foreach ($blok->aturan_kegiatan_blok as $aturan) {
                foreach ($aturan->materi_blok as $materi) {
                    $materi->materi_rinci_blok()->delete();
                    $materi->delete();
                }

                $aturan->delete();
            }

            $blok->delete();
        });

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Data berhasil dihapus !',
        ]);
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-blok')
                ->slot('<i class="ri-file-edit-line"></i> Kelola')
                ->class('btn btn-info btn-sm mb-2')
                ->route('blok.add_edit', ['id' => Crypt::encrypt($row->id)])
                ->tooltip('Edit Blok')
                ->attributes(['wire:navigate' => true]),
            Button::add('delete-blok')
                ->slot('<i class="ri-delete-bin-line"></i> Hapus')
                ->class('btn btn-danger btn-sm mb-2')
                ->tooltip('Hapus Blok')
                ->attributes(['wire:click' => "confirmDeleteBlok('".Crypt::encrypt($row->id)."')"]),
        ];
    }
}
