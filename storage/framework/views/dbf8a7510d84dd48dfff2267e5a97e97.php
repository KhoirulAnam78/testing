<?php
use App\Models\AnggotaKelompokBlok;
use App\Models\Blok;
use App\Models\LampiranMateriBlok;
use App\Models\PertemuanBlok;
use App\Models\Semester;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
?>

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
        $jenisKegiatanOptions = $this->jenisKegiatanOptions();
        $pertemuanList = $this->pertemuanQuery()->paginate(10);
        $lampiranHalaman = $this->lampiranHalaman($pertemuanList);
    ?>

    <?php if (isset($component)) { $__componentOriginal4eb374fb264ddefd5a619b521190fb97 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4eb374fb264ddefd5a619b521190fb97 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.full-page-loading','data' => ['target' => 'blok_id,semester_id,jenis_kegiatan_id','message' => 'Memuat daftar pertemuan...']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('full-page-loading'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['target' => 'blok_id,semester_id,jenis_kegiatan_id','message' => 'Memuat daftar pertemuan...']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4eb374fb264ddefd5a619b521190fb97)): ?>
<?php $attributes = $__attributesOriginal4eb374fb264ddefd5a619b521190fb97; ?>
<?php unset($__attributesOriginal4eb374fb264ddefd5a619b521190fb97); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4eb374fb264ddefd5a619b521190fb97)): ?>
<?php $component = $__componentOriginal4eb374fb264ddefd5a619b521190fb97; ?>
<?php unset($__componentOriginal4eb374fb264ddefd5a619b521190fb97); ?>
<?php endif; ?>

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
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cari !== ''): ?>
                                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('cari', '')">
                                        <i class="ri-close-line"></i>
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
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
                        <div class="col-12 col-md-4">
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
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jenisKegiatanOptions->isNotEmpty()): ?>
                    <ul class="nav nav-pills nav-tabs-gap px-3 pt-3 pb-0 mb-0 border-bottom" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" role="tab"
                                class="nav-link <?php echo e($jenis_kegiatan_id === '' ? 'active' : ''); ?>"
                                wire:click="$set('jenis_kegiatan_id', '')">
                                <i class="ri-apps-2-line me-1"></i> Semua
                            </button>
                        </li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $jenisKegiatanOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $jenis): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li class="nav-item" role="presentation">
                                <button type="button" role="tab"
                                    class="nav-link <?php echo e((string) $jenis_kegiatan_id === (string) $jenis->id ? 'active' : ''); ?>"
                                    wire:click="$set('jenis_kegiatan_id', '<?php echo e($jenis->id); ?>')">
                                    <i class="ri-price-tag-3-line me-1"></i> <?php echo e($jenis->nama); ?>

                                </button>
                            </li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="card-body">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $pertemuanList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $lampiran = $this->lampiranPertemuan($lampiranHalaman, $item);
                            $presensi = $item->presensi_pertemuan_blok->first();
                            $status = $this->statusKehadiran($presensi?->status);
                            $dosenList = $item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->values();
                        ?>
                        <div class="card border-primary-subtle bg-primary-subtle bg-opacity-10 mb-3" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'materi-saya-'.e($item->id_pertemuan_blok).''; ?>wire:key="materi-saya-<?php echo e($item->id_pertemuan_blok); ?>">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->materi_rinci_blok?->pertemuan_ke): ?>
                                            <span class="badge bg-primary text-white px-3 py-2 fs-6">
                                                <i class="ri-bookmark-3-line me-1"></i> Pertemuan <?php echo e($item->materi_rinci_blok->pertemuan_ke); ?>

                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->aturan_kegiatan_blok?->jenis_kegiatan?->nama): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2 fs-6">
                                                <i class="ri-price-tag-3-line me-1"></i> <?php echo e($item->aturan_kegiatan_blok->jenis_kegiatan->nama); ?>

                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 px-3 py-2 rounded bg-<?php echo e($status['warna']); ?>-subtle border border-<?php echo e($status['warna']); ?>">
                                        <span class="avatar-sm d-inline-flex align-items-center justify-content-center bg-<?php echo e($status['warna']); ?> text-white rounded">
                                            <i class="<?php echo e($status['icon']); ?> fs-5"></i>
                                        </span>
                                        <div class="text-start lh-sm">
                                            <div class="text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">STATUS KEHADIRAN</div>
                                            <div class="fw-bold text-<?php echo e($status['warna']); ?>"><?php echo e($status['label']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="mb-1"><?php echo e($item->materi_rinci_blok?->judul ?: ($item->topik ?: 'Materi pertemuan')); ?></h5>
                                <div class="mb-3 text-muted d-flex flex-wrap align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center">
                                        <i class="ri-book-read-line me-1 text-primary"></i>
                                        <span class="fw-semibold text-body"><?php echo e($item->blok?->kode ?? '-'); ?> &mdash; <?php echo e($item->blok?->nama ?? 'Blok tidak diketahui'); ?></span>
                                    </span>
                                    <span class="text-muted">&middot;</span>
                                    <span class="d-inline-flex align-items-center">
                                        <i class="ri-team-line me-1"></i>
                                        Kelompok <?php echo e($item->kelompok_blok?->kode ?? '-'); ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->kelompok_blok?->nama): ?>
                                            <span class="text-muted ms-1">(<?php echo e($item->kelompok_blok->nama); ?>)</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->blok?->semester): ?>
                                        <span class="text-muted">&middot;</span>
                                        <span class="d-inline-flex align-items-center">
                                            <i class="ri-calendar-2-line me-1"></i>
                                            <?php echo e(ucfirst($item->blok->semester->nama)); ?> <?php echo e($item->blok->semester->tahun); ?>

                                        </span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dosenList->isNotEmpty()): ?>
                                                <ul class="mb-0 ps-3">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $dosenList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nama): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                        <li class="small"><?php echo e($nama); ?></li>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                </ul>
                                            <?php else: ?>
                                                <div class="text-muted small fst-italic">Belum ada dosen pengampu.</div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->tanggal): ?>
                                                    <i class="ri-calendar-line text-muted me-1"></i> <?php echo e($item->tanggal->translatedFormat('l, d F Y')); ?>

                                                <?php else: ?>
                                                    <span class="text-warning"><i class="ri-alert-line me-1"></i> jadwal belum ditetapkan</span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->jam_mulai): ?>
                                                <div class="small mb-1">
                                                    <i class="ri-time-line text-muted me-1"></i>
                                                    <span class="fw-semibold"><?php echo e($this->formatJam($item->jam_mulai)); ?><?php echo e($item->jam_selesai ? ' - '.$this->formatJam($item->jam_selesai) : ''); ?></span>
                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->ruangan): ?>
                                                <div class="small">
                                                    <i class="ri-map-pin-line text-muted me-1"></i> <?php echo e($item->ruangan); ?>

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

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1875447083-0', $__key);

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
</div><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/5524db62.blade.php ENDPATH**/ ?>