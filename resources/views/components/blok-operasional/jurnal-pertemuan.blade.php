<?php

use App\Models\MonitoringPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Jurnal pelaksanaan satu pertemuan.
 *
 * Jadwal realisasi diprefill dari jadwal rencana pertemuan supaya dosen hanya perlu
 * mengubah bila pelaksanaannya bergeser. Status pelaksanaan disimpan sebagai
 * `terlaksana` untuk kompatibilitas data lama, tanpa input status dari pengguna.
 */
new class extends Component
{
    public int $pertemuan_blok_id;
    public bool $tampilkan_tombol_simpan = true;

    public ?string $tanggal_realisasi = null;
    public ?string $jam_mulai_realisasi = null;
    public ?string $jam_selesai_realisasi = null;
    public string $topik_realisasi = '';
    public string $catatan_pelaksanaan = '';
    public string $kendala = '';

    public function mount(int $pertemuan_blok_id): void
    {
        $this->pertemuan_blok_id = $pertemuan_blok_id;

        abort_unless(
            AksesPertemuanBlok::bolehKelolaPertemuan(auth()->user(), $this->pertemuan_blok_id),
            403
        );

        $this->muatJurnal();
    }

    private function muatJurnal(): void
    {
        $pertemuan = $this->pertemuan();
        $jurnal = $pertemuan->monitoring_pertemuan_blok;

        // Belum pernah diisi: pakai jadwal rencana pertemuan sebagai titik awal.
        $this->tanggal_realisasi = $jurnal
            ? $jurnal->tanggal_realisasi?->toDateString()
            : $pertemuan->tanggal?->toDateString();
        $this->jam_mulai_realisasi = $this->formatJam($jurnal ? $jurnal->jam_mulai_realisasi : $pertemuan->jam_mulai);
        $this->jam_selesai_realisasi = $this->formatJam($jurnal ? $jurnal->jam_selesai_realisasi : $pertemuan->jam_selesai);
        $this->topik_realisasi = (string) ($jurnal ? $jurnal->topik_realisasi : $pertemuan->topik);
        $this->catatan_pelaksanaan = (string) ($jurnal?->catatan_pelaksanaan ?? '');
        $this->kendala = (string) ($jurnal?->kendala ?? '');
    }

    private function pertemuan(): PertemuanBlok
    {
        return PertemuanBlok::query()
            ->with([
                'monitoring_pertemuan_blok',
                'materi_rinci_blok:id_materi_rinci_blok,judul',
            ])
            ->findOrFail($this->pertemuan_blok_id);
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }

    public function bolehIsi(): bool
    {
        return AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $this->pertemuan_blok_id);
    }

    public function simpan(): void
    {
        $this->tulisJurnal();
    }

    #[On('simpan-pelaksanaan')]
    public function simpanDariPelaksanaan(int $pertemuan_blok_id): void
    {
        if ($pertemuan_blok_id === $this->pertemuan_blok_id) {
            $this->simpan();
        }
    }

    private function tulisJurnal(): void
    {
        abort_unless($this->bolehIsi(), 403);

        $data = $this->validate([
            'tanggal_realisasi' => ['required', 'date_format:Y-m-d'],
            'jam_mulai_realisasi' => ['nullable', 'date_format:H:i'],
            'jam_selesai_realisasi' => ['nullable', 'date_format:H:i'],
            'topik_realisasi' => ['nullable', 'string', 'max:255'],
            'catatan_pelaksanaan' => ['nullable', 'string', 'max:2000'],
            'kendala' => ['nullable', 'string', 'max:2000'],
        ], [
            'tanggal_realisasi.required' => 'Tanggal pelaksanaan wajib diisi.',
            'tanggal_realisasi.date_format' => 'Tanggal pelaksanaan harus berformat YYYY-MM-DD.',
            'jam_mulai_realisasi.date_format' => 'Jam mulai harus berformat HH:MM.',
            'jam_selesai_realisasi.date_format' => 'Jam selesai harus berformat HH:MM.',
            'topik_realisasi.max' => 'Topik maksimal 255 karakter.',
            'catatan_pelaksanaan.max' => 'Catatan maksimal 2000 karakter.',
            'kendala.max' => 'Kendala maksimal 2000 karakter.',
        ]);

        if (! $this->lolosAturanDomain($data)) {
            return;
        }

        $pertemuan = $this->pertemuan();

        DB::transaction(function () use ($data, $pertemuan) {
            $muatan = [
                'status_pelaksanaan' => 'terlaksana',
                'tanggal_realisasi' => $data['tanggal_realisasi'] ?: null,
                'jam_mulai_realisasi' => $data['jam_mulai_realisasi'] ?: null,
                'jam_selesai_realisasi' => $data['jam_selesai_realisasi'] ?: null,
                'topik_realisasi' => trim((string) $data['topik_realisasi']) ?: null,
                'catatan_pelaksanaan' => trim((string) $data['catatan_pelaksanaan']) ?: null,
                'kendala' => trim((string) $data['kendala']) ?: null,
                'diisi_oleh_user_id' => auth()->id(),
            ];

            MonitoringPertemuanBlok::updateOrCreate(
                ['pertemuan_blok_id' => $this->pertemuan_blok_id],
                $muatan
            );

            $pertemuan->status = MonitoringPertemuanBlok::STATUS_PERTEMUAN['terlaksana'];
            $pertemuan->save();
        });

        $this->muatJurnal();
        $this->dispatch('jurnal-pertemuan-tersimpan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Monitoring pelaksanaan berhasil disimpan.',
        ]);
    }

    /**
     * Aturan yang tidak bisa diungkapkan sebagai rule validasi biasa. Pola addError
     * lalu return mengikuti `savePertemuan()` pada tab Pertemuan.
     *
     * @param  array<string, mixed>  $data
     */
    private function lolosAturanDomain(array $data): bool
    {
        if ($data['jam_selesai_realisasi'] && ! $data['jam_mulai_realisasi']) {
            $this->addError('jam_mulai_realisasi', 'Jam mulai wajib diisi bila jam selesai diisi.');

            return false;
        }

        if (
            $data['jam_mulai_realisasi'] && $data['jam_selesai_realisasi']
            && $data['jam_selesai_realisasi'] <= $data['jam_mulai_realisasi']
        ) {
            $this->addError('jam_selesai_realisasi', 'Jam selesai harus lebih besar dari jam mulai.');

            return false;
        }

        return true;
    }

    public function render()
    {
        $pertemuan = $this->pertemuan();

        return $this->view([
            'pertemuan' => $pertemuan,
            'bolehIsi' => $this->bolehIsi(),
        ]);
    }
};
?>

