<?php

use App\Livewire\TableKurikulumMahasiswa;
use App\Models\Kurikulum;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $bulk_prodi_id = '';

    public $bulk_angkatan = '';

    public $bulk_kurikulum_id = '';

    public string $bulk_mode = 'kosong';

    #[Locked]
    public bool $konfirmasi_bulk = false;

    public array $prodi = [];

    public array $angkatan = [];

    public function mount(): void
    {
        $this->pastikanBerhak();

        $this->prodi = Prodi::query()
            ->orderBy('nama')
            ->get(['id_prodi', 'kode', 'nama'])
            ->map(fn (Prodi $item) => [
                'id' => $item->id_prodi,
                'label' => $item->kode.' - '.$item->nama,
            ])->all();

        $this->angkatan = Mahasiswa::query()
            ->select('angkatan')
            ->whereNotNull('angkatan')
            ->distinct()
            ->orderByDesc('angkatan')
            ->pluck('angkatan')
            ->all();

    }

    public function updated($property): void
    {
        if ($property === 'bulk_prodi_id') {
            $this->bulk_kurikulum_id = '';
        }

        if (str_starts_with($property, 'bulk_')) {
            $this->konfirmasi_bulk = false;
        }
    }

    #[Computed]
    public function kurikulumBulk()
    {
        return Kurikulum::query()
            ->where('prodi_id', $this->bulk_prodi_id)
            ->where('status', 'aktif')
            ->orderByDesc('tahun_berlaku')
            ->orderBy('nama')
            ->get(['id_kurikulum', 'kode', 'nama', 'tahun_berlaku']);
    }

    #[Computed]
    public function jumlahSasaranBulk(): int
    {
        if (! $this->bulk_prodi_id || ! $this->bulk_angkatan || ! in_array($this->bulk_mode, ['kosong', 'semua'], true)) {
            return 0;
        }

        return $this->querySasaranBulk()->count();
    }

    public function siapkanBulk(): void
    {
        $this->pastikanBerhak();
        $this->validasiBulk();

        if ($this->jumlahSasaranBulk === 0) {
            $this->addError('bulk_target', 'Tidak ada mahasiswa yang sesuai dengan pilihan tersebut.');

            return;
        }

        $this->konfirmasi_bulk = true;
    }

    public function terapkanBulk(): void
    {
        $this->pastikanBerhak();
        abort_unless($this->konfirmasi_bulk, 422);
        $this->validasiBulk();

        $jumlah = DB::transaction(function () {
            $kurikulumValid = Kurikulum::query()
                ->lockForUpdate()
                ->whereKey($this->bulk_kurikulum_id)
                ->where('prodi_id', $this->bulk_prodi_id)
                ->where('status', 'aktif')
                ->exists();

            if (! $kurikulumValid) {
                throw ValidationException::withMessages([
                    'bulk_kurikulum_id' => 'Kurikulum sudah tidak aktif atau tidak sesuai dengan program studi.',
                ]);
            }

            return $this->querySasaranBulk()->update([
                'kurikulum_id' => $this->bulk_kurikulum_id,
                'updated_at' => now(),
            ]);
        });

        $this->konfirmasi_bulk = false;
        unset($this->jumlahSasaranBulk);
        $this->dispatch('kurikulum-mahasiswa-diperbarui')->to(TableKurikulumMahasiswa::class);

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => "Kurikulum berhasil diterapkan kepada {$jumlah} mahasiswa.",
        ]);
    }

    public function tutupKonfirmasiBulk(): void
    {
        $this->konfirmasi_bulk = false;
    }

    private function validasiBulk(): void
    {
        $this->validate([
            'bulk_prodi_id' => ['required', 'exists:prodi,id_prodi'],
            'bulk_angkatan' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'bulk_kurikulum_id' => [
                'required',
                Rule::exists('kurikulum', 'id_kurikulum')->where(fn ($query) => $query
                    ->where('prodi_id', $this->bulk_prodi_id)
                    ->where('status', 'aktif')
                    ->whereNull('deleted_at')),
            ],
            'bulk_mode' => ['required', Rule::in(['kosong', 'semua'])],
        ], [
            'bulk_prodi_id.required' => 'Program studi wajib dipilih.',
            'bulk_angkatan.required' => 'Angkatan wajib dipilih.',
            'bulk_kurikulum_id.required' => 'Kurikulum wajib dipilih.',
            'bulk_kurikulum_id.exists' => 'Kurikulum harus aktif dan berasal dari program studi yang dipilih.',
            'bulk_mode.required' => 'Mode penerapan wajib dipilih.',
        ]);
    }

    private function querySasaranBulk(): Builder
    {
        return Mahasiswa::query()
            ->where('prodi_id', $this->bulk_prodi_id)
            ->where('angkatan', $this->bulk_angkatan)
            ->when($this->bulk_mode === 'kosong', fn (Builder $query) => $query->whereNull('kurikulum_id'));
    }

    private function pastikanBerhak(): void
    {
        abort_unless(auth()->user()?->can('kurikulum-mahasiswa:'), 403);
    }
}; ?>

