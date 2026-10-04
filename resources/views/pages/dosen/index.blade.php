<?php

use App\Exports\ArrayTemplateExport;
use App\Imports\DosenImport;
use App\Models\Prodi;
use App\Support\Akademik\Sync\SinkronisasiDosen;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public $importFile;

    public string $prodi_id = '';

    public string $pencarian_api = '';

    public $prodi = [];

    #[Locked]
    public array $kandidat_api = [];

    #[Locked]
    public ?array $hasil_sinkronisasi = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('dosen:'), 403);
        $this->prodi = Prodi::query()
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id_prodi', 'kode', 'nama']);
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiDosen::cacheKey());
    }

    public function import()
    {
        $this->bolehSinkron();
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'importFile.required' => 'File import wajib dipilih.',
            'importFile.mimes' => 'File import harus berformat xlsx, xls, atau csv.',
        ]);

        Excel::import(new DosenImport, $this->importFile);

        $this->reset('importFile');
        session()->flash('success', 'Berhasil import data dosen');

        return $this->redirect(route('dosen.index'), navigate: true);
    }

    public function template()
    {
        abort_unless(auth()->user()?->can('dosen:tambah'), 403);

        return Excel::download(new ArrayTemplateExport([
            ['nidn', 'nip', 'nama', 'email', 'no_hp', 'gelar_depan', 'gelar_belakang', 'bidang_keahlian', 'kode_prodi', 'status'],
            ['1234567890', '198001012006041001', 'Nama Dosen Contoh', 'dosen@example.com', '081234567890', 'dr.', 'M.Kes.', 'Ilmu Kedokteran Dasar', 'PSPD', 'aktif'],
        ]), 'template-import-dosen.xlsx');
    }

    public function sinkronkan(SinkronisasiDosen $sinkronisasi): void
    {
        $this->bolehSinkron();
        $this->validate([
            'prodi_id' => [
                'required',
                Rule::exists('prodi', 'id_prodi')->where(fn (Builder $query) => $query
                    ->where('status', 'aktif')
                    ->whereNull('deleted_at')),
            ],
        ], [
            'prodi_id.required' => 'Program studi wajib dipilih.',
            'prodi_id.exists' => 'Program studi aktif tidak ditemukan.',
        ]);

        try {
            $prodi = Prodi::query()->findOrFail((int) $this->prodi_id);
            $this->hasil_sinkronisasi = $sinkronisasi->handle($prodi);
            $this->sinkronisasiBerhasil();
        } catch (DomainException $e) {
            $this->simpanHasilGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilGagal('Sinkronisasi dosen gagal. Periksa koneksi dan format data API.');
        }
    }

    public function cariDosenApi(SinkronisasiDosen $sinkronisasi): void
    {
        $this->bolehSinkron();
        $this->validate([
            'pencarian_api' => ['required', 'string', 'min:3', 'max:100'],
        ], [
            'pencarian_api.required' => 'NIP, NIDN, atau nama dosen wajib diisi.',
            'pencarian_api.min' => 'Pencarian minimal 3 karakter.',
        ]);

        try {
            $this->kandidat_api = $sinkronisasi->cari($this->pencarian_api);

            if ($this->kandidat_api === []) {
                $this->dispatch('notify', message: ['status' => 'warning', 'message' => 'Kandidat dosen tidak ditemukan di API.']);
            }
        } catch (DomainException $e) {
            $this->kandidat_api = [];
            $this->dispatch('notify', message: ['status' => 'error', 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $this->kandidat_api = [];
            $this->dispatch('notify', message: ['status' => 'error', 'message' => 'Pencarian dosen API gagal.']);
        }
    }

    public function tambahDosenApi(string $token, SinkronisasiDosen $sinkronisasi): void
    {
        $this->bolehSinkron();

        try {
            $this->hasil_sinkronisasi = $sinkronisasi->tambah($token);
            $this->reset('pencarian_api', 'kandidat_api');
            $this->sinkronisasiBerhasil();
        } catch (DomainException $e) {
            $this->simpanHasilGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilGagal('Penambahan dosen dari API gagal. Periksa koneksi dan format data API.');
        }
    }

    #[On('dosen-disinkronkan')]
    public function muatHasilSinkronisasi(): void
    {
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiDosen::cacheKey());
    }

    private function bolehSinkron(): void
    {
        abort_unless(auth()->user()?->can('dosen:tambah') && auth()->user()?->can('dosen:edit'), 403);
    }

    private function sinkronisasiBerhasil(): void
    {
        $this->dispatch('dosen-disinkronkan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $this->hasil_sinkronisasi['pesan'],
        ]);
    }

    private function simpanHasilGagal(string $pesan): void
    {
        $this->hasil_sinkronisasi = [
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
        ];

        Cache::put(SinkronisasiDosen::cacheKey(), $this->hasil_sinkronisasi, now()->addDays(30));
        $this->dispatch('dosen-disinkronkan');
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }
}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Kelola Dosen</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Akademik</a></li>
                    <li class="breadcrumb-item active">Dosen</li>
                </ol>
            </div>
        </div>
    </div>

    @if (auth()->user()?->can('dosen:tambah') && auth()->user()?->can('dosen:edit'))
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Sinkronisasi API</h5></div>
            <div class="card-body">
                <form wire:submit="sinkronkan" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label for="prodi-sync-dosen" class="form-label">Program Studi</label>
                        <select id="prodi-sync-dosen" wire:model="prodi_id" class="form-select">
                            <option value="">Pilih program studi</option>
                            @foreach ($prodi as $item)
                                <option value="{{ $item->id_prodi }}">{{ $item->kode }} - {{ $item->nama }}</option>
                            @endforeach
                        </select>
                        @error('prodi_id') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="sinkronkan">
                            <span wire:loading.remove wire:target="sinkronkan"><i class="ri-refresh-line"></i> Sync Prodi</span>
                            <span wire:loading wire:target="sinkronkan">Menyinkronkan...</span>
                        </button>
                    </div>
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-api-dosen">
                            <i class="ri-user-search-line"></i> Tambah dari API
                        </button>
                    </div>
                </form>
            </div>
            @if ($hasil_sinkronisasi)
                <div class="card-footer">
                    <div class="alert alert-{{ $hasil_sinkronisasi['status'] === 'success' ? 'success' : 'danger' }} mb-0">
                        <strong>{{ $hasil_sinkronisasi['pesan'] }}</strong>
                        <div class="small mt-1">
                            {{ $hasil_sinkronisasi['selesai_pada'] }} · Diterima: {{ $hasil_sinkronisasi['diterima'] }} ·
                            Dibuat: {{ $hasil_sinkronisasi['dibuat'] }} · Diubah: {{ $hasil_sinkronisasi['diubah'] }} ·
                            Dipulihkan: {{ $hasil_sinkronisasi['dipulihkan'] }} · Tetap: {{ $hasil_sinkronisasi['tetap'] }} ·
                            Dilewati: {{ $hasil_sinkronisasi['dilewati'] }}
                        </div>
                        @if ($hasil_sinkronisasi['rincian'])
                            <details class="small mt-2">
                                <summary>Rincian data dilewati</summary>
                                <ul class="mb-0 mt-1">
                                    @foreach ($hasil_sinkronisasi['rincian'] as $rincian)<li>{{ $rincian }}</li>@endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0">Daftar Dosen</h5>
                <div class="d-flex justify-content-end gap-2">
                    @if (auth()->user()?->can('dosen:tambah') && auth()->user()?->can('dosen:edit'))
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-import-dosen">
                            <i class="ri-upload-2-line"></i> Import
                        </button>
                    @endif
                    @can('dosen:tambah')
                        <a href="{{ route('dosen.add_edit', ['id' => 'add']) }}" wire:navigate class="btn btn-primary btn-sm">
                            <i class="ri-add-box-fill"></i> Tambah
                        </a>
                    @endcan
                </div>
            </div>
            <livewire:alert/>
            <livewire:table-dosen lazy />
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="modal-api-dosen" tabindex="-1" aria-labelledby="modal-api-dosen-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-api-dosen-label">Tambah Dosen dari API</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit="cariDosenApi" class="input-group mb-3">
                        <input type="search" wire:model="pencarian_api" class="form-control" placeholder="Cari NIP, NIDN, atau nama" aria-label="Cari dosen API">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="cariDosenApi">
                            <span wire:loading.remove wire:target="cariDosenApi">Cari</span>
                            <span wire:loading wire:target="cariDosenApi">Mencari...</span>
                        </button>
                    </form>
                    @error('pencarian_api') <div class="small text-danger mb-3">{{ $message }}</div> @enderror

                    @if ($kandidat_api)
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Kode/NIP/NIDN</th><th>Nama</th><th>Email</th><th>Prodi</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($kandidat_api as $kandidat)
                                        <tr wire:key="kandidat-dosen-{{ $kandidat['token'] }}">
                                            <td>{{ $kandidat['kd_dosen'] ?: ($kandidat['nip'] ?: $kandidat['nidn']) }}</td>
                                            <td>{{ $kandidat['nama'] }}</td>
                                            <td>{{ $kandidat['email'] ?: '-' }}</td>
                                            <td>{{ $kandidat['prodi'] ?: '-' }}</td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-primary btn-sm" wire:click="tambahDosenApi('{{ $kandidat['token'] }}')" wire:loading.attr="disabled">
                                                    Tambah
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($pencarian_api !== '')
                        <div class="text-muted text-center py-3">Belum ada kandidat. Jalankan pencarian.</div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="modal-import-dosen" tabindex="-1" aria-labelledby="modal-import-dosen-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form wire:submit="import" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-import-dosen-label">Template Import</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <button type="button" wire:click="template" wire:loading.attr="disabled" wire:target="template,import" class="btn btn-secondary btn-sm mb-3 d-block">
                        <i class="ri-file-excel-2-line"></i> Template Import
                    </button>
                    <label for="import-file-dosen" class="form-label">File Import Dosen</label>
                    <input id="import-file-dosen" type="file" class="form-control" wire:model="importFile" wire:loading.attr="disabled" wire:target="import" accept=".xlsx,.xls,.csv">
                    @error('importFile') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                    @error('import_dosen') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="template,import">
                        <span wire:loading.remove wire:target="import"><i class="ri-upload-2-line"></i> Import</span>
                        <span wire:loading wire:target="import">Memproses...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>