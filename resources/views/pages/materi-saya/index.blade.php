<?php

use App\Models\AnggotaKelompokBlok;
use App\Models\Blok;
use App\Models\LampiranMateriBlok;
use App\Models\PertemuanBlok;
use App\Models\Semester;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portal mahasiswa: materi, modul, dan video untuk pertemuan kelompoknya sendiri.
 *
 * Sepenuhnya read-only. Identitas mahasiswa dibaca ulang dari `auth()` setiap request
 * agar cakupan data tidak pernah bergantung pada payload dari klien.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $blok_id = '';
    public string $semester_id = '';
    public string $cari = '';
    public string $jenis_kegiatan_id = '';

    public function mount(): void
    {
        $this->pastikanAkses();

        if ($this->semester_id === '') {
            $aktif = Semester::query()
                ->where('is_aktif', true)
                ->whereHas('blok.pertemuan_blok', fn ($query) => $query->whereIn('kelompok_blok_id', $this->kelompokIds()))
                ->first();

            $this->semester_id = $aktif ? (string) $aktif->id_semester : '';
        }
    }

    public function updatedBlokId(): void
    {
        $this->resetPage();
    }

    public function updatedSemesterId(): void
    {
        $this->blok_id = '';
        $this->jenis_kegiatan_id = '';
        $this->resetPage();
    }

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedJenisKegiatanId(): void
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
     * @return array<int, int>
     */
    private function pesertaBlokIds(): array
    {
        $mahasiswaId = $this->pastikanAkses();

        return \App\Models\PesertaBlok::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->whereIn('status', ['aktif', 'mengulang'])
            ->whereNull('deleted_at')
            ->pluck('id_peserta_blok')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Berangkat dari `PertemuanBlok` sehingga pertemuan milik kelompok yang sudah
     * dibubarkan (soft delete) otomatis tidak ikut.
     */
    public function pertemuanQuery()
    {
        $pesertaBlokIds = $this->pesertaBlokIds();
        $kata = trim($this->cari);

        return PertemuanBlok::query()
            ->whereIn('kelompok_blok_id', $this->kelompokIds())
            ->when($this->blok_id !== '', fn ($query) => $query->where('blok_id', (int) $this->blok_id))
            ->when($this->semester_id !== '', fn ($query) => $query->whereHas('blok', fn ($q) => $q->where('semester_id', (int) $this->semester_id)))
            ->when($this->jenis_kegiatan_id !== '', fn ($query) => $query->whereHas('aturan_kegiatan_blok', fn ($q) => $q->where('jenis_kegiatan_id', (int) $this->jenis_kegiatan_id)))
            ->when($kata !== '', function ($query) use ($kata) {
                $like = '%'.$kata.'%';
                $query->where(function ($q) use ($like) {
                    $q->whereHas('materi_rinci_blok', fn ($m) => $m->where('judul', 'like', $like))
                        ->orWhere('topik', 'like', $like)
                        ->orWhereHas('blok', fn ($b) => $b->where('kode', 'like', $like)->orWhere('nama', 'like', $like))
                        ->orWhereHas('kelompok_blok', fn ($k) => $k->where('kode', 'like', $like)->orWhere('nama', 'like', $like))
                        ->orWhereHas('dosen_pertemuan_blok.dosen', fn ($d) => $d->where('nama', 'like', $like))
                        ->orWhereHas('lampiran_materi_blok', fn ($l) => $l->where('judul', 'like', $like));
                });
            })
            ->with([
                'blok:id,kode,nama,semester_id',
                'blok.semester:id_semester,nama,tahun',
                'kelompok_blok:id_kelompok_blok,kode,nama',
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
                'aturan_kegiatan_blok:id,jenis_kegiatan_id,perlu_logbook',
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
                'dosen_pertemuan_blok.dosen:id_dosen,nama',
                'presensi_pertemuan_blok' => fn ($q) => $q->whereIn('peserta_blok_id', $pesertaBlokIds),
            ])
            ->orderByRaw('(SELECT pertemuan_ke FROM materi_rinci_blok WHERE materi_rinci_blok.id_materi_rinci_blok = pertemuan_blok.materi_rinci_blok_id AND materi_rinci_blok.deleted_at IS NULL) IS NULL')
            ->orderByRaw('(SELECT pertemuan_ke FROM materi_rinci_blok WHERE materi_rinci_blok.id_materi_rinci_blok = pertemuan_blok.materi_rinci_blok_id AND materi_rinci_blok.deleted_at IS NULL) ASC')
            ->orderByRaw('tanggal IS NULL')
            ->orderBy('tanggal')
            ->orderBy('jam_mulai');
    }

    public function blokOptions()
    {
        return Blok::query()
            ->whereHas('pertemuan_blok', fn ($query) => $query->whereIn('kelompok_blok_id', $this->kelompokIds()))
            ->when($this->semester_id !== '', fn ($query) => $query->where('semester_id', (int) $this->semester_id))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);
    }

    public function semesterOptions()
    {
        return Semester::query()
            ->whereHas('blok.pertemuan_blok', fn ($query) => $query->whereIn('kelompok_blok_id', $this->kelompokIds()))
            ->orderByDesc('is_aktif')
            ->orderByDesc('tahun')
            ->orderBy('nama')
            ->get(['id_semester', 'nama', 'tahun', 'is_aktif']);
    }

    /**
     * Jenis kegiatan diambil dari aturan_kegiatan_blok yang dipakai pertemuan
     * kelompok mahasiswa ini, sehingga tab tidak menampilkan kategori kosong.
     */
    public function jenisKegiatanOptions()
    {
        return \App\Models\JenisKegiatan::query()
            ->whereHas('aturan_kegiatan_blok.pertemuan_blok', fn ($query) => $query->whereIn('kelompok_blok_id', $this->kelompokIds()))
            ->orderBy('nama')
            ->get(['id', 'nama']);
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

    /**
     * @return array{label: string, warna: string, icon: string}
     */
    public function statusKehadiran(?string $status): array
    {
        return match ($status) {
            'hadir' => ['label' => 'Hadir', 'warna' => 'success', 'icon' => 'ri-checkbox-circle-line'],
            'sakit' => ['label' => 'Sakit', 'warna' => 'warning', 'icon' => 'ri-heart-pulse-line'],
            'izin' => ['label' => 'Izin', 'warna' => 'info', 'icon' => 'ri-information-line'],
            'alpa' => ['label' => 'Alpa', 'warna' => 'danger', 'icon' => 'ri-close-circle-line'],
            default => ['label' => 'Belum Tercatat', 'warna' => 'secondary', 'icon' => 'ri-question-line'],
        };
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

    @php
        $blokOptions = $this->blokOptions();
        $semesterOptions = $this->semesterOptions();
        $jenisKegiatanOptions = $this->jenisKegiatanOptions();
        $pertemuanList = $this->pertemuanQuery()->paginate(10);
        $lampiranHalaman = $this->lampiranHalaman($pertemuanList);
    @endphp

    <x-full-page-loading
        target="blok_id,semester_id,jenis_kegiatan_id"
        message="Memuat daftar pertemuan..." />

    <div class="row">
        <div class="col-12">
            <div class="card border-primary-subtle">
                <div class="card-header bg-primary-subtle">
                    <div class="row align-items-center">
                        <div class="col-12">
                            <h5 class="mb-1">Materi Pertemuan Kelompok Saya</h5>
                            <div class="text-muted small">
                                Modul dan video dibagikan oleh pengelola blok serta dosen pengampu kelompok Anda.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-header bg-primary-subtle bg-opacity-10 border-top border-primary-subtle py-3">
                    <div class="row align-items-end g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Cari materi</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input type="text" class="form-control" placeholder="Judul, topik, dosen, blok..."
                                    wire:model.live.debounce.300ms="cari">
                                @if ($cari !== '')
                                    <button type="button" class="btn btn-secondary" wire:click="$set('cari', '')">
                                        <i class="ri-close-line"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Semester</label>
                            <select class="form-select" wire:model.live="semester_id">
                                <option value="">Semua semester</option>
                                @foreach ($semesterOptions as $semester)
                                    <option value="{{ $semester->id_semester }}">
                                        {{ ucfirst($semester->nama) }} {{ $semester->tahun }}@if ($semester->is_aktif) · Aktif
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
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
                @if ($jenisKegiatanOptions->isNotEmpty())
                    <ul class="nav nav-pills nav-tabs-gap px-3 pt-3 pb-0 mb-0 border-bottom" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" role="tab"
                                class="nav-link {{ $jenis_kegiatan_id === '' ? 'active' : '' }}"
                                wire:click="$set('jenis_kegiatan_id', '')">
                                <i class="ri-apps-2-line me-1"></i> Semua
                            </button>
                        </li>
                        @foreach ($jenisKegiatanOptions as $jenis)
                            <li class="nav-item" role="presentation">
                                <button type="button" role="tab"
                                    class="nav-link {{ (string) $jenis_kegiatan_id === (string) $jenis->id ? 'active' : '' }}"
                                    wire:click="$set('jenis_kegiatan_id', '{{ $jenis->id }}')">
                                    <i class="ri-price-tag-3-line me-1"></i> {{ $jenis->nama }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="card-body">
                    @forelse ($pertemuanList as $item)
                        @php
                            $lampiran = $this->lampiranPertemuan($lampiranHalaman, $item);
                            $presensi = $item->presensi_pertemuan_blok->first();
                            $status = $this->statusKehadiran($presensi?->status);
                            $dosenList = $item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->values();
                        @endphp
                        <div class="card border-primary-subtle bg-primary-subtle bg-opacity-10 mb-3" wire:key="materi-saya-{{ $item->id_pertemuan_blok }}">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        @if ($item->materi_rinci_blok?->pertemuan_ke)
                                            <span class="badge bg-primary text-white px-3 py-2 fs-6">
                                                <i class="ri-bookmark-3-line me-1"></i> Pertemuan {{ $item->materi_rinci_blok->pertemuan_ke }}
                                            </span>
                                        @endif
                                        @if ($item->aturan_kegiatan_blok?->jenis_kegiatan?->nama)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2 fs-6">
                                                <i class="ri-price-tag-3-line me-1"></i> {{ $item->aturan_kegiatan_blok->jenis_kegiatan->nama }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2 px-3 py-2 rounded bg-{{ $status['warna'] }}-subtle border border-{{ $status['warna'] }}">
                                        <span class="avatar-sm d-inline-flex align-items-center justify-content-center bg-{{ $status['warna'] }} text-white rounded">
                                            <i class="{{ $status['icon'] }} fs-5"></i>
                                        </span>
                                        <div class="text-start lh-sm">
                                            <div class="text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">STATUS KEHADIRAN</div>
                                            <div class="fw-bold text-{{ $status['warna'] }}">{{ $status['label'] }}</div>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="mb-1">{{ $item->materi_rinci_blok?->judul ?: ($item->topik ?: 'Materi pertemuan') }}</h5>
                                <div class="mb-3 text-muted d-flex flex-wrap align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center">
                                        <i class="ri-book-read-line me-1 text-primary"></i>
                                        <span class="fw-semibold text-body">{{ $item->blok?->kode ?? '-' }} &mdash; {{ $item->blok?->nama ?? 'Blok tidak diketahui' }}</span>
                                    </span>
                                    <span class="text-muted">&middot;</span>
                                    <span class="d-inline-flex align-items-center">
                                        <i class="ri-team-line me-1"></i>
                                        Kelompok {{ $item->kelompok_blok?->kode ?? '-' }}
                                        @if ($item->kelompok_blok?->nama)
                                            <span class="text-muted ms-1">({{ $item->kelompok_blok->nama }})</span>
                                        @endif
                                    </span>
                                    @if ($item->blok?->semester)
                                        <span class="text-muted">&middot;</span>
                                        <span class="d-inline-flex align-items-center">
                                            <i class="ri-calendar-2-line me-1"></i>
                                            {{ ucfirst($item->blok->semester->nama) }} {{ $item->blok->semester->tahun }}
                                        </span>
                                    @endif
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100 bg-light-subtle">
                                            <div class="d-flex align-items-center mb-2 pb-2 border-bottom">
                                                <span class="avatar-xs d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded me-2">
                                                    <i class="ri-user-star-line fs-5"></i>
                                                </span>
                                                <span class="text-uppercase fw-semibold small text-success">Dosen Pengampu</span>
                                            </div>
                                            @if ($dosenList->isNotEmpty())
                                                <ul class="mb-0 ps-3">
                                                    @foreach ($dosenList as $nama)
                                                        <li class="small">{{ $nama }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <div class="text-muted small fst-italic">Belum ada dosen pengampu.</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100 bg-light-subtle">
                                            <div class="d-flex align-items-center mb-2 pb-2 border-bottom">
                                                <span class="avatar-xs d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded me-2">
                                                    <i class="ri-calendar-event-line fs-5"></i>
                                                </span>
                                                <span class="text-uppercase fw-semibold small text-warning">Pelaksanaan</span>
                                            </div>
                                            <div class="small mb-1">
                                                @if ($item->tanggal)
                                                    <i class="ri-calendar-line text-muted me-1"></i> {{ $item->tanggal->translatedFormat('l, d F Y') }}
                                                @else
                                                    <span class="text-warning"><i class="ri-alert-line me-1"></i> jadwal belum ditetapkan</span>
                                                @endif
                                            </div>
                                            @if ($item->jam_mulai)
                                                <div class="small mb-1">
                                                    <i class="ri-time-line text-muted me-1"></i>
                                                    <span class="fw-semibold">{{ $this->formatJam($item->jam_mulai) }}{{ $item->jam_selesai ? ' - '.$this->formatJam($item->jam_selesai) : '' }}</span>
                                                </div>
                                            @endif
                                            @if ($item->ruangan)
                                                <div class="small">
                                                    <i class="ri-map-pin-line text-muted me-1"></i> {{ $item->ruangan }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if ($item->aturan_kegiatan_blok?->perlu_logbook)
                                    <div class="border-top mt-3 pt-3">
                                        <h6 class="mb-3"><i class="ri-file-list-3-line"></i> Logbook Saya</h6>
                                        <livewire:logbook-pertemuan
                                            :pertemuan_blok_id="$item->id_pertemuan_blok"
                                            :key="'logbook-mahasiswa-'.$item->id_pertemuan_blok" />
                                    </div>
                                @endif

                                <div class="mt-3">
                                    <div class="text-muted small text-uppercase fw-semibold mb-2">
                                        <i class="ri-attachment-line"></i> Modul &amp; Video
                                    </div>
                                    @if ($lampiran->isEmpty())
                                        <div class="text-muted small"><i class="ri-information-line"></i> Belum ada modul atau video untuk pertemuan ini.</div>
                                    @else
                                        <div class="list-group list-group-flush">
                                            @foreach ($lampiran as $tautan)
                                                <div class="list-group-item px-3 py-2 mb-2 border rounded d-flex flex-wrap align-items-center justify-content-between gap-2" wire:key="tautan-{{ $tautan->id_lampiran_materi_blok }}">
                                                    <div class="me-auto">
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
                                                    </div>
                                                    <a href="{{ $tautan->url }}" target="_blank" rel="noopener nofollow"
                                                        class="btn btn-soft-primary btn-sm">
                                                        <i class="ri-external-link-line"></i> Buka
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
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
