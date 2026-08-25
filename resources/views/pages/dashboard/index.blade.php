<?php

use App\Models\Blok;
use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\Mahasiswa;
use App\Models\MataKuliah;
use App\Models\PertemuanBlok;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public array $stats = [];
    public ?string $jenisDashboard = null;
    public $profil = null;
    public $semesterAktif = null;
    public $blokAktif = null;
    public Collection $agenda;
    public int $agendaHariIni = 0;
    public int $perluTindakLanjut = 0;

    public function mount(): void
    {
        $this->agenda = collect();
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

        $query = PertemuanBlok::query()
            ->whereHas('kelompok_blok.anggota_kelompok_blok.peserta_blok', fn ($query) => $query
                ->where('mahasiswa_id', $this->profil->id_mahasiswa)
                ->whereIn('status', ['aktif', 'mengulang']))
            ->whereNotNull('tanggal')
            ->whereDate('tanggal', '>=', today())
            ->where('status', '!=', 'batal');

        $this->agendaHariIni = (clone $query)->whereDate('tanggal', today())->count();
        $this->agenda = $this->agenda($query, true);
        $this->stats = [
            ['label' => 'Blok Diikuti', 'value' => $this->profil->peserta_blok()->whereIn('status', ['aktif', 'mengulang'])->count()],
            ['label' => 'Agenda Hari Ini', 'value' => $this->agendaHariIni],
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
        $this->agenda = $this->agenda($query);
        $this->stats = [
            ['label' => 'Mengajar Hari Ini', 'value' => $this->agendaHariIni],
            ['label' => 'Perlu Jurnal', 'value' => $this->perluTindakLanjut],
        ];
    }

    private function agenda($query, bool $denganDosen = false): Collection
    {
        $with = [
            'blok:id,kode,nama',
            'kelompok_blok:id_kelompok_blok,kode,nama',
            'materi_rinci_blok:id_materi_rinci_blok,judul',
            'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
        ];

        if ($denganDosen) {
            $with[] = 'dosen_pertemuan_blok.dosen:id_dosen,nama';
        }

        return $query->with($with)->orderBy('tanggal')->orderBy('jam_mulai')->limit(5)->get();
    }

    private function muatDashboardPengelola(): void
    {
        $this->semesterAktif = Semester::where('is_aktif', true)->first();
        $this->blokAktif = Blok::with(['prodi', 'mata_kuliah'])->where('status', 'aktif')->latest('id')->limit(5)->get();
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
        .dashboard-shortcut { display: flex; align-items: center; gap: .85rem; min-height: 82px; padding: 1rem; color: inherit; border: 1px solid var(--vz-border-color); border-radius: .3rem; }
        .dashboard-shortcut:hover { color: var(--vz-primary); border-color: var(--vz-primary); transform: translateY(-2px); }
        .dashboard-shortcut i { display: grid; width: 42px; height: 42px; place-items: center; color: var(--vz-primary); background: var(--vz-primary-bg-subtle); border-radius: .3rem; font-size: 1.3rem; }
        .dashboard-agenda-date { width: 54px; flex: 0 0 54px; padding: .5rem .25rem; text-align: center; background: var(--vz-primary-bg-subtle); border-radius: .3rem; }
        .dashboard-agenda-date strong { display: block; color: var(--vz-primary); font-size: 1.15rem; line-height: 1; }
    </style>
@endpush

<div>
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
                <p class="text-muted mb-0">{{ $jenisDashboard === 'mahasiswa' ? 'Jadwal, materi, dan kebutuhan belajar Anda dalam satu tempat.' : 'Agenda mengajar dan tindak lanjut pertemuan Anda.' }}</p>
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
                    <div class="col-md-6 col-xl-4"><a href="{{ route('pertemuan-saya.index') }}" wire:navigate class="dashboard-shortcut text-decoration-none"><i class="ri-calendar-check-line"></i><span><strong class="d-block">Pertemuan Saya</strong><small class="text-muted">Presensi, jurnal, nilai, dan modul</small></span></a></div>
                @endcan
            @endif
            <div class="col-md-6 col-xl-4"><a href="{{ route('profile') }}" wire:navigate class="dashboard-shortcut text-decoration-none"><i class="ri-user-settings-line"></i><span><strong class="d-block">Profil & Akun</strong><small class="text-muted">Profil dan kata sandi</small></span></a></div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-1">Agenda Terdekat</h5><p class="text-muted mb-0">Maksimal lima pertemuan yang terkait dengan Anda.</p></div>
            <div class="card-body p-0">
                @forelse ($agenda as $item)
                    <div class="d-flex gap-3 p-3 {{ ! $loop->last ? 'border-bottom' : '' }}" wire:key="agenda-{{ $item->id_pertemuan_blok }}">
                        <div class="dashboard-agenda-date"><strong>{{ $item->tanggal->format('d') }}</strong><small>{{ $item->tanggal->translatedFormat('M') }}</small></div>
                        <div>
                            <h6 class="mb-1">{{ $item->materi_rinci_blok?->judul ?? $item->topik ?? 'Pertemuan blok' }}</h6>
                            <div class="text-muted small">{{ $item->blok?->kode }} - {{ $item->blok?->nama }} · {{ $item->kelompok_blok?->kode }}</div>
                            <div class="small mt-1"><i class="ri-time-line"></i> {{ $item->jam_mulai ? substr($item->jam_mulai, 0, 5) : 'Waktu belum diatur' }}@if ($item->ruangan) · {{ $item->ruangan }}@endif</div>
                            @if ($jenisDashboard === 'mahasiswa' && ($pengampu = $item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->join(', ')))<div class="text-muted small">Pengampu: {{ $pengampu }}</div>@endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5"><i class="ri-calendar-check-line d-block fs-2 mb-2"></i>Belum ada agenda terjadwal.</div>
                @endforelse
            </div>
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
                    <div class="py-2 {{ ! $loop->last ? 'border-bottom' : '' }}"><strong>{{ $blok->kode }} - {{ $blok->nama }}</strong><div class="text-muted small">{{ $blok->prodi?->nama ?? 'Prodi belum diatur' }}</div></div>
                @empty
                    <p class="text-muted mb-0">Belum ada blok aktif.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>