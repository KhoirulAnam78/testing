<?php

use App\Models\Blok;
use App\Models\PertemuanBlok;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Locked]
    public int $blok_id;

    public string $kegiatan_id = '';

    public string $pertemuan_id = '';

    public string $kelompok_id = '';

    public string $status = '';

    #[Locked]
    public ?int $logbook_pertemuan_id = null;

    public string $logbook_judul = '';

    public string $logbook_konteks = '';

    public function mount($blok_id): void
    {
        $this->blok_id = (int) $blok_id;

        $blok = Blok::select('id')->findOrFail($this->blok_id);

        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);
    }

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
            ->whereHas('aturan_kegiatan_blok', fn ($query) => $query->where('perlu_logbook', true))
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

    public function bukaLogbook(string $id): void
    {
        $pertemuan = PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->whereHas('aturan_kegiatan_blok', fn ($query) => $query->where('perlu_logbook', true))
            ->with([
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
                'kelompok_blok:id_kelompok_blok,kode,nama',
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
            ])
            ->findOrFail((int) $id);

        $this->logbook_pertemuan_id = $pertemuan->id_pertemuan_blok;
        $this->logbook_judul = $pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik ?: 'Pertemuan';
        $this->logbook_konteks = implode(' · ', array_filter([
            $pertemuan->aturan_kegiatan_blok?->jenis_kegiatan?->nama,
            $pertemuan->materi_rinci_blok?->pertemuan_ke
                ? 'Pertemuan '.$pertemuan->materi_rinci_blok->pertemuan_ke
                : null,
            $pertemuan->kelompok_blok?->kode,
            $pertemuan->tanggal?->format('d/m/Y'),
        ]));

        $this->dispatch('show-logbook-modal');
    }

    public function tutupLogbook(): void
    {
        $this->reset(['logbook_pertemuan_id', 'logbook_judul', 'logbook_konteks']);
    }

    public function options()
    {
        $query = PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->whereHas('aturan_kegiatan_blok', fn ($aturan) => $aturan->where('perlu_logbook', true));

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

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jadwal</th>
                            <th>Kegiatan</th>
                            <th>Pertemuan</th>
                            <th>Kelompok</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pertemuan as $item)
                            <tr wire:key="logbook-operasional-{{ $item->id_pertemuan_blok }}">
                                <td>
                                    <div class="fw-semibold">{{ $item->tanggal?->format('d/m/Y') ?: 'Belum terjadwal' }}</div>
                                    @if ($item->jam_mulai || $item->jam_selesai)
                                        <div class="text-muted small">
                                            {{ $item->jam_mulai ? substr($item->jam_mulai, 0, 5) : '' }}{{ $item->jam_selesai ? '–'.substr($item->jam_selesai, 0, 5) : '' }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $item->aturan_kegiatan_blok?->jenis_kegiatan?->nama ?: '-' }}</td>
                                <td>
                                    @if ($item->materi_rinci_blok?->pertemuan_ke)
                                        <span class="badge bg-light text-dark border mb-1">Pertemuan {{ $item->materi_rinci_blok->pertemuan_ke }}</span>
                                    @endif
                                    <div class="fw-semibold">{{ $item->materi_rinci_blok?->judul ?: $item->topik ?: 'Pertemuan' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $item->kelompok_blok?->kode ?: 'Tanpa kelompok' }}</div>
                                    <div class="text-muted small">{{ $item->kelompok_blok?->nama }}</div>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-primary btn-sm"
                                        wire:click="bukaLogbook('{{ $item->id_pertemuan_blok }}')">
                                        <i class="ri-file-list-3-line"></i> Logbook
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Tidak ada pertemuan logbook sesuai filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $pertemuan->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="logbookModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Logbook Pertemuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" wire:click="tutupLogbook"></button>
                </div>
                <div class="modal-body">
                    @if (! $logbook_pertemuan_id)
                        <div class="text-muted">Pilih tombol Logbook pada salah satu pertemuan.</div>
                    @else
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="fw-semibold">{{ $logbook_judul }}</div>
                            <div class="text-muted small">{{ $logbook_konteks }}</div>
                        </div>

                        <livewire:logbook-pertemuan
                            :pertemuan_blok_id="$logbook_pertemuan_id"
                            :key="'logbook-operasional-detail-'.$logbook_pertemuan_id" />
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" wire:click="tutupLogbook">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>