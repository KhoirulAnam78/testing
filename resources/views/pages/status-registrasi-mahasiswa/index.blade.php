<?php

use App\Livewire\TableStatusRegistrasiMahasiswa;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StatusRegistrasiMahasiswa;
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
    public $bulk_semester_id = '';

    public $bulk_prodi_id = '';

    public $bulk_angkatan = '';

    public string $bulk_status = 'aktif';

    public string $bulk_mode = 'kosong';

    #[Locked]
    public bool $konfirmasi_bulk = false;

    public array $prodi = [];

    public array $angkatan = [];

    public function mount(): void
    {
        $this->pastikanBerhak();
        $this->bulk_semester_id = Semester::where('is_aktif', true)->value('id_semester') ?: '';
        $this->prodi = Prodi::query()
            ->orderBy('nama')
            ->get(['id_prodi', 'kode', 'nama'])
            ->map(fn (Prodi $item) => [
                'id' => $item->id_prodi,
                'label' => $item->kode.' - '.$item->nama,
            ])->all();
        $this->angkatan = Mahasiswa::query()
            ->select('angkatan')
            ->distinct()
            ->orderByDesc('angkatan')
            ->pluck('angkatan')
            ->all();
    }

    public function updated($property): void
    {
        if (str_starts_with($property, 'bulk_')) {
            $this->konfirmasi_bulk = false;
            unset($this->semesterBulk, $this->jumlahSasaranBulk, $this->jumlahDilewatiLulus);
        }
    }

    #[Computed]
    public function semesterBulk(): ?Semester
    {
        return $this->bulk_semester_id
            ? Semester::find($this->bulk_semester_id)
            : null;
    }

    #[Computed]
    public function jumlahSasaranBulk(): int
    {
        if (! $this->inputBulkTerisi() || ! $this->semesterBulk) {
            return 0;
        }

        return $this->querySasaranBulk()->count();
    }

    #[Computed]
    public function jumlahDilewatiLulus(): int
    {
        if (! $this->bulk_prodi_id || ! $this->bulk_angkatan || ! $this->semesterBulk?->is_aktif) {
            return 0;
        }

        return Mahasiswa::query()
            ->where('prodi_id', $this->bulk_prodi_id)
            ->where('angkatan', $this->bulk_angkatan)
            ->where('status', 'lulus')
            ->count();
    }

    public function siapkanBulk(): void
    {
        $this->pastikanBerhak();
        $this->validasiBulk();

        if ($this->jumlahSasaranBulk === 0) {
            $this->addError('bulk_target', 'Tidak ada mahasiswa yang dapat diperbarui dengan pilihan tersebut.');

            return;
        }

        $this->konfirmasi_bulk = true;
    }

    public function terapkanBulk(): void
    {
        $this->pastikanBerhak();
        abort_unless($this->konfirmasi_bulk, 422);
        $this->validasiBulk();

        $jumlah = DB::transaction(function (): int {
            $semester = Semester::query()->lockForUpdate()->find($this->bulk_semester_id);

            if (! $semester) {
                throw ValidationException::withMessages([
                    'bulk_semester_id' => 'Semester tidak ditemukan.',
                ]);
            }

            $mahasiswaIds = $this->querySasaranBulk($semester)
                ->lockForUpdate()
                ->orderBy('id_mahasiswa')
                ->pluck('id_mahasiswa');

            $waktu = now();
            $rows = $mahasiswaIds->map(fn ($id) => [
                'mahasiswa_id' => $id,
                'semester_id' => $semester->id_semester,
                'status' => $this->bulk_status,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ])->all();

            if ($rows === []) {
                return 0;
            }

            if ($this->bulk_mode === 'kosong') {
                DB::table('status_registrasi_mahasiswa')->insertOrIgnore($rows);
            } else {
                DB::table('status_registrasi_mahasiswa')->upsert(
                    $rows,
                    ['mahasiswa_id', 'semester_id'],
                    ['status', 'updated_at']
                );
            }

            return count($rows);
        });

        $this->konfirmasi_bulk = false;
        unset($this->jumlahSasaranBulk, $this->jumlahDilewatiLulus);
        $this->dispatch('status-registrasi-mahasiswa-diperbarui')->to(TableStatusRegistrasiMahasiswa::class);
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => "Status registrasi berhasil diterapkan kepada {$jumlah} mahasiswa.",
        ]);
    }

    public function tutupKonfirmasiBulk(): void
    {
        $this->konfirmasi_bulk = false;
    }

    private function validasiBulk(): void
    {
        $this->validate([
            'bulk_semester_id' => ['required', Rule::exists('semester', 'id_semester')->whereNull('deleted_at')],
            'bulk_prodi_id' => ['required', 'exists:prodi,id_prodi'],
            'bulk_angkatan' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'bulk_status' => ['required', Rule::in(StatusRegistrasiMahasiswa::STATUS)],
            'bulk_mode' => ['required', Rule::in(['kosong', 'semua'])],
        ], [
            'bulk_semester_id.required' => 'Semester wajib dipilih.',
            'bulk_semester_id.exists' => 'Semester tidak ditemukan.',
            'bulk_prodi_id.required' => 'Program studi wajib dipilih.',
            'bulk_angkatan.required' => 'Angkatan wajib dipilih.',
            'bulk_status.required' => 'Status registrasi wajib dipilih.',
            'bulk_status.in' => 'Status registrasi tidak valid.',
            'bulk_mode.required' => 'Mode penerapan wajib dipilih.',
        ]);
    }

    private function inputBulkTerisi(): bool
    {
        return (bool) ($this->bulk_semester_id
            && $this->bulk_prodi_id
            && $this->bulk_angkatan
            && in_array($this->bulk_status, StatusRegistrasiMahasiswa::STATUS, true)
            && in_array($this->bulk_mode, ['kosong', 'semua'], true));
    }

    private function querySasaranBulk(?Semester $semester = null): Builder
    {
        $semester ??= $this->semesterBulk;

        return Mahasiswa::query()
            ->where('prodi_id', $this->bulk_prodi_id)
            ->where('angkatan', $this->bulk_angkatan)
            ->when($semester?->is_aktif, fn (Builder $query) => $query->where('status', '!=', 'lulus'))
            ->when($this->bulk_mode === 'kosong', fn (Builder $query) => $query->whereDoesntHave(
                'status_registrasi',
                fn (Builder $registrasi) => $registrasi->where('semester_id', $semester?->id_semester)
            ));
    }

    private function pastikanBerhak(): void
    {
        abort_unless(auth()->user()?->can('status-registrasi-mahasiswa:'), 403);
    }
}; ?>

