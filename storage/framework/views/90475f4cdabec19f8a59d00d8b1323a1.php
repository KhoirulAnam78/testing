<div>
    <?php
        $queues = data_get($setUp, 'exportable.batchExport.queues', 0);
    ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($queues > 0 && $showExporting): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batchExporting && !$batchFinished): ?>
            <div
                wire:poll="updateExportProgress"
                class="my-3 px-4 rounded-md py-3 shadow-sm text-center"
            >
                <div><?php echo e(trans('livewire-powergrid::datatable.export.exporting')); ?></div>
                <div
                    class="bg-emerald-500 rounded text-center"
                    style="background-color: rgb(16 185 129); height: 0.25rem; width: <?php echo e($batchProgress); ?>%; transition: width 300ms;"
                >
                </div>
            </div>

            <div
                wire:poll="updateExportProgress"
                class="my-3 px-4 rounded-md py-3 shadow-sm text-center"
            >
                <div><?php echo e($batchProgress); ?>%</div>
                <div><?php echo e(trans('livewire-powergrid::datatable.export.exporting')); ?></div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batchFinished): ?>
            <div class="my-3">
                <p>
                    <button
                        class="btn btn-primary"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapseCompleted"
                        aria-expanded="false"
                        aria-controls="collapseCompleted"
                    >
                        ⚡ <?php echo e(trans('livewire-powergrid::datatable.export.completed')); ?>

                    </button>
                </p>
                <div
                    class="collapse"
                    id="collapseCompleted"
                >
                    <div class="card card-body">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $exportedFiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div
                                class="d-flex w-full p-2"
                                style="cursor:pointer"
                            >
                                <?php if (isset($component)) { $__componentOriginal6b8135c2c8cb1493dc3034248d76b61c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6b8135c2c8cb1493dc3034248d76b61c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.download','data' => ['style' => 'width: 1.5rem;
                                           margin-right: 6px;
                                           color: #2d3034;']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.download'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['style' => 'width: 1.5rem;
                                           margin-right: 6px;
                                           color: #2d3034;']); ?>
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
                                <a wire:click="downloadExport('<?php echo e($file); ?>')">
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
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\bootstrap5\header\batch-exporting.blade.php ENDPATH**/ ?>