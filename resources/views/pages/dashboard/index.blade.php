<?php

use App\Models\Blok;
use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\PertemuanBlok;
use App\Models\PresensiPertemuanBlok;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public array $stats = [];

    public ?string $jenisDashboard = null;

    public $profil = null;

    public $semesterAktif = null;

    public $blokAktif = null;

    public Collection $agenda;

    public Collection $riwayat;

    public array $hariKalender = [];

    #[Locked]
    public string $bulanAktif;

    #[Locked]
    public string $tanggalTerpilih;

    #[Locked]
    public bool $modalDetailTerbuka = false;

    public int $agendaHariIni = 0;

    public int $perluTindakLanjut = 0;

    public int $pertemuanSelesai = 0;

    public int $pertemuanHadir = 0;

    public function mount(): void
    {
        $this->agenda = collect();
        $this->riwayat = collect();
        $this->bulanAktif = today()->format('Y-m');
        $this->tanggalTerpilih = today()->toDateString();
        $user = auth()->user();
        $this->jenisDashboard = match (true) {
            $user->hasRole('mahasiswa') => 'mahasiswa',
            $user->hasRole('dosen') => 'dosen',
            default => null,
        };

        if ($this->jenisDashboard === 'mahasiswa') {
            $this->muatDashboardMahasiswa();

            return;
        }

        if ($this->jenisDashboard === 'dosen') {
            $this->muatDashboardDosen();

            return;
        }

        $this->muatDashboardPengelola();
    }

    private function muatDashboardMahasiswa(): void
    {
        $this->profil = auth()->user()->mahasiswa()->with('prodi')->first();

        if (! $this->profil) {
            return;
        }

        $pesertaAktif = fn ($query) => $query
            ->where('mahasiswa_id', $this->profil->id_mahasiswa)
            ->whereIn('status', ['aktif', 'mengulang']);

        $query = PertemuanBlok::query()
            ->whereHas('kelompok_blok.anggota_kelompok_blok.peserta_blok', $pesertaAktif)
            ->whereNotNull('tanggal')
            ->whereDate('tanggal', '<', today())
            ->where('status', '!=', 'batal');

        $pesertaBlokIds = $this->profil->peserta_blok()->whereIn('status', ['aktif', 'mengulang'])->pluck('id_peserta_blok');

        $this->pertemuanSelesai = (clone $query)->count();
        $this->pertemuanHadir = PresensiPertemuanBlok::query()
            ->whereIn('peserta_blok_id', $pesertaBlokIds)
            ->whereIn('status', PresensiPertemuanBlok::STATUS_HADIR)
            ->whereHas('pertemuan_blok', fn ($q) => $q->whereNotNull('tanggal')->whereDate('tanggal', '<', today())->where('status', '!=', 'batal'))
            ->count();
        $this->riwayat = $this->agendaMahasiswa($query, $pesertaBlokIds);
        $this->muatKalender();
        $this->stats = [
            ['label' => 'Blok Diikuti', 'value' => $this->profil->peserta_blok()->whereIn('status', ['aktif', 'mengulang'])->count()],
            ['label' => 'Pertemuan Selesai', 'value' => $this->pertemuanSelesai],
            ['label' => 'Kehadiran', 'value' => $this->pertemuanSelesai > 0 ? round(($this->pertemuanHadir / $this->pertemuanSelesai) * 100).'%' : '0%'],
        ];
    }

    private function muatDashboardDosen(): void
    {
        $this->profil = auth()->user()->dosen()->with('prodi')->first();

        if (! $this->profil) {
            return;
        }

        $milikDosen = fn ($query) => $query->where('dosen_id', $this->profil->id_dosen);
        $query = PertemuanBlok::query()
            ->whereHas('dosen_pertemuan_blok', $milikDosen)
            ->whereNotNull('tanggal')
            ->whereDate('tanggal', '>=', today())
            ->where('status', '!=', 'batal');

        $this->agendaHariIni = (clone $query)->whereDate('tanggal', today())->count();
        $this->perluTindakLanjut = PertemuanBlok::query()
            ->whereHas('dosen_pertemuan_blok', $milikDosen)
            ->whereDate('tanggal', '<=', today())
            ->where('status', '!=', 'batal')
            ->whereDoesntHave('monitoring_pertemuan_blok')
            ->count();
        $this->muatKalender();
        $this->stats = [
            ['label' => 'Mengajar Hari Ini', 'value' => $this->agendaHariIni],
            ['label' => 'Perlu Jurnal', 'value' => $this->perluTindakLanjut],
        ];
    }

    public function bulanSebelumnya(): void
    {
        $this->ubahBulan($this->bulan()->subMonthNoOverflow());
    }

    public function bulanBerikutnya(): void
    {
        $this->ubahBulan($this->bulan()->addMonthNoOverflow());
    }

    public function keHariIni(): void
    {
        $this->modalDetailTerbuka = false;
        $this->bulanAktif = today()->format('Y-m');
        $this->tanggalTerpilih = today()->toDateString();
        $this->muatKalender();
    }

    public function pilihTanggal(string $tanggal): void
    {
        $tanggal = $this->tanggal($tanggal);

        if (! $tanggal) {
            $this->modalDetailTerbuka = false;

            return;
        }

        $this->modalDetailTerbuka = false;
        $this->bulanAktif = $tanggal->format('Y-m');
        $this->tanggalTerpilih = $tanggal->toDateString();
        $this->muatKalender();
    }

    public function bukaDetailTanggal(string $tanggal): void
    {
        if (! $this->tanggal($tanggal)) {
            $this->modalDetailTerbuka = false;

            return;
        }

        $this->pilihTanggal($tanggal);

        $this->modalDetailTerbuka = $this->agenda->contains(
            fn (PertemuanBlok $item) => $item->tanggal->toDateString() === $this->tanggalTerpilih
        );
    }

    public function tutupDetailTanggal(): void
    {
        $this->modalDetailTerbuka = false;
    }

    private function ubahBulan(Carbon $bulan): void
    {
        $this->modalDetailTerbuka = false;
        $this->bulanAktif = $bulan->format('Y-m');
        $this->tanggalTerpilih = $bulan->startOfMonth()->toDateString();
        $this->muatKalender();
    }

    private function bulan(): Carbon
    {
        $bulan = $this->tanggal("{$this->bulanAktif}-01");

        return $bulan ?: today()->startOfMonth();
    }

    private function tanggal(string $tanggal): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return null;
        }

        try {
            $hasil = Carbon::createFromFormat('!Y-m-d', $tanggal);
        } catch (Throwable) {
            return null;
        }

        return $hasil->format('Y-m-d') === $tanggal && $hasil->year >= 1900 && $hasil->year <= 2100
            ? $hasil
            : null;
    }

    private function muatKalender(): void
    {
        if ($this->jenisDashboard && ! $this->profil) {
            return;
        }

        $bulan = $this->bulan();
        $awalGrid = $bulan->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $akhirGrid = $bulan->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $this->hariKalender = collect(Carbon::parse($awalGrid)->daysUntil($akhirGrid))
            ->map(fn (Carbon $tanggal) => [
                'tanggal' => $tanggal->toDateString(),
                'hari' => $tanggal->format('j'),
                'bulan_aktif' => $tanggal->month === $bulan->month,
                'hari_ini' => $tanggal->isToday(),
            ])->all();

        $user = auth()->user();
        $query = PertemuanBlok::query()
            ->with([
                'blok:id,kode,nama',
                'kelompok_blok:id_kelompok_blok,kode,nama',
                'materi_rinci_blok:id_materi_rinci_blok,judul',
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
                'dosen_pertemuan_blok.dosen:id_dosen,nama',
                'monitoring_pertemuan_blok:id_monitoring_pertemuan_blok,pertemuan_blok_id,tanggal_realisasi',
            ])
            ->whereNotNull('tanggal')
            ->whereBetween('tanggal', [$awalGrid->toDateString(), $akhirGrid->toDateString()]);

        if ($user->hasRole('mahasiswa')) {
            $mahasiswaId = $user->mahasiswa?->id_mahasiswa;
            $query->whereHas('kelompok_blok.anggota_kelompok_blok.peserta_blok', fn ($q) => $q
                ->where('mahasiswa_id', $mahasiswaId)
                ->whereIn('status', ['aktif', 'mengulang']));
        } elseif ($user->hasRole('dosen')) {
            $query->whereHas('dosen_pertemuan_blok', fn ($q) => $q->where('dosen_id', $user->dosen?->id_dosen));
        }

        $this->agenda = $query
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get();
    }

    private function agendaMahasiswa($query, $pesertaBlokIds): Collection
    {
        $pertemuan = $query->with([
            'blok:id,kode,nama',
            'kelompok_blok:id_kelompok_blok,kode,nama',
            'materi_rinci_blok:id_materi_rinci_blok,judul',
            'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
            'dosen_pertemuan_blok.dosen:id_dosen,nama',
            'presensi_pertemuan_blok' => fn ($q) => $q->whereIn('peserta_blok_id', $pesertaBlokIds),
        ])
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_mulai')
            ->limit(10)
            ->get();

        return $pertemuan;
    }

    private function muatDashboardPengelola(): void
    {
        $this->semesterAktif = Semester::where('is_aktif', true)->first();
        $this->blokAktif = Blok::with(['prodi', 'mata_kuliah'])->where('status', 'aktif')->latest('id')->limit(5)->get();
        $this->muatKalender();
        $this->stats = [
            ['label' => 'Program Studi', 'value' => Prodi::count(), 'icon' => 'ri-building-4-line', 'color' => 'primary'],
            ['label' => 'Dosen', 'value' => Dosen::count(), 'icon' => 'ri-user-star-line', 'color' => 'success'],
            ['label' => 'Mahasiswa', 'value' => Mahasiswa::count(), 'icon' => 'ri-group-line', 'color' => 'info'],
            ['label' => 'Mata Kuliah', 'value' => MataKuliah::count(), 'icon' => 'ri-book-open-line', 'color' => 'warning'],
            ['label' => 'Blok Akademik', 'value' => Blok::count(), 'icon' => 'ri-mind-map', 'color' => 'primary'],
            ['label' => 'Jenis Kegiatan', 'value' => JenisKegiatan::count(), 'icon' => 'ri-list-check-3', 'color' => 'success'],
        ];
    }
}; ?>

