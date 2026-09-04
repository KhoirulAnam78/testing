<div>
    <div class="row g-2 mb-3">
        <div class="col-lg-6">
            <label class="visually-hidden" for="dpna-search">Cari blok</label>
            <input id="dpna-search" type="search" class="form-control" placeholder="Cari kode atau nama blok..." wire:model.live.debounce.300ms="search">
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="visually-hidden" for="dpna-prodi">Prodi</label>
            <select id="dpna-prodi" class="form-select" wire:model.live="prodiId">
                <option value="">Semua prodi</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $prodis; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prodi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($prodi->id_prodi); ?>"><?php echo e($prodi->nama); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="visually-hidden" for="dpna-semester">Semester</label>
            <select id="dpna-semester" class="form-select" wire:model.live="semesterId">
                <option value="">Semua semester</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $semesters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $semester): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($semester->id_semester); ?>"><?php echo e(ucfirst($semester->nama)); ?> <?php echo e($semester->tahun); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </div>
        <div class="col-md-4 col-lg-1 d-grid">
            <button type="button" class="btn btn-soft-secondary" wire:click="resetFilters" aria-label="Reset filter"><i class="ri-refresh-line"></i></button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Blok</th><th>Prodi / Semester</th><th class="text-center">Peserta</th><th class="text-center">Pertemuan</th><th>Status Bobot</th><th></th></tr></thead>
            <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $bloks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $blok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php ($totalBobot = ($blok->kehadiran_masuk_dpna ? (float) $blok->bobot_kehadiran_dpna : 0) + (float) $blok->total_bobot_kegiatan_dpna); ?>
                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'dpna-'.e($blok->id).''; ?>wire:key="dpna-<?php echo e($blok->id); ?>">
                    <td><div class="fw-semibold"><?php echo e($blok->kode); ?></div><div class="text-muted small"><?php echo e($blok->nama); ?></div></td>
                    <td><?php echo e($blok->prodi->nama); ?><div class="text-muted small"><?php echo e(ucfirst($blok->semester->nama)); ?> <?php echo e($blok->semester->tahun); ?></div></td>
                    <td class="text-center"><?php echo e($blok->peserta_blok_count); ?></td>
                    <td class="text-center"><?php echo e($blok->pertemuan_blok_count); ?></td>
                    <td><span class="badge <?php echo e(abs($totalBobot - 100) < .001 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'); ?>"><?php echo e(number_format($totalBobot, 2, ',', '.')); ?>%</span></td>
                    <td class="text-end"><a class="btn btn-primary btn-sm" wire:navigate href="<?php echo e(route('dpna-blok.detail', Crypt::encrypt($blok->id))); ?>">Buka DPNA</a></td>
                </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Blok tidak ditemukan.</td></tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo e($bloks->links()); ?>

</div><?php /**PATH D:\laragon\www\sistem-blok\resources\views/livewire/table-dpna-blok.blade.php ENDPATH**/ ?>