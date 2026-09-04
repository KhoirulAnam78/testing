<?php

use App\Models\Blok;
use App\Models\JenisKegiatan;
use App\Models\PertemuanBlok;
use App\Models\Semester;
use App\Support\AksesPertemuanBlok;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portal dosen: daftar pertemuan yang diampu user yang login, dengan pengelolaan
 * tautan modul dan video per pertemuan.
 *
 * Identitas dosen selalu dibaca ulang dari `auth()` setiap request, tidak disimpan
 * sebagai properti komponen, supaya cakupan data tidak pernah bergantung pada
 * payload yang dikirim klien.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    /**
     * Tab yang tersedia pada modal pelaksanaan. Dipakai untuk memvalidasi mode yang dikirim
     * klien, baik saat membuka modal maupun saat berpindah tab.
     *
     * @var array<int, string>
     */
    public const MODE_PELAKSANAAN = ['pelaksanaan', 'nilai', 'logbook'];

    #[Url(as: 'semester')]
    public string $semester_id = '';

    #[Url(as: 'blok')]
    public string $blok_id = '';

    #[Url(as: 'kegiatan')]
    public string $jenis_kegiatan_id = '';

    #[Url(as: 'status')]
    public string $status_monitoring = '';

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $pertemuan_terpilih = null;

    public ?int $materi_rinci_terpilih = null;

    public string $modul_judul = '';

    public ?int $pelaksanaan_pertemuan_id = null;

    public string $pelaksanaan_mode = 'pelaksanaan';

    public string $pelaksanaan_judul = '';

    public string $pelaksanaan_konteks = '';

    public string $pelaksanaan_jadwal = '';

    public bool $pelaksanaan_perlu_penilaian = false;

    public bool $pelaksanaan_perlu_logbook = false;

    public bool $validasi_setelah_simpan = false;

    public bool $jurnal_tersimpan = false;

    public bool $presensi_tersimpan = false;

    public function mount(): void
    {
        $this->pastikanAkses();

        if (! request()->query->has('semester')) {
            $this->semester_id = $this->semesterAktifId();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBlokId(): void
    {
        $this->resetPage();
    }

    public function updatedSemesterId(): void
    {
        $this->blok_id = '';
        $this->resetPage();
    }

    public function updatedJenisKegiatanId(): void
    {
        $this->resetPage();
    }

    public function updatedStatusMonitoring(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['blok_id', 'jenis_kegiatan_id', 'status_monitoring', 'search']);
        $this->semester_id = $this->semesterAktifId();
        $this->resetPage();
    }

    private function semesterAktifId(): string
    {
        return (string) (Semester::where('is_aktif', true)->value('id_semester') ?? '');
    }

    /**
     * `dosen.user_id` nullable dan `Dosen` memakai soft delete, jadi user berrole
     * dosen belum tentu punya baris dosen yang bisa dipakai.
     */
    private function pastikanAkses(): int
    {
        abort_unless(auth()->user()?->can('pertemuan-saya:'), 403);

        $dosen = auth()->user()?->dosen;

        abort_unless($dosen, 403, 'Akun ini belum terhubung ke data dosen.');

        return (int) $dosen->id_dosen;
    }

    /**
     * Filter dan urutan dikerjakan di SQL karena satu dosen bisa mengampu banyak
     * pertemuan di beberapa blok sekaligus. Query berangkat dari `PertemuanBlok`
     * sehingga pertemuan yang sudah di-soft-delete otomatis tidak ikut, walaupun
     * baris `dosen_pertemuan_blok`-nya masih ada (tabel itu tanpa soft delete).
     */
    public function pertemuanQuery()
    {
        $dosenId = $this->pastikanAkses();

        return PertemuanBlok::query()
            ->select('pertemuan_blok.*')
            ->leftJoin('blok as urut_blok', 'urut_blok.id', '=', 'pertemuan_blok.blok_id')
            ->leftJoin('aturan_kegiatan_blok as urut_kegiatan', 'urut_kegiatan.id', '=', 'pertemuan_blok.aturan_kegiatan_blok_id')
            ->leftJoin('jenis_kegiatan as urut_jenis', 'urut_jenis.id', '=', 'urut_kegiatan.jenis_kegiatan_id')
            ->leftJoin('materi_rinci_blok as urut_materi', 'urut_materi.id_materi_rinci_blok', '=', 'pertemuan_blok.materi_rinci_blok_id')
            ->leftJoin('kelompok_blok as urut_kelompok', 'urut_kelompok.id_kelompok_blok', '=', 'pertemuan_blok.kelompok_blok_id')
            ->whereHas('dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->when($this->semester_id !== '', fn ($query) => $query->whereHas(
                'blok',
                fn ($blok) => $blok->where('semester_id', (int) $this->semester_id)
            ))
            ->when($this->blok_id !== '', fn ($query) => $query->where('pertemuan_blok.blok_id', (int) $this->blok_id))
            ->when($this->jenis_kegiatan_id !== '', fn ($query) => $query->whereHas(
                'aturan_kegiatan_blok',
                fn ($aturan) => $aturan->where('jenis_kegiatan_id', (int) $this->jenis_kegiatan_id)
            ))
            ->when($this->status_monitoring === 'perlu_diisi', fn ($query) => $query->where(function ($status) {
                $status->whereDoesntHave('monitoring_pertemuan_blok')
                    ->orWhere(function ($presensi) {
                        $presensi->whereHas('aturan_kegiatan_blok', fn ($aturan) => $aturan->where('perlu_presensi', true))
                            ->whereDoesntHave('presensi_pertemuan_blok');
                    });
            }))
            ->when($this->status_monitoring === 'belum_validasi', fn ($query) => $query->whereHas(
                'monitoring_pertemuan_blok',
                fn ($monitoring) => $monitoring->whereNull('divalidasi_pada')
            ))
            ->when($this->status_monitoring === 'tervalidasi', fn ($query) => $query->whereHas(
                'monitoring_pertemuan_blok',
                fn ($monitoring) => $monitoring->whereNotNull('divalidasi_pada')
            ))
            ->when(trim($this->search) !== '', function ($query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function ($inner) use ($search) {
                    $inner->where('pertemuan_blok.topik', 'like', $search)
                        ->orWhere('pertemuan_blok.ruangan', 'like', $search)
                        ->orWhereHas('materi_rinci_blok', fn ($materi) => $materi->where('judul', 'like', $search))
                        ->orWhereHas('kelompok_blok', fn ($kelompok) => $kelompok
                            ->where('kode', 'like', $search)
                            ->orWhere('nama', 'like', $search))
                        ->orWhereHas('blok', fn ($blok) => $blok
                            ->where('kode', 'like', $search)
                            ->orWhere('nama', 'like', $search));
                });
            })
            ->with([
                'blok:id,kode,nama,semester_id',
                'kelompok_blok' => fn ($query) => $query
                    ->select('id_kelompok_blok', 'kode', 'nama')
                    ->withCount('anggota_kelompok_blok'),
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
                'aturan_kegiatan_blok' => fn ($query) => $query
                    ->select('id', 'jenis_kegiatan_id', 'perlu_presensi', 'perlu_penilaian', 'perlu_logbook')
                    ->withCount('komponen_penilaian_blok'),
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
                'monitoring_pertemuan_blok',
            ])
            ->withCount([
                'lampiran_materi_blok',
                'presensi_pertemuan_blok as presensi_hadir_count' => fn ($query) => $query->where('status', 'hadir'),
                'presensi_pertemuan_blok as presensi_tercatat_count',
                'nilai_pertemuan_blok as nilai_tercatat_count',
            ])
            ->orderBy('urut_blok.nama')
            ->orderBy('urut_jenis.nama')
            ->orderByRaw('urut_materi.pertemuan_ke IS NULL')
            ->orderBy('urut_materi.pertemuan_ke')
            ->orderBy('urut_kelompok.kode')
            ->orderByRaw('pertemuan_blok.tanggal IS NULL')
            ->orderBy('pertemuan_blok.tanggal')
            ->orderBy('pertemuan_blok.jam_mulai')
            ->orderBy('pertemuan_blok.id_pertemuan_blok');
    }

    public function blokOptions()
    {
        $dosenId = $this->pastikanAkses();

        return Blok::query()
            ->whereHas('pertemuan_blok.dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->when($this->semester_id !== '', fn ($query) => $query->where('semester_id', (int) $this->semester_id))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);
    }

    public function semesterOptions()
    {
        $dosenId = $this->pastikanAkses();

        return Semester::query()
            ->whereHas('blok.pertemuan_blok.dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->orderByDesc('tahun')
            ->orderBy('nama')
            ->get(['id_semester', 'nama', 'tahun', 'is_aktif']);
    }

    public function jenisKegiatanOptions()
    {
        $dosenId = $this->pastikanAkses();

        return JenisKegiatan::query()
            ->whereHas('aturan_kegiatan_blok.pertemuan_blok.dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->orderBy('nama')
            ->get(['id', 'nama']);
    }

    public function kelolaModul(string $id): void
    {
        $dosenId = $this->pastikanAkses();

        $pertemuan = PertemuanBlok::query()
            ->whereHas('dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->with([
                'kelompok_blok:id_kelompok_blok,kode',
                'materi_rinci_blok:id_materi_rinci_blok,judul',
            ])
            ->findOrFail((int) $id);

        $this->pertemuan_terpilih = $pertemuan->id_pertemuan_blok;
        $this->materi_rinci_terpilih = (int) $pertemuan->materi_rinci_blok_id;
        $this->modul_judul = trim(
            ($pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik ?: 'Materi')
            .' - '.($pertemuan->kelompok_blok?->kode ?: '')
        );

        $this->dispatch('show-modul-pertemuan-modal');
    }

    public function tutupModul(): void
    {
        $this->reset(['pertemuan_terpilih', 'materi_rinci_terpilih', 'modul_judul']);
    }

    /**
     * Badge jumlah tautan dihitung ulang setiap komponen lampiran menyimpan sesuatu.
     */
    #[On('lampiran-materi-tersimpan')]
    public function refreshLampiran(): void
    {
        //
    }

    /**
     * Presensi dan monitoring disatukan sebagai pelaksanaan, sedangkan nilai dan logbook
     * tetap menjadi proses terpisah dalam modal yang sama.
     */
    public function kelolaPelaksanaan(string $id, string $mode = 'pelaksanaan'): void
    {
        $dosenId = $this->pastikanAkses();

        $pertemuan = PertemuanBlok::query()
            ->whereHas('dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->with([
                'kelompok_blok:id_kelompok_blok,kode',
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
                'blok:id,kode,nama',
                'aturan_kegiatan_blok:id,jenis_kegiatan_id,perlu_penilaian,perlu_logbook',
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
            ])
            ->findOrFail((int) $id);

        $perluPenilaian = (bool) $pertemuan->aturan_kegiatan_blok?->perlu_penilaian;
        $perluLogbook = (bool) $pertemuan->aturan_kegiatan_blok?->perlu_logbook;
        $modeTersedia = ['pelaksanaan'];

        if ($perluPenilaian) {
            $modeTersedia[] = 'nilai';
        }

        if ($perluLogbook) {
            $modeTersedia[] = 'logbook';
        }

        $this->pelaksanaan_pertemuan_id = $pertemuan->id_pertemuan_blok;
        $this->pelaksanaan_mode = in_array($mode, $modeTersedia, true) ? $mode : 'pelaksanaan';
        $this->pelaksanaan_perlu_penilaian = $perluPenilaian;
        $this->pelaksanaan_perlu_logbook = $perluLogbook;
        $this->pelaksanaan_judul = $pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik ?: 'Pertemuan';
        $this->pelaksanaan_konteks = implode(' · ', array_filter([
            $pertemuan->aturan_kegiatan_blok?->jenis_kegiatan?->nama,
            $pertemuan->materi_rinci_blok?->pertemuan_ke
                ? 'Pertemuan '.$pertemuan->materi_rinci_blok->pertemuan_ke
                : null,
            $pertemuan->blok?->kode,
            $pertemuan->kelompok_blok?->kode,
        ]));
        $this->pelaksanaan_jadwal = implode(' · ', array_filter([
            $pertemuan->tanggal?->format('d/m/Y'),
            implode('–', array_filter([$this->formatJam($pertemuan->jam_mulai), $this->formatJam($pertemuan->jam_selesai)])),
            $pertemuan->ruangan,
        ]));

        $this->dispatch('show-pelaksanaan-modal');
    }

    public function setPelaksanaanMode(string $mode): void
    {
        if (
            $mode === 'pelaksanaan'
            || ($mode === 'nilai' && $this->pelaksanaan_perlu_penilaian)
            || ($mode === 'logbook' && $this->pelaksanaan_perlu_logbook)
        ) {
            $this->pelaksanaan_mode = $mode;
        }
    }

    public function tutupPelaksanaan(): void
    {
        $this->resetStateSimpanPelaksanaan();
        $this->reset([
            'pelaksanaan_pertemuan_id',
            'pelaksanaan_mode',
            'pelaksanaan_judul',
            'pelaksanaan_konteks',
            'pelaksanaan_jadwal',
            'pelaksanaan_perlu_penilaian',
            'pelaksanaan_perlu_logbook',
        ]);
    }

    public function simpanPelaksanaan(): void
    {
        abort_unless(
            $this->pelaksanaan_pertemuan_id
                && AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $this->pelaksanaan_pertemuan_id),
            403
        );

        $this->resetStateSimpanPelaksanaan();
        $this->dispatch(
            'simpan-pelaksanaan',
            pertemuan_blok_id: $this->pelaksanaan_pertemuan_id
        );
    }

    public function validasiPelaksanaan(): void
    {
        abort_unless(
            $this->pelaksanaan_pertemuan_id
                && AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $this->pelaksanaan_pertemuan_id),
            403
        );

        $this->resetStateSimpanPelaksanaan();
        $this->validasi_setelah_simpan = true;
        $this->dispatch(
            'simpan-pelaksanaan',
            pertemuan_blok_id: $this->pelaksanaan_pertemuan_id
        );
    }

    #[On('presensi-pertemuan-tersimpan')]
    public function tandaiPresensiTersimpan(): void
    {
        $this->presensi_tersimpan = true;
        $this->lanjutkanValidasi();
    }

    #[On('jurnal-pertemuan-tersimpan')]
    public function tandaiJurnalTersimpan(): void
    {
        $this->jurnal_tersimpan = true;
        $this->lanjutkanValidasi();
    }

    private function lanjutkanValidasi(): void
    {
        if (
            $this->validasi_setelah_simpan
            && $this->jurnal_tersimpan
            && $this->presensi_tersimpan
            && $this->pelaksanaan_pertemuan_id
        ) {
            $this->validasi_setelah_simpan = false;
            $this->dispatch(
                'validasi-pelaksanaan',
                pertemuan_blok_id: $this->pelaksanaan_pertemuan_id
            );
        }
    }

    private function resetStateSimpanPelaksanaan(): void
    {
        $this->reset(['validasi_setelah_simpan', 'jurnal_tersimpan', 'presensi_tersimpan']);
    }

    /**
     * Badge hadir/total dan status validasi dihitung di query, jadi halaman perlu
     * ikut segar setiap presensi, jurnal, atau nilai disimpan.
     */
    #[On('nilai-pertemuan-tersimpan')]
    public function refreshPelaksanaan(): void
    {
        //
    }

    public function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }
}; ?>