<div>
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">Status Registrasi Mahasiswa</h4>
        <ol class="breadcrumb m-0">
            <li class="breadcrumb-item">Akademik</li>
            <li class="breadcrumb-item active">Status Registrasi Mahasiswa</li>
        </ol>
    </div>

    <livewire:alert />

    @if (! $this->semesterBulk)
        <div class="alert alert-warning">
            Semester aktif belum ditetapkan. Pilih semester secara manual sebelum menerapkan status registrasi.
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h5 class="mb-1">Terapkan Per Angkatan</h5>
            <div class="text-muted small">Gunakan mode hanya yang belum diatur agar status individual tetap dipertahankan.</div>
        </div>
        <form wire:submit="siapkanBulk" class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="bulk-semester">Semester</label>
                    <select id="bulk-semester" class="form-select" wire:model.live="bulk_semester_id">
                        <option value="">Pilih semester</option>
                        @foreach (Semester::orderByDesc('tahun')->orderByDesc('kode')->get() as $semester)
                            <option value="{{ $semester->id_semester }}">
                                {{ $semester->kode }} - {{ ucfirst($semester->nama) }} {{ $semester->tahun }}{{ $semester->is_aktif ? ' (Aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('bulk_semester_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
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
                <div class="col-md-2">
                    <label class="form-label" for="bulk-status">Status</label>
                    <select id="bulk-status" class="form-select" wire:model.live="bulk_status">
                        <option value="aktif">Aktif</option>
                        <option value="cuti">Cuti</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                    @error('bulk_status') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="bulk-mode">Mode</label>
                    <select id="bulk-mode" class="form-select" wire:model.live="bulk_mode">
                        <option value="kosong">Belum diatur</option>
                        <option value="semua">Timpa semua</option>
                    </select>
                    @error('bulk_mode') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <span class="badge bg-info-subtle text-info fs-6">{{ $this->jumlahSasaranBulk }} mahasiswa</span>
                @if ($this->jumlahDilewatiLulus > 0)
                    <span class="badge bg-warning-subtle text-warning fs-6">{{ $this->jumlahDilewatiLulus }} lulus dilewati</span>
                @endif
                <button type="submit" class="btn btn-primary ms-md-auto" wire:loading.attr="disabled" wire:target="siapkanBulk">
                    <i class="ri-check-double-line"></i> Siapkan
                </button>
            </div>
            @error('bulk_target') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-1">Pengaturan Per Mahasiswa</h5>
            <div class="text-muted small">Pilih semester, filter mahasiswa, lalu atur status registrasinya.</div>
        </div>
        <div class="card-body">
            <livewire:table-status-registrasi-mahasiswa />
        </div>
    </div>

    @if ($konfirmasi_bulk)
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="konfirmasi-bulk-title">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="konfirmasi-bulk-title">Konfirmasi Status Registrasi</h5>
                        <button type="button" class="btn-close" wire:click="tutupKonfirmasiBulk" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            Status <strong>{{ ucfirst($bulk_status) }}</strong> akan diterapkan kepada
                            <strong>{{ $this->jumlahSasaranBulk }} mahasiswa</strong> untuk semester
                            <strong>{{ $this->semesterBulk?->kode }}</strong>.
                        </p>
                        @if ($bulk_mode === 'semua')
                            <div class="alert alert-warning mb-0">Mode <strong>Timpa semua</strong> akan mengganti status yang sudah diatur.</div>
                        @else
                            <div class="alert alert-info mb-0">Status mahasiswa yang sudah diatur tidak akan diubah.</div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" wire:click="tutupKonfirmasiBulk">Batal</button>
                        <button type="button" class="btn btn-primary" wire:click="terapkanBulk" wire:loading.attr="disabled" wire:target="terapkanBulk">
                            Terapkan Status
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>