@push('styles')
    <style>
        .dashboard-welcome { background: linear-gradient(120deg, #064e3b, #047857); color: #fff; }
        .dashboard-welcome .text-muted { color: rgba(255, 255, 255, .72) !important; }
        .dashboard-shortcut { display: flex; align-items: center; gap: .85rem; min-height: 82px; padding: 1rem; color: inherit; background: var(--vz-card-bg); border: 1px solid var(--vz-border-color); border-radius: .3rem; box-shadow: var(--vz-box-shadow-sm); cursor: pointer; transition: color .2s ease, background-color .2s ease, border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
        .dashboard-shortcut:hover, .dashboard-shortcut:focus-visible { color: var(--vz-primary); background: var(--vz-primary-bg-subtle); border-color: var(--vz-primary); box-shadow: 0 .25rem .75rem rgba(var(--vz-primary-rgb), .18); transform: translateY(-2px); }
        .dashboard-shortcut:focus-visible { outline: 3px solid rgba(var(--vz-primary-rgb), .25); outline-offset: 2px; }
        .dashboard-shortcut i { display: grid; width: 42px; height: 42px; place-items: center; color: var(--vz-primary); background: var(--vz-primary-bg-subtle); border-radius: .3rem; font-size: 1.3rem; }
        .dashboard-agenda-date { width: 54px; flex: 0 0 54px; padding: .5rem .25rem; text-align: center; background: var(--vz-primary-bg-subtle); border-radius: .3rem; }
        .dashboard-agenda-date strong { display: block; color: var(--vz-primary); font-size: 1.15rem; line-height: 1; }
        .dashboard-calendar-weekdays, .dashboard-calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
        .dashboard-calendar-weekdays { color: var(--vz-secondary-color); font-size: .75rem; font-weight: 600; text-align: center; text-transform: uppercase; }
        .dashboard-calendar-weekdays > div { padding: .65rem .25rem; border: 1px solid var(--vz-border-color); border-bottom: 0; }
        .dashboard-calendar-day { min-width: 0; min-height: 112px; padding: .45rem; color: inherit; background: var(--vz-card-bg); border: 1px solid var(--vz-border-color); text-align: left; }
        .dashboard-calendar-day:hover, .dashboard-calendar-day.is-selected { color: inherit; border-color: var(--vz-primary); }
        .dashboard-calendar-day.is-outside { color: var(--vz-secondary-color); background: var(--vz-tertiary-bg); }
        .dashboard-calendar-day.has-agenda { background: var(--vz-primary-bg-subtle); box-shadow: inset 0 4px 0 var(--vz-primary); }
        .dashboard-calendar-day.has-agenda:hover, .dashboard-calendar-day.has-agenda.is-selected { background: var(--vz-primary-bg-subtle); }
        .dashboard-calendar-number { display: grid; width: 1.75rem; height: 1.75rem; place-items: center; border-radius: 50%; font-size: 1.1rem; font-weight: 600; }
        .dashboard-calendar-day.is-today .dashboard-calendar-number { color: #fff; background: var(--vz-primary); }
        .dashboard-calendar-item { display: block; margin-top: .3rem; padding-left: .4rem; overflow: hidden; border-left: 3px solid var(--vz-secondary); font-size: .72rem; text-overflow: ellipsis; white-space: nowrap; }
        .dashboard-calendar-count { display: none; }
        @media (max-width: 767.98px) {
            .dashboard-calendar-day { min-height: 62px; padding: .25rem; text-align: center; }
            .dashboard-calendar-item { display: none; }
            .dashboard-calendar-count { display: inline-block; margin-top: .2rem; }
            .dashboard-calendar-weekdays { font-size: .65rem; }
        }
    </style>
@endpush

<div>
    <x-full-page-loading
        target="bulanSebelumnya,bulanBerikutnya,keHariIni,pilihTanggal,bukaDetailTanggal"
        message="Memuat kalender..."
    />

    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">{{ $jenisDashboard ? 'Dashboard Saya' : 'Dashboard Akademik' }}</h4>
            <p class="text-muted mb-0 mt-1">Sistem Blok Fakultas Kedokteran UIN Jambi</p>
        </div>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item active">Dashboard</li></ol>
    </div>

    @if ($jenisDashboard)
        <div class="card dashboard-welcome border-0">
            <div class="card-body p-4">
                <small class="text-uppercase">Portal {{ ucfirst($jenisDashboard) }}</small>
                <h2 class="text-white mt-2 mb-1">Halo, {{ $profil?->nama ?? auth()->user()->name }}</h2>
                <p class="text-muted mb-0">{{ $jenisDashboard === 'mahasiswa' ? 'Materi, pengampu, tanggal pelaksanaan, dan kehadiran Anda dalam satu tempat.' : 'Agenda mengajar dan tindak lanjut pertemuan Anda.' }}</p>
            </div>
        </div>

        @unless ($profil)
            <div class="alert alert-warning" role="alert">Akun belum terhubung ke data {{ $jenisDashboard }}. Hubungi pengelola akademik.</div>
        @endunless

        <div class="row g-3 mb-4">
            @foreach ($stats as $stat)
                <div class="col-6 col-lg-3">
                    <div class="card h-100 mb-0"><div class="card-body"><p class="text-muted mb-2">{{ $stat['label'] }}</p><h3 class="mb-0">{{ $stat['value'] }}</h3></div></div>
                </div>
            @endforeach
            @if ($profil)
                <div class="col-12 col-lg-6">
                    <div class="card h-100 mb-0"><div class="card-body"><p class="text-muted mb-2">Identitas</p><h6 class="mb-1">{{ $profil->prodi?->nama ?? 'Program studi belum diatur' }}</h6><span>{{ $jenisDashboard === 'mahasiswa' ? "NIM {$profil->nim} · Angkatan {$profil->angkatan}" : 'NIDN '.($profil->nidn ?: '-') }}</span></div></div>
                </div>
            @endif
        </div>

        <div class="row g-3 mb-4">
            @if ($jenisDashboard === 'mahasiswa')
                @can('materi-saya:')
                    <div class="col-md-6 col-xl-4"><a href="{{ route('materi-saya.index') }}" wire:navigate class="dashboard-shortcut text-decoration-none"><i class="ri-book-open-line"></i><span><strong class="d-block">Materi & Modul</strong><small class="text-muted">Bahan belajar dan logbook</small></span></a></div>
                @endcan
            @else
                @can('pertemuan-saya:')
                    <div class="col-md-6 col-xl-4"><a href="{{ route('pertemuan-saya.index') }}" wire:navigate class="dashboard-shortcut text-decoration-none bg-primary text-white"><i class="ri-calendar-check-line"></i><span><strong class="d-block">Pertemuan Saya</strong><small class="text-white">Presensi, jurnal, nilai, dan modul</small></span></a></div>
                @endcan
            @endif
            <div class="col-md-6 col-xl-4"><a href="{{ route('profile') }}" wire:navigate class="dashboard-shortcut text-decoration-none bg-primary text-white"><i class="ri-user-settings-line"></i><span><strong class="d-block">Profil & Akun</strong><small class="text-white">Profil dan kata sandi</small></span></a></div>
        </div>
    @else
        <div class="card border-0">
            <div class="card-body p-4">
                <span class="badge bg-primary-subtle text-primary mb-3">Pusat Pengelolaan</span>
                <h2>Ruang kerja operasional pembelajaran sistem blok.</h2>
                <p class="text-muted mb-0">Pantau fondasi akademik dan kelola operasional melalui menu samping.</p>
                <p class="mt-3 mb-0"><strong>Semester Aktif:</strong> {{ $semesterAktif ? ucfirst($semesterAktif->nama).' '.$semesterAktif->tahun : 'Belum diatur' }}</p>
            </div>
        </div>
        <div class="row g-3 mb-4">
            @foreach ($stats as $stat)
                <div class="col-6 col-lg-4"><div class="card h-100 mb-0"><div class="card-body d-flex align-items-center justify-content-between"><div><p class="text-muted mb-2">{{ $stat['label'] }}</p><h4 class="mb-0">{{ number_format($stat['value'], 0, ',', '.') }}</h4></div><span class="avatar-sm"><span class="avatar-title rounded bg-{{ $stat['color'] }}-subtle text-{{ $stat['color'] }}"><i class="{{ $stat['icon'] }}"></i></span></span></div></div></div>
            @endforeach
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Blok Aktif</h5></div>
            <div class="card-body">
                @forelse ($blokAktif as $blok)
                    <div class="py-2 {{ ! $loop->last ? 'border-bottom' : '' }}"><strong>{{ $blok->nama }}</strong><div class="text-muted small">{{ $blok->prodi?->nama ?? 'Prodi belum diatur' }}</div></div>
                @empty
                    <p class="text-muted mb-0">Belum ada blok aktif.</p>
                @endforelse
            </div>
        </div>
    @endif

        <div class="card">
                <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h5 class="card-title mb-1">{{ match ($jenisDashboard) { 'mahasiswa' => 'Kalender Perkuliahan', 'dosen' => 'Kalender Mengajar', default => 'Kalender Akademik' } }}</h5>
                        <p class="text-muted mb-0">{{ match ($jenisDashboard) { 'mahasiswa' => 'Jadwal pertemuan kelompok Anda, termasuk perubahan status kegiatan.', 'dosen' => 'Jadwal lampau dan mendatang yang terkait dengan Anda.', default => 'Semua jadwal pertemuan dari seluruh blok.' } }}</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-light btn-icon" wire:click="bulanSebelumnya" wire:loading.attr="disabled" aria-label="Bulan sebelumnya"><i class="ri-arrow-left-s-line"></i></button>
                        <strong class="text-capitalize px-2">{{ $this->bulan()->translatedFormat('F Y') }}</strong>
                        <button type="button" class="btn btn-light btn-icon" wire:click="bulanBerikutnya" wire:loading.attr="disabled" aria-label="Bulan berikutnya"><i class="ri-arrow-right-s-line"></i></button>
                        <button type="button" class="btn btn-outline-primary" wire:click="keHariIni" wire:loading.attr="disabled">Hari Ini</button>
                        <label class="visually-hidden" for="tanggal-kalender">Pilih tanggal</label>
                        <input id="tanggal-kalender" type="date" class="form-control w-auto" value="{{ $tanggalTerpilih }}" wire:change="pilihTanggal($event.target.value)" min="1900-01-01" max="2100-12-31">
                    </div>
                </div>
                <div class="card-body">
                    @php $agendaPerTanggal = $agenda->groupBy(fn ($item) => $item->tanggal->toDateString()); @endphp
                    <div class="dashboard-calendar-weekdays" aria-hidden="true">
                        @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $namaHari)
                            <div>{{ $namaHari }}</div>
                        @endforeach
                    </div>
                    <div class="dashboard-calendar-grid" aria-label="Kalender {{ $this->bulan()->translatedFormat('F Y') }}">
                        @foreach ($hariKalender as $hari)
                            @php $agendaHari = $agendaPerTanggal->get($hari['tanggal'], collect()); @endphp
                            <button type="button"
                                wire:key="kalender-{{ $hari['tanggal'] }}"
                                wire:click="bukaDetailTanggal('{{ $hari['tanggal'] }}')"
                                class="dashboard-calendar-day {{ ! $hari['bulan_aktif'] ? 'is-outside' : '' }} {{ $hari['hari_ini'] ? 'is-today' : '' }} {{ $agendaHari->isNotEmpty() ? 'has-agenda' : '' }} {{ $tanggalTerpilih === $hari['tanggal'] ? 'is-selected' : '' }}"
                                aria-label="{{ Carbon::parse($hari['tanggal'])->translatedFormat('l, d F Y') }}, {{ $agendaHari->count() }} agenda"
                                aria-pressed="{{ $tanggalTerpilih === $hari['tanggal'] ? 'true' : 'false' }}">
                                <span class="dashboard-calendar-number">{{ $hari['hari'] }}</span>
                                @foreach ($agendaHari->take(3) as $item)
                                    <span class="dashboard-calendar-item">{{ $item->jam_mulai ? substr($item->jam_mulai, 0, 5).' · ' : '' }}{{ $item->materi_rinci_blok?->judul ?? $item->topik ?? 'Pertemuan blok' }}</span>
                                @endforeach
                                @if ($agendaHari->count() > 3)
                                    <span class="dashboard-calendar-item">+{{ $agendaHari->count() - 3 }} lainnya</span>
                                @endif
                                @if ($agendaHari->isNotEmpty())
                                    <span class="dashboard-calendar-count badge bg-primary-subtle text-primary">{{ $agendaHari->count() }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                </div>
            </div>

            @if ($modalDetailTerbuka)
                <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="modal-detail-agenda-title" wire:click.self="tutupDetailTanggal" wire:keydown.escape.window="tutupDetailTanggal">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title" id="modal-detail-agenda-title">Detail Kegiatan</h5>
                                    <p class="text-muted text-capitalize mb-0">{{ Carbon::parse($tanggalTerpilih)->translatedFormat('l, d F Y') }}</p>
                                </div>
                                <button type="button" class="btn-close" wire:click="tutupDetailTanggal" aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body">
                                @foreach ($agendaPerTanggal->get($tanggalTerpilih, collect()) as $item)
                                    <div class="d-flex gap-3 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}" wire:key="detail-agenda-{{ $item->id_pertemuan_blok }}">
                                        <div>
                                            <h6 class="mb-1">{{ $item->materi_rinci_blok?->judul ?? $item->topik ?? 'Pertemuan blok' }}</h6>
                                            <div class="text-muted small">{{ $item->blok?->kode }} - {{ $item->blok?->nama }} · {{ $item->kelompok_blok?->kode }}</div>
                                            @if ($jenisDashboard !== 'dosen' && ($pengampu = $item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->join(', ')))
                                                <div class="text-muted small">Pengampu: {{ $pengampu }}</div>
                                            @endif
                                            <div class="small mt-1"><i class="ri-time-line"></i> {{ $item->jam_mulai ? substr($item->jam_mulai, 0, 5) : 'Waktu belum diatur' }}@if ($item->ruangan) · {{ $item->ruangan }}@endif</div>
                                            @if ($item->monitoring_pertemuan_blok?->tanggal_realisasi)
                                                <div class="small mt-1"><i class="ri-calendar-check-line"></i> Realisasi: {{ $item->monitoring_pertemuan_blok->tanggal_realisasi->translatedFormat('d F Y') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" wire:click="tutupDetailTanggal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-backdrop fade show" wire:click="tutupDetailTanggal" aria-hidden="true"></div>
        @endif

        @if ($jenisDashboard === 'mahasiswa')
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-1">Daftar Pertemuan Terlaksana</h5>
                    <p class="text-muted mb-0">Materi, pengampu, tanggal pelaksanaan, dan status kehadiran Anda.</p>
                </div>
                <div class="card-body p-0">
                    @forelse ($riwayat as $item)
                        <div class="d-flex gap-3 p-3 {{ ! $loop->last ? 'border-bottom' : '' }}" wire:key="agenda-{{ $item->id_pertemuan_blok }}">
                            <div class="dashboard-agenda-date"><strong>{{ $item->tanggal->format('d') }}</strong><small>{{ $item->tanggal->translatedFormat('M') }}</small></div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $item->materi_rinci_blok?->judul ?? $item->topik ?? 'Pertemuan blok' }}</h6>
                                <div class="text-muted small">{{ $item->blok?->kode }} - {{ $item->blok?->nama }} · {{ $item->kelompok_blok?->kode }}</div>
                                @if ($pengampu = $item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->join(', '))
                                    <div class="text-muted small">Pengampu: {{ $pengampu }}</div>
                                @endif
                                @php $presensi = $item->presensi_pertemuan_blok->first(); @endphp
                                <div class="mt-2">
                                    @if ($presensi)
                                        @php
                                            $warna = match ($presensi->status) {
                                                'hadir' => 'success',
                                                'sakit', 'izin' => 'warning',
                                                'alpa' => 'danger',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $warna }}-subtle text-{{ $warna }}"><i class="ri-user-check-line"></i> Kehadiran: {{ ucfirst($presensi->status) }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary"><i class="ri-question-line"></i> Kehadiran belum tercatat</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-5"><i class="ri-calendar-check-line d-block fs-2 mb-2"></i>Belum ada pertemuan terlaksana.</div>
                    @endforelse
                </div>
            </div>
        @endif
</div>