<?php
use Livewire\Component;
?>

<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <label class="form-label mb-0"><?php echo e($label); ?></label>
        <span class="badge bg-primary-subtle text-primary"><?php echo e(count($selected)); ?> dipilih</span>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedItems->isNotEmpty()): ?>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <span class="badge bg-light text-body border d-inline-flex align-items-center gap-1">
                    <?php echo e($item->{$colSearch}); ?><?php echo e($colSubtitle ? ' - '.$item->{$colSubtitle} : ''); ?>

                    <button type="button" class="btn btn-link btn-sm p-0 text-danger lh-1" wire:click="removeValue('<?php echo e($item->{$colValue}); ?>')">
                        <i class="ri-close-line"></i>
                    </button>
                </span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="dropdown py-2" style="width:100%" data-bs-auto-close="outside">
        <span class="form-control d-flex justify-content-between align-items-center" id="button_<?php echo e($wire_model); ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span><?php echo e(count($selected) ? count($selected).' data dipilih' : 'Pilih data'); ?></span>
            <i class="ri-search-line"></i>
        </span>

        <ul class="dropdown-menu p-2" id="dropdown_<?php echo e($wire_model); ?>" style="width: 100%; max-height: 360px; overflow-y: auto;" aria-labelledby="button_<?php echo e($wire_model); ?>" wire:ignore.self>
            <li class="mb-2">
                <input type="text" role="button" data-bs-toggle="dropdown" autofocus wire:model.live.debounce.500ms="search" class="form-control" placeholder="Cari...">
            </li>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $value = (string) $item->{$colValue};
                    $isSelected = in_array($value, $selected, true);
                    $isUsedByOther = isset($item->blok_id) && $item->blok_id && (int) $item->blok_id !== (int) $currentValue;
                ?>
                <li>
                    <button type="button" class="dropdown-item d-flex gap-2 align-items-start rounded py-2" wire:click="toggleValue('<?php echo e($item->{$colValue}); ?>')">
                        <input type="checkbox" class="form-check-input mt-1 pe-none" <?php if($isSelected): echo 'checked'; endif; ?> tabindex="-1">
                        <span class="d-block">
                            <span class="fw-semibold d-block"><?php echo e($item->{$colSearch}); ?><?php echo e($colSubtitle ? ' - '.$item->{$colSubtitle} : ''); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($item->sks)): ?>
                                <span class="text-muted small"><?php echo e($item->sks); ?> SKS</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isUsedByOther): ?>
                                <span class="badge bg-warning-subtle text-warning ms-1">Dipakai blok lain</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </span>
                    </button>
                </li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <li><span class="dropdown-item text-muted">Tidak ada data.</span></li>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
    </div>
</div><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/15ca02d5.blade.php ENDPATH**/ ?>