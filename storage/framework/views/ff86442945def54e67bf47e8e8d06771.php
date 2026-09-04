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
        $this->resetPage();
    }

    public function updatedCari(): void
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

    <?php
        $blokOptions = $this->blokOptions();
        $semesterOptions = $this->semesterOptions();
        $pertemuanList = $this->pertemuanQuery()->paginate(10);
        $lampiranHalaman = $this->lampiranHalaman($pertemuanList);
    ?>

    <div class="row">
        <div class="col-12">
            <div class="card border-primary-subtle">
                <div class="card-header bg-primary-subtle">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h5 class="mb-1">Materi Pertemuan Kelompok Saya</h5>
                            <div class="text-muted small">
                                Modul dan video dibagikan oleh pengelola blok serta dosen pengampu kelompok Anda.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cari materi</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input type="text" class="form-control" placeholder="Judul, topik, dosen, blok..."
                                    wire:model.live.debounce.300ms="cari">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cari !== ''): ?>
                                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('cari', '')">
                                        <i class="ri-close-line"></i>
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Semester</label>
                            <select class="form-select" wire:model.live="semester_id">
                                <option value="">Semua semester</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $semesterOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $semester): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <option value="<?php echo e($semester->id_semester); ?>">
                                        <?php echo e(ucfirst($semester->nama)); ?> <?php echo e($semester->tahun); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($semester->is_aktif): ?> · Aktif
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Blok</label>
                            <select class="form-select" wire:model.live="blok_id">
                                <option value="">Semua blok</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blokOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $blok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <option value="<?php echo e($blok->id); ?>"><?php echo e($blok->kode); ?> - <?php echo e($blok->nama); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $pertemuanList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $lampiran = $this->lampiranPertemuan($lampiranHalaman, $item);
                            $presensi = $item->presensi_pertemuan_blok->first();
                            $status = $this->statusKehadiran($presensi?->status);
                            $dosenList = $item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->values();
                        ?>
                        <div class="card border shadow-sm mb-3" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'materi-saya-'.e($item->id_pertemuan_blok).''; ?>wire:key="materi-saya-<?php echo e($item->id_pertemuan_blok); ?>">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->materi_rinci_blok?->pertemuan_ke): ?>
                                            <span class="badge bg-primary-subtle text-primary">Pertemuan <?php echo e($item->materi_rinci_blok->pertemuan_ke); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->aturan_kegiatan_blok?->jenis_kegiatan?->nama): ?>
                                            <span class="badge bg-light text-dark border"><?php echo e($item->aturan_kegiatan_blok->jenis_kegiatan->nama); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <span class="badge bg-<?php echo e($status['warna']); ?>-subtle text-<?php echo e($status['warna']); ?>">
                                            <i class="<?php echo e($status['icon']); ?>"></i> Kehadiran: <?php echo e($status['label']); ?>

                                        </span>
                                    </div>
                                    <div class="text-muted small">
                                        <?php echo e($item->blok?->kode); ?> &middot; Kelompok <?php echo e($item->kelompok_blok?->kode); ?>

                                    </div>
                                </div>

                                <h5 class="mb-3"><?php echo e($item->materi_rinci_blok?->judul ?: ($item->topik ?: 'Materi pertemuan')); ?></h5>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100">
                                            <div class="text-muted small text-uppercase fw-semibold mb-2">
                                                <i class="ri-user-star-line"></i> Dosen Pengampu
                                            </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dosenList->isNotEmpty()): ?>
                                                <ul class="mb-0 ps-3">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $dosenList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nama): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                        <li class="small"><?php echo e($nama); ?></li>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                </ul>
                                            <?php else: ?>
                                                <div class="text-muted small">Belum ada dosen pengampu.</div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100">
                                            <div class="text-muted small text-uppercase fw-semibold mb-2">
                                                <i class="ri-calendar-event-line"></i> Pelaksanaan
                                            </div>
                                            <div class="small">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->tanggal): ?>
                                                    <i class="ri-calendar-line text-muted"></i> <?php echo e($item->tanggal->translatedFormat('l, d F Y')); ?>

                                                <?php else: ?>
                                                    <span class="text-warning"><i class="ri-alert-line"></i> jadwal belum ditetapkan</span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->jam_mulai): ?>
                                                <div class="small">
                                                    <i class="ri-time-line text-muted"></i>
                                                    <?php echo e($this->formatJam($item->jam_mulai)); ?><?php echo e($item->jam_selesai ? ' - '.$this->formatJam($item->jam_selesai) : ''); ?>

                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->ruangan): ?>
                                                <div class="small">
                                                    <i class="ri-map-pin-line text-muted"></i> <?php echo e($item->ruangan); ?>

                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->aturan_kegiatan_blok?->perlu_logbook): ?>
                                    <div class="border-top mt-3 pt-3">
                                        <h6 class="mb-3"><i class="ri-file-list-3-line"></i> Logbook Saya</h6>
                                        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('logbook-pertemuan', ['pertemuan_blok_id' => $item->id_pertemuan_blok]);

$__keyOuter = $__key ?? null;

$__key = 'logbook-mahasiswa-'.$item->id_pertemuan_blok;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3757574136-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="mt-3">
                                    <div class="text-muted small text-uppercase fw-semibold mb-2">
                                        <i class="ri-attachment-line"></i> Modul &amp; Video
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lampiran->isEmpty()): ?>
                                        <div class="text-muted small"><i class="ri-information-line"></i> Belum ada modul atau video untuk pertemuan ini.</div>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $lampiran; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tautan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <div class="list-group-item px-3 py-2 mb-2 border rounded d-flex flex-wrap align-items-center justify-content-between gap-2" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tautan-'.e($tautan->id_lampiran_materi_blok).''; ?>wire:key="tautan-<?php echo e($tautan->id_lampiran_materi_blok); ?>">
                                                    <div class="me-auto">
                                                        <div class="small">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tautan->jenis === 'video'): ?>
                                                                <span class="badge bg-danger-subtle text-danger"><i class="ri-video-line"></i> Video</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-primary-subtle text-primary"><i class="ri-links-line"></i> Modul</span>
                                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                            <span class="fw-semibold"><?php echo e($tautan->judul); ?></span>
                                                        </div>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tautan->deskripsi): ?>
                                                            <div class="text-muted small mt-1"><?php echo e($tautan->deskripsi); ?></div>
                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    </div>
                                                    <a href="<?php echo e($tautan->url); ?>" target="_blank" rel="noopener nofollow"
                                                        class="btn btn-soft-primary btn-sm">
                                                        <i class="ri-external-link-line"></i> Buka
                                                    </a>
                                                </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="text-muted text-center py-4">
                            Anda belum terdaftar di kelompok blok manapun, atau kelompok Anda belum punya jadwal pertemuan.
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="mt-3"><?php echo e($pertemuanList->links()); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\materi-saya\index.blade.php ENDPATH**/ ?>