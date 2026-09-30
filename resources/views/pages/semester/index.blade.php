<?php

use App\Support\Akademik\Sync\SinkronisasiSemester;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $kode_semester = '';

    public string $mulai_tahun = '2025';

    #[Locked]
    public ?array $hasil_sinkronisasi = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('semester:'), 403);
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiSemester::cacheKey());
    }

    public function sinkronkan(SinkronisasiSemester $sinkronisasi): void
    {
        abort_unless(auth()->user()?->can('semester:'), 403);

        $this->kode_semester = trim($this->kode_semester);
        $this->validate([
            'kode_semester' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'mulai_tahun' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
        ], [
            'kode_semester.regex' => 'Kode semester harus terdiri dari 5 digit, contoh: 20261.',
            'mulai_tahun.required' => 'Tahun awal wajib diisi.',
            'mulai_tahun.integer' => 'Tahun awal harus berupa angka.',
            'mulai_tahun.digits' => 'Tahun awal harus terdiri dari 4 digit.',
            'mulai_tahun.min' => 'Tahun awal minimal 2000.',
            'mulai_tahun.max' => 'Tahun awal maksimal 2100.',
        ]);

        try {
            $this->hasil_sinkronisasi = $sinkronisasi->handle(
                $this->kode_semester !== '' ? $this->kode_semester : null,
                (int) $this->mulai_tahun
            );
            $this->dispatch('semester-disinkronkan');
            $this->dispatch('notify', message: [
                'status' => 'success',
                'message' => $this->hasil_sinkronisasi['pesan'],
            ]);
        } catch (DomainException $e) {
            $this->simpanHasilGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilGagal('Sinkronisasi semester gagal. Periksa koneksi dan format data API.');
        }
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
            'tetap' => 0,
            'pesan' => $pesan,
        ];

        Cache::put(SinkronisasiSemester::cacheKey(), $this->hasil_sinkronisasi, now()->addDays(30));
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }
}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Semester</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Akademik</a></li>
                    <li class="breadcrumb-item active">Semester</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-1">Sinkronisasi Semester</h5>
                    <p class="text-muted mb-0">Data semester bersumber dari API akademik dan tidak dibuat manual di aplikasi ini.</p>
                </div>
                <div class="card-body border-bottom">
                    <form wire:submit="sinkronkan">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="kode-semester">Kode Semester</label>
                                <input
                                    id="kode-semester"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="5"
                                    class="form-control @error('kode_semester') is-invalid @enderror"
                                    wire:model="kode_semester"
                                    placeholder="Contoh: 20261"
                                >
                                @error('kode_semester') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Isi untuk menarik satu semester. Kosongkan untuk menarik rentang tahun.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="mulai-tahun">Mulai Tahun</label>
                                <input
                                    id="mulai-tahun"
                                    type="number"
                                    min="2000"
                                    max="2100"
                                    class="form-control @error('mulai_tahun') is-invalid @enderror"
                                    wire:model="mulai_tahun"
                                >
                                @error('mulai_tahun') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5 text-md-end">
                                <div class="form-label d-none d-md-block" aria-hidden="true">&nbsp;</div>
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="sinkronkan">
                                    <span wire:loading.remove wire:target="sinkronkan"><i class="ri-refresh-line"></i> Sinkronkan Semester</span>
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
                                    Diterima: {{ $hasil_sinkronisasi['diterima'] }} ·
                                    Unik: {{ $hasil_sinkronisasi['unik'] }} ·
                                    Dibuat: {{ $hasil_sinkronisasi['dibuat'] }} ·
                                    Diubah: {{ $hasil_sinkronisasi['diubah'] }} ·
                                    Tetap: {{ $hasil_sinkronisasi['tetap'] }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0">Daftar Semester</h5>
                        @can('semester:tambah')
                            <a href="{{ route('semester.add_edit', ['id' => 'add']) }}" wire:navigate class="btn btn-primary btn-sm">
                                <i class="ri-add-box-fill"></i> Tambah
                            </a>
                        @endcan
                    </div>
                    <livewire:alert/>
                    <livewire:table-semester lazy />
                </div>
            </div>
        </div>
    </div>
</div>
