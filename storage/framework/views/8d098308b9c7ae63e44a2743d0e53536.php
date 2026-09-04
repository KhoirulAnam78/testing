<?php
use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\KomponenPenilaian;
use App\Models\KomponenPenilaianBlok;
use App\Models\MataKuliah;
use App\Models\MateriBlok;
use App\Models\MateriRinciBlok;
use App\Models\PengelolaBlok;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
?>

<form wire:submit="saveAndContinue">
    <?php echo csrf_field(); ?>
    <?php
        $totalMateri = collect($aturan)->sum(fn ($item) => count($item['materi'] ?? []));
        $totalRinci = collect($aturan)->sum(fn ($item) => collect($item['materi'] ?? [])->sum(fn ($materi) => count($materi['rinci'] ?? [])));
        $totalPertemuan = collect($aturan)->sum(fn ($item) => collect($item['materi'] ?? [])->sum(fn ($materi) => collect($materi['rinci'] ?? [])->sum(fn ($rinci) => (int) ($rinci['jumlah_sesi'] ?? 1))));
        $totalRinciTanpaTanggal = collect($aturan)->sum(fn ($item) => collect($item['materi'] ?? [])
            ->sum(fn ($materi) => collect($materi['rinci'] ?? [])->filter(fn ($rinci) => empty($rinci['tanggal_rencana']))->count()));
        $activeAturan = $aturan[$active_aturan_index] ?? null;
        $activeJenis = $activeAturan ? $jenis_kegiatan->firstWhere('id', (int) ($activeAturan['jenis_kegiatan_id'] ?? 0)) : null;
    ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h4 class="mb-0"><?php echo e($edit_id ? 'Edit Blok' : 'Tambah Blok'); ?></h4>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info-subtle text-info"><?php echo e(count($aturan)); ?> kegiatan</span>
                            <span class="badge bg-success-subtle text-success"><?php echo e($totalRinci); ?> rincian</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs nav-tabs-custom mb-4" role="tablist">
                        <li class="nav-item">
                            <button type="button" class="nav-link <?php echo e($active_tab === 'informasi' ? 'active' : ''); ?>" wire:click="setActiveTab('informasi')">
                                <i class="ri-information-line"></i> Informasi
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link <?php echo e($active_tab === 'kegiatan' ? 'active' : ''); ?>" wire:click="setActiveTab('kegiatan')">
                                <i class="ri-list-check-2"></i> Kegiatan
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link <?php echo e($active_tab === 'materi' ? 'active' : ''); ?>" wire:click="setActiveTab('materi')">
                                <i class="ri-book-open-line"></i> Materi
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link <?php echo e($active_tab === 'penilaian' ? 'active' : ''); ?>" wire:click="setActiveTab('penilaian')">
                                <i class="ri-graduation-cap-line"></i> Penilaian
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link <?php echo e($active_tab === 'review' ? 'active' : ''); ?>" wire:click="setActiveTab('review')">
                                <i class="ri-checkbox-circle-line"></i> Review
                            </button>
                        </li>
                    </ul>

                    <?php if (isset($component)) { $__componentOriginal4eb374fb264ddefd5a619b521190fb97 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4eb374fb264ddefd5a619b521190fb97 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.full-page-loading','data' => ['message' => 'Memproses halaman blok...']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('full-page-loading'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['message' => 'Memproses halaman blok...']); ?>
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

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($save_attempted && $errors->any()): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                            <div class="fw-semibold mb-1">
                                <i class="ri-error-warning-line"></i> Data blok belum lengkap.
                            </div>
                            <div class="mb-2">Periksa kembali isian yang ditandai sebelum menyimpan.</div>
                            <ul class="mb-0 ps-3">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <li><?php echo e($message); ?></li>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($copy_success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                            <i class="ri-check-line"></i> <?php echo e($copy_success_message); ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($save_success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="status">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                            <i class="ri-check-line"></i> <?php echo e($save_success_message); ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($active_tab === 'informasi'): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $edit_id): ?>
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="row g-3 align-items-end">
                                    <div class="col-lg-8">
                                        <label class="form-label">Salin Struktur dari Blok Sebelumnya</label>
                                        <select class="form-select" wire:model.live="copy_blok_id">
                                            <option value="">Pilih blok sumber</option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blok_copy_options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $blokOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <option value="<?php echo e($blokOption['id']); ?>"><?php echo e($blokOption['label']); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['copy_blok_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="col-lg-4 text-lg-end">
                                        <button type="button" class="btn btn-soft-info" wire:click="copyFromBlok" wire:loading.attr="disabled" wire:target="copyFromBlok">
                                            <span wire:loading.remove wire:target="copyFromBlok"><i class="ri-file-copy-line"></i> Salin Struktur</span>
                                            <span wire:loading wire:target="copyFromBlok">Menyalin...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="row">
                            <div class="col-xl-8">
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Program Studi</label>
                                        <select class="form-select" wire:model.live="prodi_id">
                                            <option value="">Pilih prodi</option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $prodi; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <option value="<?php echo e($item->id_prodi); ?>"><?php echo e($item->kode); ?> - <?php echo e($item->nama); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['prodi_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Semester</label>
                                        <select class="form-select" wire:model="semester_id">
                                            <option value="">Pilih semester</option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $semester; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <option value="<?php echo e($item->id_semester); ?>"><?php echo e(ucfirst($item->nama)); ?> <?php echo e($item->tahun); ?> (<?php echo e($item->kode); ?>)</option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['semester_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dropdown.select-search', ['query' => 'App\Models\Dosen','wire_model' => 'koordinator_id','label' => 'Koordinator Blok','colSearch' => 'nama','colValue' => 'id_dosen','selected' => $koordinator_id,'conditions' => 'status = \'aktif\'']);

$__keyOuter = $__key ?? null;

$__key = 'koordinator-'.$edit_id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1472283087-0', $__key);

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
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['koordinator_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dropdown.select-search', ['query' => 'App\Models\Dosen','wire_model' => 'asisten_koordinator_id','label' => 'Asisten Koordinator','colSearch' => 'nama','colValue' => 'id_dosen','selected' => $asisten_koordinator_id,'conditions' => 'status = \'aktif\'']);

$__keyOuter = $__key ?? null;

$__key = 'asisten-koordinator-'.$edit_id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1472283087-1', $__key);

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
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['asisten_koordinator_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dropdown.multi-select-search', ['query' => 'App\Models\Dosen','wire_model' => 'selected_kontributor_ids','label' => 'Kontributor Blok','colSearch' => 'nama','colSubtitle' => 'nidn','colValue' => 'id_dosen','selected' => $selected_kontributor_ids,'conditions' => 'status = \'aktif\'','limit' => 10]);

$__keyOuter = $__key ?? null;

$__key = 'kontributor-blok-'.$edit_id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1472283087-2', $__key);

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
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selected_kontributor_ids.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nama Blok</label>
                                    <input type="text" class="form-control" wire:model.live.debounce.500ms="nama">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['nama'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tanggal Mulai</label>
                                        <input type="date" class="form-control" wire:model="tanggal_mulai">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tanggal_mulai'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tanggal Selesai</label>
                                        <input type="date" class="form-control" wire:model="tanggal_selesai">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tanggal_selesai'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea class="form-control" wire:model="deskripsi" rows="3"></textarea>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['deskripsi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="border rounded p-3 h-100">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selected_mata_kuliah_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($prodi_id)): ?>
                                        <div class="alert alert-info mb-0 alert-dismissible fade show" role="alert">
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                            Pilih program studi terlebih dahulu untuk menampilkan mata kuliah.</div>
                                    <?php else: ?>
                                        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dropdown.multi-select-search', ['query' => 'App\Models\MataKuliah','wire_model' => 'selected_mata_kuliah_ids','label' => 'Mata Kuliah yang Memakai Blok','colSearch' => 'kode','colSubtitle' => 'nama','colValue' => 'id','selected' => $selected_mata_kuliah_ids,'conditions' => 'status = \'aktif\' and prodi_id = '.(int) $prodi_id,'currentValue' => $edit_id,'limit' => 10]);

$__keyOuter = $__key ?? null;

$__key = 'mata-kuliah-blok-'.$prodi_id.'-'.$edit_id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1472283087-3', $__key);

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
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($active_tab === 'kegiatan'): ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['aturan'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="alert alert-danger py-2 mb-0 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" wire:click="applyTemplateStandar">
                                <i class="ri-magic-line"></i> Gunakan Template Standar
                            </button>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $aturan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $jenisTerpilih = $jenis_kegiatan->firstWhere('id', (int) ($item['jenis_kegiatan_id'] ?? 0));
                                $materiCount = count($item['materi'] ?? []);
                                $rinciCount = collect($item['materi'] ?? [])->sum(fn ($materi) => count($materi['rinci'] ?? []));
                            ?>
                            <div class="card border shadow-sm mb-3 aturan-card" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'aturan-config-'.e($index).''; ?>wire:key="aturan-config-<?php echo e($index); ?>">
                                <div class="card-header bg-light-subtle border-bottom">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="badge bg-primary rounded-pill px-2">#<?php echo e($index + 1); ?></span>
                                            <div class="d-flex flex-column">
                                                <span class="text-uppercase small text-muted fw-semibold lh-1">Kegiatan</span>
                                                <span class="fw-semibold fs-15"><?php echo e($jenisTerpilih ? $jenisTerpilih->nama : 'Kegiatan belum dipilih'); ?></span>
                                            </div>
                                            <span class="badge bg-primary-subtle text-primary"><?php echo e($materiCount); ?> materi</span>
                                            <span class="badge bg-secondary-subtle text-secondary"><?php echo e($rinciCount); ?> rincian</span>
                                            <span class="badge bg-success-subtle text-success">Kelompok belajar</span>
                                        </div>
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeAturan(<?php echo e($index); ?>)" <?php if(count($aturan) <= 1): echo 'disabled'; endif; ?>>
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-lg-5 mb-3">
                                            <label class="form-label">Jenis Kegiatan</label>
                                            <select class="form-select" wire:model.live="aturan.<?php echo e($index); ?>.jenis_kegiatan_id">
                                                <option value="">Pilih jenis kegiatan</option>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $jenis_kegiatan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $jenis): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <option
                                                        value="<?php echo e($jenis->id); ?>"
                                                        <?php if(collect($aturan)->except($index)->pluck('jenis_kegiatan_id')->contains(fn ($id) => (int) $id === (int) $jenis->id)): echo 'disabled'; endif; ?>
                                                    ><?php echo e($jenis->kode); ?> - <?php echo e($jenis->nama); ?></option>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </select>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$index.jenis_kegiatan_id"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <div class="col-md-6 col-lg-3 mb-3">
                                            <label class="form-label">Durasi (Menit)</label>
                                            <input type="number" class="form-control" wire:model.live="aturan.<?php echo e($index); ?>.durasi_menit">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$index.durasi_menit"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <div class="col-md-6 col-lg-2 mb-3">
                                            <label class="form-label">Urutan</label>
                                            <input type="number" class="form-control" wire:model="aturan.<?php echo e($index); ?>.urutan">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$index.urutan"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                    <hr class="my-3">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-lg-7">
                                            <span class="text-uppercase small text-muted fw-semibold d-block mb-2">Pengaturan Kegiatan</span>
                                            <div class="d-flex flex-wrap gap-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="perlu_presensi_<?php echo e($index); ?>" wire:model="aturan.<?php echo e($index); ?>.perlu_presensi">
                                                    <label class="form-check-label" for="perlu_presensi_<?php echo e($index); ?>">Presensi</label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="perlu_logbook_<?php echo e($index); ?>" wire:model="aturan.<?php echo e($index); ?>.perlu_logbook">
                                                    <label class="form-check-label" for="perlu_logbook_<?php echo e($index); ?>">Logbook</label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="perlu_penilaian_<?php echo e($index); ?>" wire:model.live="aturan.<?php echo e($index); ?>.perlu_penilaian">
                                                    <label class="form-check-label" for="perlu_penilaian_<?php echo e($index); ?>">
                                                        Penilaian
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($item['komponen'])): ?>
                                                            <span class="badge bg-light text-dark border"><?php echo e(count($item['komponen'])); ?> komponen</span>
                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-5">
                                            <span class="text-uppercase small text-muted fw-semibold d-block mb-2">Aksi Cepat</span>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button type="button" class="btn btn-soft-info btn-sm" wire:click="setActiveAturan(<?php echo e($index); ?>)">
                                                    <i class="ri-book-open-line"></i> Isi Materi
                                                </button>
                                                <button type="button" class="btn btn-soft-secondary btn-sm" wire:click="setActivePenilaian(<?php echo e($index); ?>)">
                                                    <i class="ri-graduation-cap-line"></i> Isi Penilaian
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="addAturan">
                                <i class="ri-add-box-fill"></i> Tambah Kegiatan
                            </button>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($active_tab === 'materi'): ?>
                        <div class="row">
                            <div class="col-xl-3 mb-3">
                                <div class="list-group">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $aturan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <?php
                                            $jenisTerpilih = $jenis_kegiatan->firstWhere('id', (int) ($item['jenis_kegiatan_id'] ?? 0));
                                            $rinciCount = collect($item['materi'] ?? [])->sum(fn ($materi) => count($materi['rinci'] ?? []));
                                        ?>
                                        <button type="button" class="list-group-item list-group-item-action <?php echo e($active_aturan_index === $index ? 'active' : ''); ?>" wire:click="setActiveAturan(<?php echo e($index); ?>)">
                                            <div class="fw-semibold"><?php echo e($jenisTerpilih ? $jenisTerpilih->nama : 'Kegiatan belum dipilih'); ?></div>
                                            <small><?php echo e(count($item['materi'] ?? [])); ?> materi, <?php echo e($rinciCount); ?> rincian</small>
                                        </button>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="col-xl-9">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $activeAturan): ?>
                                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                        Tambahkan kegiatan terlebih dahulu.</div>
                                <?php else: ?>
                                    <div class="mb-3">
                                        <h5 class="mb-1"><?php echo e($activeJenis ? $activeJenis->nama : 'Kegiatan belum dipilih'); ?></h5>
                                        <div class="text-muted small">Default kegiatan: <?php echo e($activeAturan['durasi_menit'] ?? 0); ?> menit per sesi</div>
                                    </div>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.materi"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger mb-2"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ($activeAturan['materi'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $materiIndex => $materi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <div class="border rounded p-3 mb-3" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'materi-active-'.e($active_aturan_index).'-'.e($materiIndex).''; ?>wire:key="materi-active-<?php echo e($active_aturan_index); ?>-<?php echo e($materiIndex); ?>">
                                            <div class="row g-3">
                                                <div class="col-lg-7 mb-3">
                                                    <label class="form-label">Judul Pokok Materi</label>
                                                    <input type="text" class="form-control" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.judul">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.materi.$materiIndex.judul"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                                <div class="col-md-3 col-lg-2 mb-3">
                                                    <label class="form-label">Urutan</label>
                                                    <input type="number" class="form-control" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.urutan">
                                                </div>
                                                <div class="col-md-4 col-lg-2 mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select class="form-select" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.status">
                                                        <option value="aktif">Aktif</option>
                                                        <option value="nonaktif">Nonaktif</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2 col-lg-1 mb-3 text-end">
                                                    <label class="form-label d-block">&nbsp;</label>
                                                    <button type="button" class="btn btn-danger btn-sm" wire:click="removeMateri(<?php echo e($active_aturan_index); ?>, <?php echo e($materiIndex); ?>)" <?php if(count($activeAturan['materi'] ?? []) <= 1): echo 'disabled'; endif; ?>>
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <details class="mb-3">
                                                <summary class="text-muted">Detail tambahan</summary>
                                                <div class="pt-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">Deskripsi</label>
                                                        <textarea class="form-control" rows="2" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.deskripsi"></textarea>
                                                    </div>
                                                    <div class="mb-0">
                                                        <label class="form-label">Capaian Pembelajaran</label>
                                                        <textarea class="form-control" rows="2" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.capaian_pembelajaran"></textarea>
                                                    </div>
                                                </div>
                                            </details>

                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                                <h6 class="mb-0">Rincian Materi</h6>
                                            </div>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.materi.$materiIndex.rinci"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger mb-2"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ($materi['rinci'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rinciIndex => $rinci): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <div class="border rounded bg-light p-3 mb-2" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'rinci-active-'.e($active_aturan_index).'-'.e($materiIndex).'-'.e($rinciIndex).''; ?>wire:key="rinci-active-<?php echo e($active_aturan_index); ?>-<?php echo e($materiIndex); ?>-<?php echo e($rinciIndex); ?>">
                                                    <div class="row g-3 align-items-start">
                                                        <div class="col-lg-7 mb-3">
                                                            <label class="form-label">Judul Rinci</label>
                                                            <input type="text" class="form-control" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.rinci.<?php echo e($rinciIndex); ?>.judul">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.judul"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </div>
                                                        <div class="col-md-3 col-lg-2 mb-3">
                                                            <label class="form-label">Pertemuan</label>
                                                            <input type="number" class="form-control" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.rinci.<?php echo e($rinciIndex); ?>.pertemuan_ke">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.pertemuan_ke"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </div>
                                                        <div class="col-md-3 col-lg-2 mb-3">
                                                            <label class="form-label">Menit / Sesi</label>
                                                            <input type="number" min="1" max="1440" class="form-control" wire:model.live="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.rinci.<?php echo e($rinciIndex); ?>.durasi_menit_per_sesi">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.durasi_menit_per_sesi"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </div>
                                                        <div class="col-md-2 col-lg-1 mb-3 text-end">
                                                            <label class="form-label d-block">&nbsp;</label>
                                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeRinci(<?php echo e($active_aturan_index); ?>, <?php echo e($materiIndex); ?>, <?php echo e($rinciIndex); ?>)">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-4 col-lg-3 mb-3">
                                                            <label class="form-label">Tanggal Rencana</label>
                                                            <input type="date" class="form-control" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.rinci.<?php echo e($rinciIndex); ?>.tanggal_rencana">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.tanggal_rencana"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </div>
                                                        <div class="col-md-4 col-lg-2 mb-3">
                                                            <label class="form-label">Jam Mulai</label>
                                                            <input type="time" class="form-control" wire:model.live="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.rinci.<?php echo e($rinciIndex); ?>.jam_mulai_rencana">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.jam_mulai_rencana"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </div>
                                                        <div class="col-md-4 col-lg-2 mb-3">
                                                            <label class="form-label">Jam Selesai</label>
                                                            <input type="time" class="form-control" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.rinci.<?php echo e($rinciIndex); ?>.jam_selesai_rencana">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.jam_selesai_rencana"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </div>
                                                        <div class="col-lg-5 mb-3 d-flex align-items-end">
                                                            <div class="text-muted small">
                                                                <i class="ri-information-line"></i>
                                                                Menjadi usulan awal jadwal. Tiap kelompok masih bisa memakai tanggal berbeda di menu Operasional Blok.
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <details>
                                                        <summary class="text-muted">Referensi dan catatan</summary>
                                                        <div class="pt-3">
                                                            <div class="mb-3">
                                                                <label class="form-label">Deskripsi</label>
                                                                <textarea class="form-control" rows="2" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.rinci.<?php echo e($rinciIndex); ?>.deskripsi"></textarea>
                                                            </div>
                                                            <div class="mb-0">
                                                                <label class="form-label">Referensi</label>
                                                                <textarea class="form-control" rows="2" wire:model="aturan.<?php echo e($active_aturan_index); ?>.materi.<?php echo e($materiIndex); ?>.rinci.<?php echo e($rinciIndex); ?>.referensi"></textarea>
                                                            </div>
                                                        </div>
                                                    </details>
                                                </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                            <div class="d-flex justify-content-end mt-2">
                                                <button type="button" class="btn btn-primary btn-sm" wire:click="addRinci(<?php echo e($active_aturan_index); ?>, <?php echo e($materiIndex); ?>)">
                                                    <i class="ri-add-box-fill"></i> Tambah Rincian
                                                </button>
                                            </div>
                                        </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="addMateri(<?php echo e($active_aturan_index); ?>)">
                                            <i class="ri-add-box-fill"></i> Tambah Pokok Materi
                                        </button>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($active_tab === 'penilaian'): ?>
                        <div class="row">
                            <div class="col-xl-3 mb-3">
                                <div class="list-group">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $aturan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <?php
                                            $jenisTerpilih = $jenis_kegiatan->firstWhere('id', (int) ($item['jenis_kegiatan_id'] ?? 0));
                                            $komponenCount = count($item['komponen'] ?? []);
                                        ?>
                                        <button type="button" class="list-group-item list-group-item-action <?php echo e($active_aturan_index === $index ? 'active' : ''); ?>" wire:click="setActivePenilaian(<?php echo e($index); ?>)">
                                            <div class="fw-semibold"><?php echo e($jenisTerpilih ? $jenisTerpilih->nama : 'Kegiatan belum dipilih'); ?></div>
                                            <small>
                                                <?php echo e($komponenCount); ?> komponen
                                                <?php echo e(empty($item['perlu_penilaian']) ? '- tidak dinilai' : ''); ?>

                                            </small>
                                        </button>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="col-xl-9">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $activeAturan): ?>
                                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                        Tambahkan kegiatan terlebih dahulu.</div>
                                <?php else: ?>
                                    <?php
                                        $komponenAktif = $activeAturan['komponen'] ?? [];
                                        $totalNilaiMaks = collect($komponenAktif)
                                            ->sum(fn ($baris) => (float) ($baris['nilai_maks'] ?? 0));
                                    ?>

                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                        <div>
                                            <h5 class="mb-1"><?php echo e($activeJenis ? $activeJenis->nama : 'Kegiatan belum dipilih'); ?></h5>
                                            <div class="text-muted small">
                                                Komponen yang dinilai dosen pada setiap pertemuan kegiatan ini.
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="ambilStandarPenilaian(<?php echo e($active_aturan_index); ?>)">
                                            <i class="ri-download-2-line"></i> Ambil dari Standar
                                        </button>
                                    </div>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                            id="perlu_penilaian_tab_<?php echo e($active_aturan_index); ?>"
                                            wire:model.live="aturan.<?php echo e($active_aturan_index); ?>.perlu_penilaian">
                                        <label class="form-check-label" for="perlu_penilaian_tab_<?php echo e($active_aturan_index); ?>">
                                            Kegiatan ini dinilai
                                        </label>
                                    </div>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.komponen"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert">
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                            <?php echo e($message); ?></div>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($komponenAktif)): ?>
                                        <div class="alert alert-light border alert-dismissible fade show" role="alert">
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                            <i class="ri-information-line"></i>
                                            Belum ada komponen penilaian. Tekan <span class="fw-semibold">Ambil dari Standar</span>
                                            untuk menyalin rubrik jenis kegiatan ini.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="min-width: 220px;">Komponen</th>
                                                        <th style="width: 130px;">Nilai Min</th>
                                                        <th style="width: 130px;">Nilai Maks</th>
                                                        <th style="width: 110px;">Urutan</th>
                                                        <th style="width: 70px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $komponenAktif; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $komponenIndex => $baris): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'komponen-'.e($active_aturan_index).'-'.e($komponenIndex).''; ?>wire:key="komponen-<?php echo e($active_aturan_index); ?>-<?php echo e($komponenIndex); ?>">
                                                            <td>
                                                                <?php
                                                                    $masterKomponen = $komponen_penilaian->firstWhere(
                                                                        'id',
                                                                        (int) ($baris['komponen_penilaian_id'] ?? 0)
                                                                    );
                                                                ?>
                                                                <span class="fw-semibold"><?php echo e($masterKomponen?->nama ?: 'Komponen tidak ditemukan'); ?></span>
                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.komponen.$komponenIndex.komponen_penilaian_id"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                                    <div class="text-sm text-danger"><?php echo e($message); ?></div>
                                                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <input type="number" min="0" step="0.01" class="form-control form-control-sm"
                                                                    wire:model.live.debounce.400ms="aturan.<?php echo e($active_aturan_index); ?>.komponen.<?php echo e($komponenIndex); ?>.nilai_min">
                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.komponen.$komponenIndex.nilai_min"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                                    <div class="text-sm text-danger"><?php echo e($message); ?></div>
                                                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <input type="number" min="0.01" step="0.01" class="form-control form-control-sm"
                                                                    wire:model.live.debounce.400ms="aturan.<?php echo e($active_aturan_index); ?>.komponen.<?php echo e($komponenIndex); ?>.nilai_maks">
                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.komponen.$komponenIndex.nilai_maks"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                                    <div class="text-sm text-danger"><?php echo e($message); ?></div>
                                                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <input type="number" class="form-control form-control-sm"
                                                                    wire:model="aturan.<?php echo e($active_aturan_index); ?>.komponen.<?php echo e($komponenIndex); ?>.urutan">
                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["aturan.$active_aturan_index.komponen.$komponenIndex.urutan"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                                    <div class="text-sm text-danger"><?php echo e($message); ?></div>
                                                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                            </td>
                                                            <td class="text-end">
                                                                <button type="button" class="btn btn-danger btn-sm"
                                                                    wire:click="removeKomponen(<?php echo e($active_aturan_index); ?>, <?php echo e($komponenIndex); ?>)">
                                                                    <i class="ri-delete-bin-line"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="row g-2 mt-3">
                                            <div class="col-sm-4">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="text-muted small">Jumlah Komponen</div>
                                                    <div class="fs-5 fw-semibold"><?php echo e(count($komponenAktif)); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="text-muted small">Total Nilai Maksimum per Pertemuan</div>
                                                    <div class="fs-5 fw-semibold"><?php echo e(number_format($totalNilaiMaks, 2, ',', '.')); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="text-muted small">Rumus Nilai Akhir</div>
                                                    <div class="fw-semibold">Total skor ÷ <?php echo e(number_format($totalNilaiMaks, 2, ',', '.')); ?> × 100</div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalNilaiMaks <= 0): ?>
                                            <div class="alert alert-warning py-2 mt-3 mb-0 alert-dismissible fade show" role="alert">
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                                <i class="ri-alert-line"></i>
                                                Total nilai maksimum harus lebih dari 0 agar nilai akhir dapat dihitung.
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted small mt-2">
                                                Skor komponen per pertemuan dijumlahkan, lalu dinormalisasi ke skala 100.
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($active_tab === 'review'): ?>
                        <div class="row">
                            <div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="text-muted">Kegiatan</div><h4 class="mb-0"><?php echo e(count($aturan)); ?></h4></div></div>
                            <div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="text-muted">Pertemuan</div><h4 class="mb-0"><?php echo e($totalPertemuan); ?></h4></div></div>
                            <div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="text-muted">Materi</div><h4 class="mb-0"><?php echo e($totalMateri); ?></h4></div></div>
                            <div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="text-muted">Rincian</div><h4 class="mb-0"><?php echo e($totalRinci); ?></h4></div></div>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalRinciTanpaTanggal > 0): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                <i class="ri-calendar-close-line"></i>
                                <?php echo e($totalRinciTanpaTanggal); ?> rincian materi belum punya tanggal rencana pertemuan.
                                Blok tetap bisa disimpan, tetapi jadwal per kelompok harus diisi manual di menu Operasional Blok.
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $aturan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $jenisTerpilih = $jenis_kegiatan->firstWhere('id', (int) ($item['jenis_kegiatan_id'] ?? 0));
                                $rinciCount = 0;
                                $tanggalRencana = collect();
                                foreach ($item['materi'] ?? [] as $materiReview) {
                                    $rinciCount += count($materiReview['rinci'] ?? []);
                                    foreach ($materiReview['rinci'] ?? [] as $rinciReview) {
                                        if (! empty($rinciReview['tanggal_rencana'])) {
                                            $tanggalRencana->push($rinciReview['tanggal_rencana']);
                                        }
                                    }
                                }
                                $tanggalRencana = $tanggalRencana->sort()->values();
                                $komponenReview = collect($item['komponen'] ?? []);
                                $totalNilaiMaksReview = 0;
                                $daftarKomponenReview = [];
                                foreach ($komponenReview as $barisReview) {
                                    $totalNilaiMaksReview += (float) ($barisReview['nilai_maks'] ?? 0);
                                    $masterKomponenReview = $komponen_penilaian->firstWhere(
                                        'id',
                                        (int) ($barisReview['komponen_penilaian_id'] ?? 0)
                                    );
                                    $namaKomponenReview = $masterKomponenReview
                                        ? $masterKomponenReview->nama
                                        : 'komponen';
                                    $daftarKomponenReview[] = $namaKomponenReview
                                        .' ('.($barisReview['nilai_min'] ?? 0)
                                        .'-'.($barisReview['nilai_maks'] ?? 0).')';
                                }
                                $daftarKomponenReview = implode(', ', $daftarKomponenReview);
                            ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold"><?php echo e($jenisTerpilih ? $jenisTerpilih->nama : 'Kegiatan belum dipilih'); ?></div>
                                        <div class="text-muted small"><?php echo e($item['durasi_menit'] ?? 0); ?> menit per sesi</div>
                                        <div class="text-muted small">
                                            Rencana:
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tanggalRencana->isEmpty()): ?>
                                                belum diisi
                                            <?php else: ?>
                                                <?php echo e(\Illuminate\Support\Carbon::parse($tanggalRencana->first())->format('d/m/Y')); ?>

                                                &ndash;
                                                <?php echo e(\Illuminate\Support\Carbon::parse($tanggalRencana->last())->format('d/m/Y')); ?>

                                                (<?php echo e($tanggalRencana->count()); ?> dari <?php echo e($rinciCount); ?> rincian)
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <div class="text-muted small">
                                            Penilaian:
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($item['perlu_penilaian'])): ?>
                                                tidak dinilai
                                            <?php elseif($komponenReview->isEmpty()): ?>
                                                <span class="text-danger">ditandai dinilai tapi komponennya belum disusun</span>
                                            <?php else: ?>
                                                <?php echo e($daftarKomponenReview); ?>

                                                &mdash; maksimum <?php echo e($totalNilaiMaksReview); ?>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-primary-subtle text-primary"><?php echo e(count($item['materi'] ?? [])); ?> materi</span>
                                        <span class="badge bg-secondary-subtle text-secondary"><?php echo e($rinciCount); ?> rincian</span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($item['perlu_penilaian'])): ?>
                                            <span class="badge bg-info-subtle text-info"><?php echo e($komponenReview->count()); ?> komponen nilai</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div style="position: fixed; bottom: 50px; left: 0; width: 100%; display: flex; justify-content: center; gap: 8px; z-index: 1050;">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($active_tab !== 'review'): ?>
            <button type="button" class="btn btn-primary shadow d-flex align-items-center gap-2 fab-save"
                wire:click="saveCurrentTab" wire:loading.attr="disabled" wire:target="saveCurrentTab,saveAndNextKegiatan,saveAndContinue">
                <span wire:loading.remove wire:target="saveCurrentTab">
                    <i class="ri-save-line"></i> SIMPAN
                </span>
                <span wire:loading wire:target="saveCurrentTab">Menyimpan...</span>
            </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($active_tab, ['materi', 'penilaian'], true)): ?>
            <button type="button" class="btn btn-info shadow d-flex align-items-center gap-2 fab-save"
                wire:click="saveAndNextKegiatan" wire:loading.attr="disabled" wire:target="saveCurrentTab,saveAndNextKegiatan,saveAndContinue">
                <span wire:loading.remove wire:target="saveAndNextKegiatan">
                    <i class="ri-arrow-right-line"></i> LANJUT MENGISI
                </span>
                <span wire:loading wire:target="saveAndNextKegiatan">Menyimpan...</span>
            </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <button type="submit" class="btn btn-primary shadow d-flex align-items-center gap-2 fab-save"
            wire:loading.attr="disabled" wire:target="saveCurrentTab,saveAndNextKegiatan,saveAndContinue,save">
            <span wire:loading.remove wire:target="saveAndContinue,save">
                <i class="ri-save-line"></i> <?php echo e($active_tab === 'review' ? 'SIMPAN' : 'SIMPAN DAN LANJUT'); ?>

            </span>
            <span wire:loading wire:target="saveAndContinue,save">Menyimpan...</span>
        </button>
    </div>
</form><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/0aff051a.blade.php ENDPATH**/ ?>