<div>
    <?php
        $queues = data_get($setUp, 'exportable.batchExport.queues', 0);
    ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($queues > 0 && $showExporting): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batchExporting && !$batchFinished): ?>
            <div
                wire:poll="updateExportProgress"
                class="w-full my-3 px-4 rounded dark:text-pg-primary-300 bg-pg-primary-100 dark:bg-pg-primary-800 py-3 text-center"
            >
                <div><?php echo e(trans('livewire-powergrid::datatable.export.exporting')); ?></div>
                <span class="font-normal text-xs"><?php echo e($batchProgress); ?>%</span>
                <div
                    class="bg-emerald-500 rounded h-1 text-center"
                    style="width: <?php echo e($batchProgress); ?>%; transition: width 300ms;"
                >
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batchFinished): ?>
            <div class="w-full my-3 dark:bg-pg-primary-800">
                <div
                    x-data={show:true}
                    class="rounded-top"
                >
                    <div
                        class="px-4 py-3 rounded-md cursor-pointer bg-pg-primary-100 shadow dark:bg-pg-primary-800"
                        x-on:click="show =!show"
                    >
                        <div class="flex justify-between">
                            <button
                                class="appearance-none text-left text-base font-medium text-pg-primary-500 focus:outline-none dark:text-pg-primary-300"
                                type="button"
                            >
                                ⚡ <?php echo e(trans('livewire-powergrid::datatable.export.completed')); ?>

                            </button>
                            <?php if (isset($component)) { $__componentOriginal27ab07ab5afa7ba5aa4eae5a99d53d59 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal27ab07ab5afa7ba5aa4eae5a99d53d59 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.chevron-double-down','data' => ['class' => 'w-5 text-pg-primary-400 dark:text-pg-primary-200']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.chevron-double-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-5 text-pg-primary-400 dark:text-pg-primary-200']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal27ab07ab5afa7ba5aa4eae5a99d53d59)): ?>
<?php $attributes = $__attributesOriginal27ab07ab5afa7ba5aa4eae5a99d53d59; ?>
<?php unset($__attributesOriginal27ab07ab5afa7ba5aa4eae5a99d53d59); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal27ab07ab5afa7ba5aa4eae5a99d53d59)): ?>
<?php $component = $__componentOriginal27ab07ab5afa7ba5aa4eae5a99d53d59; ?>
<?php unset($__componentOriginal27ab07ab5afa7ba5aa4eae5a99d53d59); ?>
<?php endif; ?>
                        </div>
                    </div>
                    <div
                        x-show="show"
                        class="border-l border-b border-r border-pg-primary-200 dark:border-pg-primary-600 px-2 py-4 dark:border-0 dark:bg-pg-primary-700"
                    >
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $exportedFiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="flex w-full p-2">
                                <?php if (isset($component)) { $__componentOriginal6b8135c2c8cb1493dc3034248d76b61c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6b8135c2c8cb1493dc3034248d76b61c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.download','data' => ['class' => 'w-5 text-pg-primary-700 dark:text-pg-primary-300 mr-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.download'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-5 text-pg-primary-700 dark:text-pg-primary-300 mr-3']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6b8135c2c8cb1493dc3034248d76b61c)): ?>
<?php $attributes = $__attributesOriginal6b8135c2c8cb1493dc3034248d76b61c; ?>
<?php unset($__attributesOriginal6b8135c2c8cb1493dc3034248d76b61c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6b8135c2c8cb1493dc3034248d76b61c)): ?>
<?php $component = $__componentOriginal6b8135c2c8cb1493dc3034248d76b61c; ?>
<?php unset($__componentOriginal6b8135c2c8cb1493dc3034248d76b61c); ?>
<?php endif; ?>
                                <a
                                    class="cursor-pointer text-pg-primary-600 dark:text-pg-primary-300"
                                    wire:click="downloadExport('<?php echo e($file); ?>')"
                                >
                                    <?php echo e($file); ?>

                                </a>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\tailwind\header\batch-exporting.blade.php ENDPATH**/ ?>