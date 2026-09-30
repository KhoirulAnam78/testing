<?php

use App\Models\Prodi;
use App\Support\Akademik\Sync\SinkronisasiKurikulum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $prodi_id = '';

    public $prodi = [];

    #[Locked]
    public ?array $hasil_sinkronisasi = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('kurikulum:'), 403);
        $this->prodi = Prodi::query()
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id_prodi', 'kode', 'nama']);
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiKurikulum::cacheKey());
    }

    public function sinkronkan(SinkronisasiKurikulum $sinkronisasi): void
    {
        abort_unless(auth()->user()?->can('kurikulum:'), 403);

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
            $this->dispatch('kurikulum-disinkronkan');
            $this->dispatch('notify', message: [
                'status' => 'success',
                'message' => $this->hasil_sinkronisasi['pesan'],
            ]);
        } catch (DomainException $e) {
            $this->simpanHasilGagal($e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->simpanHasilGagal('Sinkronisasi kurikulum gagal. Periksa koneksi, data lokal, dan format API.');
        }
    }

    #[On('kurikulum-disinkronkan')]
    public function muatHasilSinkronisasi(): void
    {
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiKurikulum::cacheKey());
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
            'direkonsiliasi' => 0,
            'dipulihkan' => 0,
            'tetap' => 0,
            'pivot_dibuat' => 0,
            'pivot_diubah' => 0,
            'pivot_dipulihkan' => 0,
            'pivot_tetap' => 0,
            'pesan' => $pesan,
        ];

        Cache::put(SinkronisasiKurikulum::cacheKey(), $this->hasil_sinkronisasi, now()->addDays(30));
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }
}; ?>

<div>
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">Kurikulum</h4>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item">Akademik</li><li class="breadcrumb-item active">Kurikulum</li></ol>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Sinkronisasi API Akademik</h5></div>
        <div class="card-body">
            <form wire:submit="sinkronkan" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="prodi-sync-kurikulum" class="form-label">Program Studi</label>
                    <select id="prodi-sync-kurikulum" wire:model="prodi_id" class="form-select" wire:loading.attr="disabled" wire:target="sinkronkan">
                        <option value="">Pilih program studi</option>
                        @foreach ($prodi as $item)
                            <option value="{{ $item->id_prodi }}">{{ $item->kode }} - {{ $item->nama }}</option>
                        @endforeach
                    </select>
                    @error('prodi_id') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="sinkronkan">
                        <span wire:loading.remove wire:target="sinkronkan"><i class="ri-refresh-line"></i> Sinkronkan Kurikulum</span>
                        <span wire:loading wire:target="sinkronkan"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Menyinkronkan...</span>
                    </button>
                </div>
            </form>

            @if ($hasil_sinkronisasi)
                <div class="alert {{ $hasil_sinkronisasi['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mt-3 mb-0">
                    <div class="fw-semibold">{{ $hasil_sinkronisasi['pesan'] }}</div>
                    <div class="small mt-1">
                        Selesai: {{ $hasil_sinkronisasi['selesai_pada'] }}
                        @if ($hasil_sinkronisasi['prodi']) · Prodi: {{ $hasil_sinkronisasi['prodi'] }} @endif
                    </div>
                    @if ($hasil_sinkronisasi['status'] === 'success')
                        <div class="small mt-1">
                            API: {{ $hasil_sinkronisasi['diterima'] }} · Unik: {{ $hasil_sinkronisasi['unik'] }} ·
                            Dibuat: {{ $hasil_sinkronisasi['dibuat'] }} · Diubah: {{ $hasil_sinkronisasi['diubah'] }} ·
                            Direkonsiliasi: {{ $hasil_sinkronisasi['direkonsiliasi'] }} · Dipulihkan: {{ $hasil_sinkronisasi['dipulihkan'] }} ·
                            Tetap: {{ $hasil_sinkronisasi['tetap'] }}
                        </div>
                        <div class="small">
                            Pemetaan dibuat: {{ $hasil_sinkronisasi['pivot_dibuat'] }} · Diubah: {{ $hasil_sinkronisasi['pivot_diubah'] }} ·
                            Dipulihkan: {{ $hasil_sinkronisasi['pivot_dipulihkan'] }} · Tetap: {{ $hasil_sinkronisasi['pivot_tetap'] }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Kurikulum</h5>
            <a href="{{ route('kurikulum.add_edit', ['id' => 'add']) }}" wire:navigate wire:loading.class="pe-none disabled" wire:target="sinkronkan" class="btn btn-primary btn-sm"><i class="ri-add-box-fill"></i> Tambah</a>
        </div>
        <div class="card-body"><livewire:alert/><livewire:table-kurikulum lazy /></div>
    </div>
</div>