<div>
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">Kurikulum Mahasiswa</h4>
        <ol class="breadcrumb m-0">
            <li class="breadcrumb-item">Akademik</li>
            <li class="breadcrumb-item active">Kurikulum Mahasiswa</li>
        </ol>
    </div>

    <livewire:alert />

    <div class="card">
        <div class="card-header">
            <h5 class="mb-1">Terapkan Per Angkatan</h5>
            <div class="text-muted small">Pilih hanya yang kosong agar pengaturan individual tetap dipertahankan.</div>
        </div>
        <form wire:submit="siapkanBulk" class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="bulk-prodi">Program Studi</label>
                    <select id="bulk-prodi" class="form-select" wire:model.live="bulk_prodi_id">
                        <option value="">Pilih prodi</option>
                        @foreach ($prodi as $item)
                            <option value="{{ $item['id'] }}">{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    @error('bulk_prodi_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="bulk-angkatan">Angkatan</label>
                    <select id="bulk-angkatan" class="form-select" wire:model.live="bulk_angkatan">
                        <option value="">Pilih angkatan</option>
                        @foreach ($angkatan as $tahun)
                            <option value="{{ $tahun }}">{{ $tahun }}</option>
                        @endforeach
                    </select>
                    @error('bulk_angkatan') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="bulk-kurikulum">Kurikulum Aktif</label>
                    <select id="bulk-kurikulum" class="form-select" wire:model="bulk_kurikulum_id" @disabled(! $bulk_prodi_id)>
                        <option value="">Pilih kurikulum</option>
                        @foreach ($this->kurikulumBulk as $item)
                            <option value="{{ $item->id_kurikulum }}">{{ $item->kode }} - {{ $item->nama }} ({{ $item->tahun_berlaku }})</option>
                        @endforeach
                    </select>
                    @error('bulk_kurikulum_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="bulk-mode">Mode Penerapan</label>
                    <select id="bulk-mode" class="form-select" wire:model.live="bulk_mode">
                        <option value="kosong">Hanya yang belum memiliki kurikulum</option>
                        <option value="semua">Timpa seluruh mahasiswa</option>
                    </select>
                    @error('bulk_mode') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="siapkanBulk">
                        <i class="ri-check-double-line"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 mt-3">
                <span class="badge bg-info-subtle text-info fs-6">{{ $this->jumlahSasaranBulk }} mahasiswa</span>
                <span class="text-muted small">akan diperbarui berdasarkan pilihan saat ini.</span>
            </div>
            @error('bulk_target') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-1">Pengaturan Per Mahasiswa</h5>
            <div class="text-muted small">Filter mahasiswa lalu atur atau kosongkan kurikulumnya.</div>
        </div>
        <div class="card-body">
            <livewire:table-kurikulum-mahasiswa />
        </div>
    </div>

    @if ($konfirmasi_bulk)
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="konfirmasi-bulk-title">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="konfirmasi-bulk-title">Konfirmasi Penetapan Kurikulum</h5>
                        <button type="button" class="btn-close" wire:click="tutupKonfirmasiBulk" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Kurikulum akan diterapkan kepada <strong>{{ $this->jumlahSasaranBulk }} mahasiswa</strong>.</p>
                        @if ($bulk_mode === 'semua')
                            <div class="alert alert-warning mb-0">
                                Mode <strong>Timpa seluruh mahasiswa</strong> akan mengganti pengaturan individual yang sudah ada.
                            </div>
                        @else
                            <div class="alert alert-info mb-0">Mahasiswa yang sudah memiliki kurikulum tidak akan diubah.</div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" wire:click="tutupKonfirmasiBulk">Batal</button>
                        <button type="button" class="btn btn-primary" wire:click="terapkanBulk" wire:loading.attr="disabled" wire:target="terapkanBulk">
                            Terapkan Kurikulum
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

</div>