<div>
    <x-full-page-loading message="Memproses operasional blok..." />
    <div class="border rounded p-3 mb-3 bg-light">
        <div class="text-muted small">Jadwal Rencana</div>
        <div class="fw-semibold">{{ $pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik }}</div>
        <div class="text-muted small mt-1">
            @if ($pertemuan->tanggal)
                {{ $pertemuan->tanggal->format('d/m/Y') }}
            @else
                tanggal belum ditetapkan
            @endif
            @if ($pertemuan->jam_mulai)
                &middot; {{ substr((string) $pertemuan->jam_mulai, 0, 5) }}{{ $pertemuan->jam_selesai ? '-'.substr((string) $pertemuan->jam_selesai, 0, 5) : '' }}
            @endif
            @if ($pertemuan->ruangan)
                &middot; {{ $pertemuan->ruangan }}
            @endif
        </div>
    </div>

    <form wire:submit="simpan">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small mb-1">Tanggal Pelaksanaan</label>
                <input type="date" class="form-control form-control-sm" wire:model="tanggal_realisasi" @disabled(! $bolehIsi)>
                @error('tanggal_realisasi') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Mulai</label>
                <input type="time" class="form-control form-control-sm" wire:model="jam_mulai_realisasi" @disabled(! $bolehIsi)>
                @error('jam_mulai_realisasi') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Selesai</label>
                <input type="time" class="form-control form-control-sm" wire:model="jam_selesai_realisasi" @disabled(! $bolehIsi)>
                @error('jam_selesai_realisasi') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label class="form-label small mb-1">Topik yang Disampaikan</label>
                <input type="text" class="form-control form-control-sm" wire:model="topik_realisasi" @disabled(! $bolehIsi)>
                @error('topik_realisasi') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label small mb-1">Catatan Pelaksanaan <span class="text-muted">(opsional)</span></label>
                <textarea class="form-control form-control-sm" rows="3" wire:model="catatan_pelaksanaan" @disabled(! $bolehIsi)></textarea>
                @error('catatan_pelaksanaan') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">Kendala <span class="text-muted">(opsional)</span></label>
                <textarea class="form-control form-control-sm" rows="3" wire:model="kendala" @disabled(! $bolehIsi)></textarea>
                @error('kendala') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        @if ($bolehIsi)
            <div class="d-flex flex-wrap gap-2 mt-3">
                @if ($tampilkan_tombol_simpan)
                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="simpan">
                        <i class="ri-save-line"></i> SIMPAN
                    </button>
                @endif
            </div>
        @endif
    </form>
</div>
