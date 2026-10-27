<?php

use App\Exports\ArrayTemplateExport;
use App\Imports\DosenImport;
use App\Support\Akademik\Sync\SinkronisasiDosen;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public $importFile;

    public string $pencarian_api = '';

    #[Locked]
    public array $kandidat_api = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('dosen:'), 403);
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
            $hasil = $sinkronisasi->tambah($token);
            $this->reset('pencarian_api', 'kandidat_api');
            $this->dispatch('dosen-disinkronkan');
            $this->dispatch('notify', message: ['status' => 'success', 'message' => $hasil['pesan']]);
        } catch (DomainException $e) {
            $this->dispatch('notify', message: ['status' => 'error', 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('notify', message: ['status' => 'error', 'message' => 'Penambahan dosen dari API gagal. Periksa koneksi dan format data API.']);
        }
    }

    private function bolehSinkron(): void
    {
        abort_unless(auth()->user()?->can('dosen:tambah') && auth()->user()?->can('dosen:edit'), 403);
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

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0">Daftar Dosen</h5>
                <div class="d-flex justify-content-end gap-2">
                    @if (auth()->user()?->can('dosen:tambah') && auth()->user()?->can('dosen:edit'))
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-api-dosen">
                            <i class="ri-user-search-line"></i> Tambah dari API
                        </button>
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