<div>
    <x-full-page-loading target="kelolaModul,kelolaPelaksanaan" message="Memuat data pertemuan..." />

    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Pertemuan Saya</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Portal Saya</a></li>
                    <li class="breadcrumb-item active">Pertemuan Saya</li>
                </ol>
            </div>
        </div>
    </div>

    @php($semesterOptions = $this->semesterOptions())
    @php($blokOptions = $this->blokOptions())
    @php($jenisKegiatanOptions = $this->jenisKegiatanOptions())
    @php($pertemuanList = $this->pertemuanQuery()->paginate(15))

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-1">Jadwal &amp; Materi yang Saya Ampu</h5>
                    <div class="text-muted small">
                        Lengkapi tautan modul dan video untuk setiap pertemuan. Tautan bertanda
                        <span class="fw-semibold">dari materi</span> disiapkan pengelola dan berlaku untuk semua kelompok.
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4 col-xl-2">
                            <label class="form-label" for="filter-semester">Semester</label>
                            <select id="filter-semester" class="form-select" wire:model.live="semester_id">
                                <option value="">Semua semester</option>
                                @foreach ($semesterOptions as $semester)
                                    <option value="{{ $semester->id_semester }}">
                                        {{ ucfirst($semester->nama) }} {{ $semester->tahun }}{{ $semester->is_aktif ? ' (aktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-xl-2">
                            <label class="form-label">Blok</label>
                            <select class="form-select" wire:model.live="blok_id">
                                <option value="">Semua blok</option>
                                @foreach ($blokOptions as $blok)
                                    <option value="{{ $blok->id }}">{{ $blok->kode }} - {{ $blok->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-xl-2">
                            <label class="form-label">Jenis Kegiatan</label>
                            <select class="form-select" wire:model.live="jenis_kegiatan_id">
                                <option value="">Semua kegiatan</option>
                                @foreach ($jenisKegiatanOptions as $jenisKegiatan)
                                    <option value="{{ $jenisKegiatan->id }}">{{ $jenisKegiatan->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-xl-2">
                            <label class="form-label">Status Monitoring</label>
                            <select class="form-select" wire:model.live="status_monitoring">
                                <option value="">Semua status</option>
                                <option value="perlu_diisi">Perlu diisi</option>
                                <option value="belum_validasi">Belum divalidasi</option>
                                <option value="tervalidasi">Tervalidasi</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Cari</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input type="search" class="form-control" placeholder="Materi, blok, kelompok..."
                                    wire:model.live.debounce.400ms="search">
                                <button type="button" class="btn btn-secondary" wire:click="resetFilters" title="Reset filter">
                                    <i class="ri-refresh-line"></i><span class="visually-hidden">Reset filter</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Jadwal / Realisasi</th>
                                    <th>Pertemuan</th>
                                    <th>Kelompok / Kegiatan</th>
                                    <th>Kelengkapan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pertemuanList as $item)
                                    <tr wire:key="pertemuan-saya-{{ $item->id_pertemuan_blok }}">
                                        <td>
                                            <div class="small text-muted">Rencana</div>
                                            <div class="fw-semibold">{{ $item->tanggal?->format('d/m/Y') ?: 'Belum dijadwalkan' }}</div>
                                            @if ($item->jam_mulai || $item->jam_selesai)
                                                <div class="small text-muted">
                                                    {{ $this->formatJam($item->jam_mulai) }}{{ $item->jam_selesai ? '–'.$this->formatJam($item->jam_selesai) : '' }}
                                                    {{ $item->ruangan ? ' · '.$item->ruangan : '' }}
                                                </div>
                                            @endif
                                            @if ($item->monitoring_pertemuan_blok?->tanggal_realisasi)
                                                <div class="small text-muted mt-2">Realisasi</div>
                                                <div class="small fw-semibold text-success">
                                                    {{ $item->monitoring_pertemuan_blok->tanggal_realisasi->format('d/m/Y') }}
                                                    @if ($item->monitoring_pertemuan_blok->jam_mulai_realisasi)
                                                        · {{ $this->formatJam($item->monitoring_pertemuan_blok->jam_mulai_realisasi) }}
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column align-items-start gap-1">
                                                @if ($item->materi_rinci_blok?->pertemuan_ke)
                                                    <span class="badge bg-light text-dark border">Pertemuan {{ $item->materi_rinci_blok->pertemuan_ke }}</span>
                                                @endif
                                                <div class="small fw-semibold text-wrap">
                                                    {{ $item->materi_rinci_blok?->judul ?: $item->topik }}
                                                </div>
                                                <div class="text-muted small">{{ $item->blok?->kode }} · {{ $item->blok?->nama }}</div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info">{{ $item->aturan_kegiatan_blok?->jenis_kegiatan?->nama }}</span>
                                            <div class="small fw-semibold mt-1">{{ $item->kelompok_blok?->kode ?: 'Tanpa kelompok' }}</div>
                                            @if ($item->kelompok_blok?->nama)
                                                <div class="text-muted small">{{ $item->kelompok_blok->nama }}</div>
                                            @endif
                                            @if ($item->kelompok_blok?->anggota_kelompok_blok_count)
                                                <div class="text-muted small">{{ $item->kelompok_blok->anggota_kelompok_blok_count }} mahasiswa</div>
                                            @endif
                                        </td>
                                        <td>
                                            @php($jurnal = $item->monitoring_pertemuan_blok)
                                            <div class="d-flex justify-content-between gap-3 small mb-1">
                                                <span>Monitoring</span>
                                                @if (! $jurnal)
                                                    <span class="badge bg-warning-subtle text-warning">belum diisi</span>
                                                @elseif ($jurnal->divalidasi_pada)
                                                    <span class="badge bg-success-subtle text-success">tervalidasi</span>
                                                @else
                                                    <span class="badge bg-info-subtle text-info">belum divalidasi</span>
                                                @endif
                                            </div>
                                            <div class="d-flex justify-content-between gap-3 small mb-1">
                                                <span>Presensi</span>
                                                @if (! $item->aturan_kegiatan_blok?->perlu_presensi)
                                                    <span class="text-muted">tidak perlu</span>
                                                @elseif (! $item->presensi_tercatat_count)
                                                    <span class="badge bg-warning-subtle text-warning">belum diisi</span>
                                                @else
                                                    <span class="fw-semibold">{{ $item->presensi_hadir_count }}/{{ $item->kelompok_blok?->anggota_kelompok_blok_count ?? $item->presensi_tercatat_count }} hadir</span>
                                                @endif
                                            </div>
                                            <div class="d-flex justify-content-between gap-3 small">
                                                <span>Nilai</span>
                                                @if (! $item->aturan_kegiatan_blok?->perlu_penilaian)
                                                    <span class="text-muted">tidak dinilai</span>
                                                @else
                                                    @php($komponenCount = (int) ($item->aturan_kegiatan_blok?->komponen_penilaian_blok_count ?? 0))
                                                    @php($selTarget = $komponenCount * (int) ($item->kelompok_blok?->anggota_kelompok_blok_count ?? 0))
                                                    @if ($komponenCount === 0)
                                                        <span class="badge bg-danger-subtle text-danger">rubrik kosong</span>
                                                    @elseif (! $item->nilai_tercatat_count)
                                                        <span class="badge bg-warning-subtle text-warning">belum diisi</span>
                                                    @elseif ($selTarget > 0 && $item->nilai_tercatat_count < $selTarget)
                                                        <span class="badge bg-warning-subtle text-warning">{{ $item->nilai_tercatat_count }}/{{ $selTarget }}</span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success">lengkap</span>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-primary btn-sm"
                                                wire:click="kelolaModul('{{ $item->id_pertemuan_blok }}')">
                                                <i class="ri-links-line"></i> Modul
                                                @if ($item->lampiran_materi_blok_count > 0)
                                                    <span class="badge bg-light text-primary ms-1"
                                                        title="tautan khusus kelompok ini">{{ $item->lampiran_materi_blok_count }}</span>
                                                @endif
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm mt-1"
                                                wire:click="kelolaPelaksanaan('{{ $item->id_pertemuan_blok }}', 'pelaksanaan')">
                                                <i class="ri-booklet-line"></i> {{ $jurnal?->divalidasi_pada ? 'Lihat Monitoring' : 'Isi Monitoring' }}
                                            </button>
                                            @if ($item->aturan_kegiatan_blok?->perlu_penilaian)
                                                <button type="button" class="btn btn-info btn-sm mt-1"
                                                    wire:click="kelolaPelaksanaan('{{ $item->id_pertemuan_blok }}', 'nilai')">
                                                    <i class="ri-graduation-cap-line"></i> Nilai
                                                </button>
                                            @endif
                                            @if ($item->aturan_kegiatan_blok?->perlu_logbook)
                                                <button type="button" class="btn btn-warning btn-sm mt-1"
                                                    wire:click="kelolaPelaksanaan('{{ $item->id_pertemuan_blok }}', 'logbook')">
                                                    <i class="ri-file-list-3-line"></i> Logbook
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            Tidak ada pertemuan yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">{{ $pertemuanList->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modulPertemuanModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tautan Modul &amp; Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" wire:click="tutupModul"></button>
                </div>
                <div class="modal-body">
                    @if (! $pertemuan_terpilih || ! $materi_rinci_terpilih)
                        <div class="text-muted">Pilih tombol Kelola pada salah satu pertemuan.</div>
                    @else
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="text-muted small">Pertemuan</div>
                            <div class="fw-semibold">{{ $modul_judul }}</div>
                        </div>

                        <livewire:blok-operasional.lampiran-materi
                            :materi_rinci_blok_id="$materi_rinci_terpilih"
                            :pertemuan_blok_id="$pertemuan_terpilih"
                            :key="'lampiran-saya-'.$pertemuan_terpilih" />
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" wire:click="tutupModul">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pelaksanaanModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Monitoring Pertemuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" wire:click="tutupPelaksanaan"></button>
                </div>
                <div class="modal-body">
                    @if (! $pelaksanaan_pertemuan_id)
                        <div class="text-muted">Pilih aksi monitoring pada salah satu pertemuan.</div>
                    @else
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="fw-semibold">{{ $pelaksanaan_judul }}</div>
                            <div class="text-muted small">{{ $pelaksanaan_konteks }}</div>
                            @if ($pelaksanaan_jadwal)
                                <div class="text-muted small mt-1"><i class="ri-calendar-line"></i> {{ $pelaksanaan_jadwal }}</div>
                            @endif
                        </div>

                        <ul class="nav nav-pills mb-3">
                            <li class="nav-item">
                                <button type="button"
                                    class="nav-link {{ $pelaksanaan_mode === 'pelaksanaan' ? 'active' : '' }}"
                                    wire:click="setPelaksanaanMode('pelaksanaan')">
                                    <i class="ri-calendar-check-line"></i> Monitoring
                                </button>
                            </li>
                            @if ($pelaksanaan_perlu_penilaian)
                                <li class="nav-item">
                                    <button type="button"
                                        class="nav-link {{ $pelaksanaan_mode === 'nilai' ? 'active' : '' }}"
                                        wire:click="setPelaksanaanMode('nilai')">
                                        <i class="ri-graduation-cap-line"></i> Nilai
                                    </button>
                                </li>
                            @endif
                            @if ($pelaksanaan_perlu_logbook)
                                <li class="nav-item">
                                    <button type="button"
                                        class="nav-link {{ $pelaksanaan_mode === 'logbook' ? 'active' : '' }}"
                                        wire:click="setPelaksanaanMode('logbook')">
                                        <i class="ri-file-list-3-line"></i> Logbook
                                    </button>
                                </li>
                            @endif
                        </ul>

                        @if ($pelaksanaan_mode === 'nilai')
                            <livewire:blok-operasional.nilai-pertemuan
                                :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                :key="'nilai-saya-'.$pelaksanaan_pertemuan_id" />
                        @elseif ($pelaksanaan_mode === 'logbook')
                            <livewire:logbook-pertemuan
                                :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                :key="'logbook-saya-'.$pelaksanaan_pertemuan_id" />
                        @else
                            <div class="mb-4">
                                <h6 class="mb-3"><i class="ri-booklet-line"></i> Monitoring</h6>
                                <livewire:blok-operasional.jurnal-pertemuan
                                    :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                    :tampilkan_tombol_simpan="false"
                                    :tampilkan_tombol_validasi="false"
                                    :key="'jurnal-saya-'.$pelaksanaan_pertemuan_id" />
                            </div>

                            <div class="border-top pt-4">
                                <h6 class="mb-3"><i class="ri-user-follow-line"></i> Presensi</h6>
                                <livewire:blok-operasional.presensi-pertemuan
                                    :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                    :tampilkan_tombol_simpan="false"
                                    :key="'presensi-saya-'.$pelaksanaan_pertemuan_id" />
                            </div>

                        @endif
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" wire:click="tutupPelaksanaan">Tutup</button>
                    @if (
                        $pelaksanaan_pertemuan_id
                        && $pelaksanaan_mode === 'pelaksanaan'
                        && AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $pelaksanaan_pertemuan_id)
                    )
                        <button type="button" class="btn btn-primary"
                            wire:click="simpanPelaksanaan"
                            wire:loading.attr="disabled"
                            wire:target="simpanPelaksanaan,validasiPelaksanaan">
                            <i class="ri-save-line"></i> Simpan
                        </button>
                        <button type="button" class="btn btn-success"
                            wire:click="validasiPelaksanaan"
                            wire:confirm="Validasi pertemuan ini? Presensi dan monitoring akan terkunci dan tidak dapat diubah lagi."
                            wire:loading.attr="disabled"
                            wire:target="simpanPelaksanaan,validasiPelaksanaan">
                            <i class="ri-shield-check-line"></i> Simpan &amp; Validasi
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const getModal = (modalId) => {
                const element = document.getElementById(modalId);

                if (! element || ! window.bootstrap?.Modal) {
                    return null;
                }

                return window.bootstrap.Modal.getOrCreateInstance(element);
            };

            const bind = (modalId, showEvent, hideEvent) => {
                document.addEventListener(showEvent, () => {
                    setTimeout(() => getModal(modalId)?.show(), 50);
                });

                document.addEventListener(hideEvent, () => {
                    getModal(modalId)?.hide();
                });
            };

            bind('modulPertemuanModal', 'show-modul-pertemuan-modal', 'hide-modul-pertemuan-modal');
            bind('pelaksanaanModal', 'show-pelaksanaan-modal', 'hide-pelaksanaan-modal');
        })();
    </script>
@endpush
