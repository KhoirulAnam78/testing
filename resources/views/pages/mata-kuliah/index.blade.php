<?php

use App\Exports\ArrayTemplateExport;
use App\Imports\MataKuliahImport;
use App\Models\Prodi;
use App\Support\Akademik\Sync\SinkronisasiMataKuliah;
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

    public $prodi = [];

    #[Locked]
    public ?array $hasil_sinkronisasi = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('mata-kuliah:'), 403);
        $this->prodi = Prodi::query()
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id_prodi', 'kode', 'nama']);
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiMataKuliah::cacheKey());
    }

    public function import()
    {
        abort_unless(
            auth()->user()?->can('mata-kuliah:tambah') && auth()->user()?->can('mata-kuliah:edit'),
            403
        );

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'importFile.required' => 'File import wajib dipilih.',
            'importFile.mimes' => 'File import harus berformat xlsx, xls, atau csv.',
        ]);

        Excel::import(new MataKuliahImport, $this->importFile);

        $this->reset('importFile');
        session()->flash('success', 'Berhasil import data mata kuliah');

        return $this->redirect(route('mata-kuliah.index'), navigate: true);
    }

    public function template()
    {
        abort_unless(auth()->user()?->can('mata-kuliah:tambah'), 403);

        return Excel::download(new ArrayTemplateExport([
            ['kode_prodi', 'kode', 'nama', 'sks', 'deskripsi', 'status'],
            ['PSPD', 'BIO101', 'Biomedik Dasar', '3', 'Contoh deskripsi mata kuliah', 'aktif'],
        ]), 'template-import-mata-kuliah.xlsx');
    }

    public function sinkronkan(SinkronisasiMataKuliah $sinkronisasi): void
    {
        abort_unless(
            auth()->user()?->can('mata-kuliah:tambah') && auth()->user()?->can('mata-kuliah:edit'),
            403
        );

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
            $this->dispatch('mata-kuliah-disinkronkan');
            $this->dispatch('notify', message: [
                'status' => 'success',
                'message' => $this->hasil_sinkronisasi['pesan'],
            ]);
        } catch (DomainException $e) {
            $this->simpanHasilGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilGagal('Sinkronisasi mata kuliah gagal. Periksa koneksi dan format data API.');
        }
    }

    #[On('mata-kuliah-disinkronkan')]
    public function muatHasilSinkronisasi(): void
    {
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiMataKuliah::cacheKey());
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
            'pesan' => $pesan,
        ];

        Cache::put(SinkronisasiMataKuliah::cacheKey(), $this->hasil_sinkronisasi, now()->addDays(30));
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }
}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Kelola Mata Kuliah</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Akademik</a></li>
                    <li class="breadcrumb-item active">Mata Kuliah</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                @if (auth()->user()?->can('mata-kuliah:tambah') && auth()->user()?->can('mata-kuliah:edit'))
                    <div class="card-header">
                        <h5 class="mb-1">Sinkronisasi Mata Kuliah</h5>
                        <p class="text-muted mb-0">Kode, nama, dan SKS diperbarui dari API. Status, deskripsi, dan data lokal yang tidak dikirim API tetap dipertahankan.</p>
                    </div>
                    <div class="card-body border-bottom">
                        <form wire:submit="sinkronkan">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-7">
                                    <label class="form-label" for="prodi-sinkronisasi">Program Studi</label>
                                    <select id="prodi-sinkronisasi" class="form-select @error('prodi_id') is-invalid @enderror" wire:model="prodi_id">
                                        <option value="">Pilih prodi</option>
                                        @foreach ($prodi as $item)
                                            <option value="{{ $item->id_prodi }}">{{ $item->kode }} - {{ $item->nama }}</option>
                                        @endforeach
                                    </select>
                                    @error('prodi_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text">Kode prodi lokal harus sama dengan <code>kd_prodi</code> API.</div>
                                </div>
                                <div class="col-md-5 text-md-end">
                                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="sinkronkan">
                                        <span wire:loading.remove wire:target="sinkronkan"><i class="ri-refresh-line"></i> Sinkronkan Mata Kuliah</span>
                                        <span wire:loading wire:target="sinkronkan">Sedang menyinkronkan...</span>
                                    </button>
                                </div>
                            </div>
                        </form>

                        @if ($hasil_sinkronisasi)
                            <div class="alert {{ $hasil_sinkronisasi['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mt-3 mb-0" role="status">
                                <div class="fw-semibold">Sinkronisasi {{ $hasil_sinkronisasi['selesai_pada'] }}</div>
                                <div>{{ $hasil_sinkronisasi['pesan'] }}</div>
                                @if ($hasil_sinkronisasi['status'] === 'success')
                                    <div class="small mt-1">
                                        Prodi: {{ $hasil_sinkronisasi['prodi'] }} ·
                                        Diterima: {{ $hasil_sinkronisasi['diterima'] }} ·
                                        Unik: {{ $hasil_sinkronisasi['unik'] }} ·
                                        Dibuat: {{ $hasil_sinkronisasi['dibuat'] }} ·
                                        Diubah: {{ $hasil_sinkronisasi['diubah'] }} ·
                                        Dipulihkan: {{ $hasil_sinkronisasi['dipulihkan'] }} ·
                                        Tetap: {{ $hasil_sinkronisasi['tetap'] }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0">Daftar Mata Kuliah</h5>
                        <div class="d-flex justify-content-end gap-2">
                            @if (auth()->user()?->can('mata-kuliah:tambah') && auth()->user()?->can('mata-kuliah:edit'))
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-import-mata-kuliah">
                                    <i class="ri-upload-2-line"></i> Import
                                </button>
                            @endif
                            @can('mata-kuliah:tambah')
                                <a href="{{ route('mata-kuliah.add_edit', ['id' => 'add']) }}" wire:navigate wire:loading.class="pe-none disabled" wire:target="template,import" class="btn btn-primary btn-sm">
                                    <i class="ri-add-box-fill"></i> Tambah
                                </a>
                            @endcan
                        </div>
                    </div>
                    <livewire:alert/>
                    <livewire:table-mata-kuliah lazy />
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="modal-import-mata-kuliah" tabindex="-1" aria-labelledby="modal-import-mata-kuliah-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form wire:submit="import" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-import-mata-kuliah-label">Template Import</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <button type="button" wire:click="template" wire:loading.attr="disabled" wire:target="template,import" class="btn btn-secondary btn-sm mb-3 d-block">
                        <i class="ri-file-excel-2-line"></i> Template Import
                    </button>
                    <label for="import-file-mata-kuliah" class="form-label">File Import Mata Kuliah</label>
                    <input id="import-file-mata-kuliah" type="file" class="form-control" wire:model="importFile" wire:loading.attr="disabled" wire:target="import" accept=".xlsx,.xls,.csv">
                    @error('importFile') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                    @error('import_mata_kuliah') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
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
