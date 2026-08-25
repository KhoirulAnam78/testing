<?php

use App\Models\AnggotaKelompokBlok;
use App\Models\Blok;
use App\Models\LampiranMateriBlok;
use App\Models\PertemuanBlok;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portal mahasiswa: materi, modul, dan video untuk pertemuan kelompoknya sendiri.
 *
 * Sepenuhnya read-only. Identitas mahasiswa dibaca ulang dari `auth()` setiap request
 * agar cakupan data tidak pernah bergantung pada payload dari klien.
 */
new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $blok_id = '';

    public function mount(): void
    {
        $this->pastikanAkses();
    }

    public function updatedBlokId(): void
    {
        $this->resetPage();
    }

    /**
     * `mahasiswa.user_id` nullable, jadi user berrole mahasiswa belum tentu punya
     * baris mahasiswa yang bisa dipakai.
     */
    private function pastikanAkses(): int
    {
        abort_unless(auth()->user()?->can('materi-saya:'), 403);

        $mahasiswa = auth()->user()?->mahasiswa;

        abort_unless($mahasiswa, 403, 'Akun ini belum terhubung ke data mahasiswa.');

        return (int) $mahasiswa->id_mahasiswa;
    }

    /**
     * Kelompok yang diikuti mahasiswa ini, hanya selama kepesertaan bloknya berjalan.
     * Join manual tidak memakai global scope soft delete `peserta_blok`, jadi
     * `deleted_at` disaring eksplisit.
     *
     * @return array<int, int>
     */
    private function kelompokIds(): array
    {
        $mahasiswaId = $this->pastikanAkses();

        return AnggotaKelompokBlok::query()
            ->join('peserta_blok', 'peserta_blok.id_peserta_blok', '=', 'anggota_kelompok_blok.peserta_blok_id')
            ->where('peserta_blok.mahasiswa_id', $mahasiswaId)
            ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
            ->whereNull('peserta_blok.deleted_at')
            ->pluck('anggota_kelompok_blok.kelompok_blok_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Berangkat dari `PertemuanBlok` sehingga pertemuan milik kelompok yang sudah
     * dibubarkan (soft delete) otomatis tidak ikut.
     */
    public function pertemuanQuery()
    {
        return PertemuanBlok::query()
            ->whereIn('kelompok_blok_id', $this->kelompokIds())
            ->when($this->blok_id !== '', fn ($query) => $query->where('blok_id', (int) $this->blok_id))
            ->with([
                'blok:id,kode,nama',
                'kelompok_blok:id_kelompok_blok,kode,nama',
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
                'aturan_kegiatan_blok:id,jenis_kegiatan_id',
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama,perlu_logbook',
                'dosen_pertemuan_blok.dosen:id_dosen,nama',
            ])
            ->orderByRaw('tanggal IS NULL')
            ->orderBy('tanggal')
            ->orderBy('jam_mulai');
    }

    public function blokOptions()
    {
        return Blok::query()
            ->whereHas('pertemuan_blok', fn ($query) => $query->whereIn('kelompok_blok_id', $this->kelompokIds()))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);
    }

    /**
     * Semua lampiran aktif untuk pertemuan pada halaman ini diambil sekali, lalu
     * dibagi per pertemuan di Blade. Menghindari satu query per baris.
     */
    public function lampiranHalaman($pertemuanList)
    {
        $materiIds = collect($pertemuanList->items())->pluck('materi_rinci_blok_id')->unique()->values();
        $pertemuanIds = collect($pertemuanList->items())->pluck('id_pertemuan_blok')->values();

        if ($materiIds->isEmpty()) {
            return collect();
        }

        return LampiranMateriBlok::query()
            ->aktif()
            ->whereIn('materi_rinci_blok_id', $materiIds->all())
            ->where(fn ($query) => $query
                ->whereNull('pertemuan_blok_id')
                ->orWhereIn('pertemuan_blok_id', $pertemuanIds->all()))
            ->orderByRaw('pertemuan_blok_id IS NULL DESC')
            ->orderBy('jenis')
            ->orderBy('urutan')
            ->orderBy('id_lampiran_materi_blok')
            ->get();
    }

    /**
     * Lampiran default materi ditambah lampiran khusus kelompok pertemuan ini.
     */
    public function lampiranPertemuan($semua, PertemuanBlok $pertemuan)
    {
        return $semua->filter(
            fn (LampiranMateriBlok $lampiran) => (int) $lampiran->materi_rinci_blok_id === (int) $pertemuan->materi_rinci_blok_id
                && ($lampiran->pertemuan_blok_id === null
                    || (int) $lampiran->pertemuan_blok_id === (int) $pertemuan->id_pertemuan_blok)
        );
    }

    public function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }
}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Materi &amp; Modul</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Portal Saya</a></li>
                    <li class="breadcrumb-item active">Materi &amp; Modul</li>
                </ol>
            </div>
        </div>
    </div>

    @php($blokOptions = $this->blokOptions())
    @php($pertemuanList = $this->pertemuanQuery()->paginate(10))
    @php($lampiranHalaman = $this->lampiranHalaman($pertemuanList))

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <h5 class="mb-1">Materi Pertemuan Kelompok Saya</h5>
                            <div class="text-muted small">
                                Modul dan video dibagikan oleh pengelola blok serta dosen pengampu kelompok Anda.
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Blok</label>
                            <select class="form-select" wire:model.live="blok_id">
                                <option value="">Semua blok</option>
                                @foreach ($blokOptions as $blok)
                                    <option value="{{ $blok->id }}">{{ $blok->kode }} - {{ $blok->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @forelse ($pertemuanList as $item)
                        @php($lampiran = $this->lampiranPertemuan($lampiranHalaman, $item))
                        <div class="border rounded p-3 mb-3" wire:key="materi-saya-{{ $item->id_pertemuan_blok }}">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div>
                                    <div class="fw-semibold">
                                        @if ($item->materi_rinci_blok?->pertemuan_ke)
                                            <span class="badge bg-light text-dark border">Pertemuan {{ $item->materi_rinci_blok->pertemuan_ke }}</span>
                                        @endif
                                        {{ $item->materi_rinci_blok?->judul ?: $item->topik }}
                                    </div>
                                    <div class="text-muted small mt-1">
                                        {{ $item->blok?->kode }} - {{ $item->blok?->nama }}
                                        &middot; {{ $item->aturan_kegiatan_blok?->jenis_kegiatan?->nama }}
                                        &middot; Kelompok {{ $item->kelompok_blok?->kode }}
                                    </div>
                                    <div class="text-muted small">
                                        @if ($item->tanggal)
                                            {{ $item->tanggal->format('d/m/Y') }}
                                        @else
                                            <span class="text-warning">jadwal belum ditetapkan</span>
                                        @endif
                                        @if ($item->jam_mulai)
                                            &middot; {{ $this->formatJam($item->jam_mulai) }}{{ $item->jam_selesai ? '-'.$this->formatJam($item->jam_selesai) : '' }}
                                        @endif
                                        @if ($item->ruangan)
                                            &middot; {{ $item->ruangan }}
                                        @endif
                                    </div>
                                    @php($dosen = $item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->join(', '))
                                    @if ($dosen !== '')
                                        <div class="text-muted small">Pengampu: {{ $dosen }}</div>
                                    @endif
                                </div>
                            </div>

                            @if ($item->aturan_kegiatan_blok?->jenis_kegiatan?->perlu_logbook)
                                <div class="border-top mt-3 pt-3">
                                    <h6><i class="ri-file-list-3-line"></i> Logbook Saya</h6>
                                    <livewire:logbook-pertemuan
                                        :pertemuan_blok_id="$item->id_pertemuan_blok"
                                        :key="'logbook-mahasiswa-'.$item->id_pertemuan_blok" />
                                </div>
                            @endif

                            @if ($lampiran->isEmpty())
                                <div class="text-muted small mt-2">
                                    <i class="ri-information-line"></i>
                                    Belum ada modul atau video untuk pertemuan ini.
                                </div>
                            @else
                                <div class="list-group list-group-flush mt-2">
                                    @foreach ($lampiran as $tautan)
                                        <div class="list-group-item px-0 py-2" wire:key="tautan-{{ $tautan->id_lampiran_materi_blok }}">
                                            <div class="small">
                                                @if ($tautan->jenis === 'video')
                                                    <span class="badge bg-danger-subtle text-danger"><i class="ri-video-line"></i> Video</span>
                                                @else
                                                    <span class="badge bg-primary-subtle text-primary"><i class="ri-links-line"></i> Modul</span>
                                                @endif
                                                <span class="fw-semibold">{{ $tautan->judul }}</span>
                                            </div>
                                            @if ($tautan->deskripsi)
                                                <div class="text-muted small mt-1">{{ $tautan->deskripsi }}</div>
                                            @endif
                                            <div class="small mt-1">
                                                <a href="{{ $tautan->url }}" target="_blank" rel="noopener nofollow"
                                                    class="btn btn-soft-primary btn-sm">
                                                    <i class="ri-external-link-line"></i> Buka
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted text-center py-4">
                            Anda belum terdaftar di kelompok blok manapun, atau kelompok Anda belum punya jadwal pertemuan.
                        </div>
                    @endforelse

                    <div class="mt-3">{{ $pertemuanList->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
