<?php
use Livewire\Component;
?>

<div>
    <label for="selectpicker"><?php echo e($label); ?></label>
    <div class="dropdown py-2" style="width:100%">
        <span class="form-control" id="button_<?php echo e($wire_model); ?>" role="button" data-bs-toggle="dropdown"
            aria-expanded="false">
            <?php echo e($selected ?? 'Choose One'); ?>

            </button>
            <ul class="dropdown-menu" id="dropdown_<?php echo e($wire_model); ?>" style="width: 100%"
                aria-labelledby="button_<?php echo e($wire_model); ?>" wire:ignore.self>
                <li style="padding:5px"><input type="text" role="button" data-bs-toggle="dropdown" autofocus
                        wire:model.live.debounce.500ms='search' class="form-control" placeholder="search..."></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li class="dropdown-item">
                        <div style="width: 100%;" wire:click='selectValue("<?php echo e($item->$colValue); ?>")'>
                            <span class="<?php echo e($selected == $item->$colValue ? 'fw-bold' : ''); ?>">
                                <?php echo e($item->$colSearch); ?>

                            </span>
                        </div>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <span class="dropdown-item">No data...</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
    </div>
</div><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/32e0687e.blade.php ENDPATH**/ ?>