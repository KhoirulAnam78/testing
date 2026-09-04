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
    public int $pertemuanSelesai = 0;
    public int $pertemuanHadir = 0;

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
        $this->agenda = $this->agendaMahasiswa($query, $pesertaBlokIds);
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

<?php $__env->startPush('styles'); ?>
    <style>
        .dashboard-welcome { background: linear-gradient(120deg, #064e3b, #047857); color: #fff; }
        .dashboard-welcome .text-muted { color: rgba(255, 255, 255, .72) !important; }
        .dashboard-shortcut { display: flex; align-items: center; gap: .85rem; min-height: 82px; padding: 1rem; color: inherit; border: 1px solid var(--vz-border-color); border-radius: .3rem; }
        .dashboard-shortcut:hover { color: var(--vz-primary); border-color: var(--vz-primary); transform: translateY(-2px); }
        .dashboard-shortcut i { display: grid; width: 42px; height: 42px; place-items: center; color: var(--vz-primary); background: var(--vz-primary-bg-subtle); border-radius: .3rem; font-size: 1.3rem; }
        .dashboard-agenda-date { width: 54px; flex: 0 0 54px; padding: .5rem .25rem; text-align: center; background: var(--vz-primary-bg-subtle); border-radius: .3rem; }
        .dashboard-agenda-date strong { display: block; color: var(--vz-primary); font-size: 1.15rem; line-height: 1; }
    </style>
<?php $__env->stopPush(); ?>

<div>
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0"><?php echo e($jenisDashboard ? 'Dashboard Saya' : 'Dashboard Akademik'); ?></h4>
            <p class="text-muted mb-0 mt-1">Sistem Blok Fakultas Kedokteran UIN Jambi</p>
        </div>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item active">Dashboard</li></ol>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jenisDashboard): ?>
        <div class="card dashboard-welcome border-0">
            <div class="card-body p-4">
                <small class="text-uppercase">Portal <?php echo e(ucfirst($jenisDashboard)); ?></small>
                <h2 class="text-white mt-2 mb-1">Halo, <?php echo e($profil?->nama ?? auth()->user()->name); ?></h2>
                <p class="text-muted mb-0"><?php echo e($jenisDashboard === 'mahasiswa' ? 'Materi, pengampu, tanggal pelaksanaan, dan kehadiran Anda dalam satu tempat.' : 'Agenda mengajar dan tindak lanjut pertemuan Anda.'); ?></p>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($profil)): ?>
            <div class="alert alert-warning" role="alert">Akun belum terhubung ke data <?php echo e($jenisDashboard); ?>. Hubungi pengelola akademik.</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="row g-3 mb-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $stats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="col-6 col-lg-3">
                    <div class="card h-100 mb-0"><div class="card-body"><p class="text-muted mb-2"><?php echo e($stat['label']); ?></p><h3 class="mb-0"><?php echo e($stat['value']); ?></h3></div></div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profil): ?>
                <div class="col-12 col-lg-6">
                    <div class="card h-100 mb-0"><div class="card-body"><p class="text-muted mb-2">Identitas</p><h6 class="mb-1"><?php echo e($profil->prodi?->nama ?? 'Program studi belum diatur'); ?></h6><span><?php echo e($jenisDashboard === 'mahasiswa' ? "NIM {$profil->nim} · Angkatan {$profil->angkatan}" : 'NIDN '.($profil->nidn ?: '-')); ?></span></div></div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="row g-3 mb-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jenisDashboard === 'mahasiswa'): ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('materi-saya:')): ?>
                    <div class="col-md-6 col-xl-4"><a href="<?php echo e(route('materi-saya.index')); ?>" wire:navigate class="dashboard-shortcut text-decoration-none"><i class="ri-book-open-line"></i><span><strong class="d-block">Materi & Modul</strong><small class="text-muted">Bahan belajar dan logbook</small></span></a></div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('pertemuan-saya:')): ?>
                    <div class="col-md-6 col-xl-4"><a href="<?php echo e(route('pertemuan-saya.index')); ?>" wire:navigate class="dashboard-shortcut text-decoration-none"><i class="ri-calendar-check-line"></i><span><strong class="d-block">Pertemuan Saya</strong><small class="text-muted">Presensi, jurnal, nilai, dan modul</small></span></a></div>
                <?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="col-md-6 col-xl-4"><a href="<?php echo e(route('profile')); ?>" wire:navigate class="dashboard-shortcut text-decoration-none"><i class="ri-user-settings-line"></i><span><strong class="d-block">Profil & Akun</strong><small class="text-muted">Profil dan kata sandi</small></span></a></div>
        </div>

        <div class="card">
            <div class="card-header">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jenisDashboard === 'mahasiswa'): ?>
                    <h5 class="card-title mb-1">Daftar Pertemuan Terlaksana</h5>
                    <p class="text-muted mb-0">Materi, pengampu, tanggal pelaksanaan, dan status kehadiran Anda.</p>
                <?php else: ?>
                    <h5 class="card-title mb-1">Agenda Terdekat</h5>
                    <p class="text-muted mb-0">Maksimal lima pertemuan yang terkait dengan Anda.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $agenda; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="d-flex gap-3 p-3 <?php echo e(! $loop->last ? 'border-bottom' : ''); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'agenda-'.e($item->id_pertemuan_blok).''; ?>wire:key="agenda-<?php echo e($item->id_pertemuan_blok); ?>">
                        <div class="dashboard-agenda-date"><strong><?php echo e($item->tanggal->format('d')); ?></strong><small><?php echo e($item->tanggal->translatedFormat('M')); ?></small></div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?php echo e($item->materi_rinci_blok?->judul ?? $item->topik ?? 'Pertemuan blok'); ?></h6>
                            <div class="text-muted small"><?php echo e($item->blok?->kode); ?> - <?php echo e($item->blok?->nama); ?> · <?php echo e($item->kelompok_blok?->kode); ?></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jenisDashboard === 'mahasiswa'): ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pengampu = $item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->join(', ')): ?>
                                    <div class="text-muted small">Pengampu: <?php echo e($pengampu); ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php $presensi = $item->presensi_pertemuan_blok->first(); ?>
                                <div class="mt-2">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($presensi): ?>
                                        <?php
                                            $warna = match ($presensi->status) {
                                                'hadir' => 'success',
                                                'sakit', 'izin' => 'warning',
                                                'alpa' => 'danger',
                                                default => 'secondary',
                                            };
                                        ?>
                                        <span class="badge bg-<?php echo e($warna); ?>-subtle text-<?php echo e($warna); ?>"><i class="ri-user-check-line"></i> Kehadiran: <?php echo e(ucfirst($presensi->status)); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary"><i class="ri-question-line"></i> Kehadiran belum tercatat</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="small mt-1"><i class="ri-time-line"></i> <?php echo e($item->jam_mulai ? substr($item->jam_mulai, 0, 5) : 'Waktu belum diatur'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->ruangan): ?> · <?php echo e($item->ruangan); ?>

                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="text-center text-muted py-5"><i class="ri-calendar-check-line d-block fs-2 mb-2"></i><?php echo e($jenisDashboard === 'mahasiswa' ? 'Belum ada pertemuan terlaksana.' : 'Belum ada agenda terjadwal.'); ?></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0">
            <div class="card-body p-4">
                <span class="badge bg-primary-subtle text-primary mb-3">Pusat Pengelolaan</span>
                <h2>Ruang kerja operasional pembelajaran sistem blok.</h2>
                <p class="text-muted mb-0">Pantau fondasi akademik dan kelola operasional melalui menu samping.</p>
                <p class="mt-3 mb-0"><strong>Semester Aktif:</strong> <?php echo e($semesterAktif ? ucfirst($semesterAktif->nama).' '.$semesterAktif->tahun : 'Belum diatur'); ?></p>
            </div>
        </div>
        <div class="row g-3 mb-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $stats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="col-6 col-lg-4"><div class="card h-100 mb-0"><div class="card-body d-flex align-items-center justify-content-between"><div><p class="text-muted mb-2"><?php echo e($stat['label']); ?></p><h4 class="mb-0"><?php echo e(number_format($stat['value'], 0, ',', '.')); ?></h4></div><span class="avatar-sm"><span class="avatar-title rounded bg-<?php echo e($stat['color']); ?>-subtle text-<?php echo e($stat['color']); ?>"><i class="<?php echo e($stat['icon']); ?>"></i></span></span></div></div></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Blok Aktif</h5></div>
            <div class="card-body">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $blokAktif; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $blok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="py-2 <?php echo e(! $loop->last ? 'border-bottom' : ''); ?>"><strong><?php echo e($blok->kode); ?> - <?php echo e($blok->nama); ?></strong><div class="text-muted small"><?php echo e($blok->prodi?->nama ?? 'Prodi belum diatur'); ?></div></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="text-muted mb-0">Belum ada blok aktif.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\dashboard\index.blade.php ENDPATH**/ ?>