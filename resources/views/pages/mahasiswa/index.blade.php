<?php

use App\Exports\ArrayTemplateExport;
use App\Imports\MahasiswaImport;
use App\Support\Akademik\Sync\SinkronisasiMahasiswa;
use Illuminate\Support\Facades\Cache;
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

    #[Locked]
    public ?array $hasil_sinkronisasi = null;

    public function mount(): void
    {
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiMahasiswa::cacheKey());
    }

    public function import()
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'importFile.required' => 'File import wajib dipilih.',
            'importFile.mimes' => 'File import harus berformat xlsx, xls, atau csv.',
        ]);

        Excel::import(new MahasiswaImport, $this->importFile);

        $this->reset('importFile');
        session()->flash('success', 'Berhasil import data mahasiswa');

        return $this->redirect(route('mahasiswa.index'), navigate: true);
    }

    public function template()
    {
        return Excel::download(new ArrayTemplateExport([
            ['nim', 'nama', 'email', 'no_hp', 'kode_prodi', 'angkatan', 'status'],
            ['20260001', 'Nama Mahasiswa Contoh', 'mahasiswa@example.com', '081234567891', 'PSPD', '2026', 'aktif'],
        ]), 'template-import-mahasiswa.xlsx');
    }

    public function sinkronkan(SinkronisasiMahasiswa $sinkronisasi): void
    {
        abort_unless(
            auth()->user()?->can('mahasiswa:tambah') && auth()->user()?->can('mahasiswa:edit'),
            403
        );

        try {
            $this->hasil_sinkronisasi = $sinkronisasi->handle();
            $this->dispatch('mahasiswa-disinkronkan');
            $this->dispatch('notify', message: [
                'status' => 'success',
                'message' => $this->hasil_sinkronisasi['pesan'],
            ]);
        } catch (DomainException $e) {
            $this->simpanHasilGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilGagal('Sinkronisasi mahasiswa gagal. Periksa koneksi, data lokal, dan format API.');
        }
    }

    #[On('mahasiswa-disinkronkan')]
    public function muatHasilSinkronisasi(): void
    {
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiMahasiswa::cacheKey());
    }

    private function simpanHasilGagal(string $pesan): void
    {
        $this->hasil_sinkronisasi = [
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
        ];

        Cache::put(SinkronisasiMahasiswa::cacheKey(), $this->hasil_sinkronisasi, now()->addDays(30));
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }
}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Kelola Mahasiswa</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Akademik</a></li>
                    <li class="breadcrumb-item active">Mahasiswa</li>
                </ol>
            </div>
        </div>
    </div>

    @if (auth()->user()?->can('mahasiswa:tambah') && auth()->user()?->can('mahasiswa:edit'))
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-1">Sinkronisasi Mahasiswa</h5>
                        <p class="text-muted mb-0">Data akun dan mahasiswa diperbarui dari API berdasarkan NIM. Foto manual tetap dipertahankan. Data lokal yang hilang dari API tidak dihapus.</p>
                    </div>
                    <div class="card-body">
                        <button type="button" class="btn btn-primary" wire:click="sinkronkan" wire:loading.attr="disabled" wire:target="sinkronkan">
                            <span wire:loading.remove wire:target="sinkronkan"><i class="ri-refresh-line"></i> Sinkronkan Mahasiswa</span>
                            <span wire:loading wire:target="sinkronkan">Sedang menyinkronkan...</span>
                        </button>

                        @if ($hasil_sinkronisasi)
                            <div class="alert {{ $hasil_sinkronisasi['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mt-3 mb-0" role="status">
                                <div class="fw-semibold">Sinkronisasi {{ $hasil_sinkronisasi['selesai_pada'] }}</div>
                                <div>{{ $hasil_sinkronisasi['pesan'] }}</div>
                                @if ($hasil_sinkronisasi['status'] === 'success')
                                    <div class="small mt-1">
                                        Diterima: {{ $hasil_sinkronisasi['diterima'] }} ·
                                        Unik: {{ $hasil_sinkronisasi['unik'] }} ·
                                        Dibuat: {{ $hasil_sinkronisasi['dibuat'] }} ·
                                        Diubah: {{ $hasil_sinkronisasi['diubah'] }} ·
                                        Dipulihkan: {{ $hasil_sinkronisasi['dipulihkan'] }} ·
                                        Tetap: {{ $hasil_sinkronisasi['tetap'] }} ·
                                        Dilewati: {{ $hasil_sinkronisasi['dilewati'] }}
                                    </div>
                                    <div class="small mt-1">
                                        Foto disimpan: {{ $hasil_sinkronisasi['foto_disimpan'] }} ·
                                        Tidak tersedia: {{ $hasil_sinkronisasi['foto_tidak_tersedia'] }} ·
                                        Manual dipertahankan: {{ $hasil_sinkronisasi['foto_dipertahankan'] }} ·
                                        Gagal: {{ $hasil_sinkronisasi['foto_gagal'] }}
                                    </div>
                                    @if ($hasil_sinkronisasi['rincian'])
                                        <details class="small mt-2">
                                            <summary>Rincian data dilewati (maksimum 20)</summary>
                                            <ul class="mb-0 mt-1">
                                                @foreach ($hasil_sinkronisasi['rincian'] as $rincian)
                                                    <li>{{ $rincian }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-6"><h5>Daftar Mahasiswa</h5></div>
                        <div class="col-6 text-end d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-import-mahasiswa">
                                <i class="ri-upload-2-line"></i> Import
                            </button>
                            <a href="{{ route('mahasiswa.add_edit', ['id' => 'add']) }}" wire:navigate wire:loading.class="pe-none disabled" wire:target="template,import" class="btn btn-primary btn-sm">
                                <i class="ri-add-box-fill"></i> Tambah
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <livewire:alert/>
                    <livewire:table-mahasiswa lazy />
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="modal-import-mahasiswa" tabindex="-1" aria-labelledby="modal-import-mahasiswa-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form wire:submit="import" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-import-mahasiswa-label">Template Import</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <button type="button" wire:click="template" wire:loading.attr="disabled" wire:target="template,import" class="btn btn-secondary btn-sm mb-3 d-block">
                        <i class="ri-file-excel-2-line"></i> Template Import
                    </button>
                    <label for="import-file-mahasiswa" class="form-label">File Import Mahasiswa</label>
                    <input id="import-file-mahasiswa" type="file" class="form-control" wire:model="importFile" wire:loading.attr="disabled" wire:target="import" accept=".xlsx,.xls,.csv">
                    @error('importFile') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                    @error('import_mahasiswa') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
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
