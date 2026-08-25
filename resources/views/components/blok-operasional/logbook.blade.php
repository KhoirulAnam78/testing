<?php

use App\Models\PertemuanBlok;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $blok_id;
    public string $kegiatan_id = '';
    public string $pertemuan_id = '';
    public string $kelompok_id = '';
    public string $status = '';

    public function updated($property): void
    {
        if (in_array($property, ['kegiatan_id', 'pertemuan_id', 'kelompok_id', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function pertemuanQuery()
    {
        return PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->whereHas('aturan_kegiatan_blok.jenis_kegiatan', fn ($query) => $query->where('perlu_logbook', true))
            ->when($this->kegiatan_id !== '', fn ($query) => $query->whereHas(
                'aturan_kegiatan_blok',
                fn ($aturan) => $aturan->where('jenis_kegiatan_id', (int) $this->kegiatan_id)
            ))
            ->when($this->pertemuan_id !== '', fn ($query) => $query->whereKey((int) $this->pertemuan_id))
            ->when($this->kelompok_id !== '', fn ($query) => $query->where('kelompok_blok_id', (int) $this->kelompok_id))
            ->when($this->status !== '', function ($query) {
                if ($this->status === 'belum') {
                    $query->whereDoesntHave('logbook_pertemuan_blok');
                } else {
                    $query->whereHas('logbook_pertemuan_blok', fn ($logbook) => $logbook->where('status', $this->status));
                }
            })
            ->with([
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
                'kelompok_blok:id_kelompok_blok,kode,nama',
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
            ])
            ->orderBy('tanggal')
            ->orderBy('jam_mulai');
    }

    public function options()
    {
        $query = PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->whereHas('aturan_kegiatan_blok.jenis_kegiatan', fn ($jenis) => $jenis->where('perlu_logbook', true));

        return [
            'kegiatan' => (clone $query)->with('aturan_kegiatan_blok.jenis_kegiatan:id,nama')->get()
                ->pluck('aturan_kegiatan_blok.jenis_kegiatan')->filter()->unique('id')->sortBy('nama'),
            'pertemuan' => (clone $query)->with('materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke')->get(),
            'kelompok' => (clone $query)->with('kelompok_blok:id_kelompok_blok,kode,nama')->get()
                ->pluck('kelompok_blok')->filter()->unique('id_kelompok_blok')->sortBy('kode'),
        ];
    }
};
?>

<div>
    <x-full-page-loading message="Memproses operasional blok..." />
    @php($options = $this->options())
    @php($pertemuan = $this->pertemuanQuery()->paginate(10))

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Logbook Pertemuan</h5></div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Kegiatan</label>
                    <select class="form-select" wire:model.live="kegiatan_id">
                        <option value="">Semua kegiatan</option>
                        @foreach ($options['kegiatan'] as $item)
                            <option value="{{ $item->id }}">{{ $item->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pertemuan</label>
                    <select class="form-select" wire:model.live="pertemuan_id">
                        <option value="">Semua pertemuan</option>
                        @foreach ($options['pertemuan'] as $item)
                            <option value="{{ $item->id_pertemuan_blok }}">
                                {{ $item->materi_rinci_blok?->judul ?: $item->topik ?: 'Pertemuan '.$item->id_pertemuan_blok }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelompok</label>
                    <select class="form-select" wire:model.live="kelompok_id">
                        <option value="">Semua kelompok</option>
                        @foreach ($options['kelompok'] as $item)
                            <option value="{{ $item->id_kelompok_blok }}">{{ $item->kode }} - {{ $item->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" wire:model.live="status">
                        <option value="">Semua status</option>
                        <option value="belum">Belum unggah</option>
                        <option value="menunggu">Menunggu validasi</option>
                        <option value="valid">Valid</option>
                        <option value="ditolak">Ditolak</option>
                    </select>
                </div>
            </div>

            @forelse ($pertemuan as $item)
                <div class="border rounded p-3 mb-3" wire:key="logbook-operasional-{{ $item->id_pertemuan_blok }}">
                    <div class="fw-semibold">
                        {{ $item->materi_rinci_blok?->judul ?: $item->topik ?: 'Pertemuan' }}
                    </div>
                    <div class="text-muted small mb-3">
                        {{ $item->aturan_kegiatan_blok?->jenis_kegiatan?->nama }}
                        &middot; {{ $item->kelompok_blok?->kode }}
                        &middot; {{ $item->tanggal?->format('d/m/Y') ?: 'belum terjadwal' }}
                    </div>
                    <livewire:logbook-pertemuan
                        :pertemuan_blok_id="$item->id_pertemuan_blok"
                        :key="'logbook-operasional-detail-'.$item->id_pertemuan_blok" />
                </div>
            @empty
                <div class="text-muted text-center py-4">Tidak ada pertemuan logbook sesuai filter.</div>
            @endforelse

            {{ $pertemuan->links() }}
        </div>
    </div>
</div>