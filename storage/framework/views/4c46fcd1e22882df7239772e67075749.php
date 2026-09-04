<div
    x-data="{ open: false, countChecked: <?php if ((object) ('checkboxValues') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('checkboxValues'->value()); ?>')<?php echo e('checkboxValues'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('checkboxValues'); ?>')<?php endif; ?>.live }"
    class="dropdown"
>
    <div
        tabindex="0"
        role="button"
        class="btn btn-sm btn-primary"
    >
        <?php if (isset($component)) { $__componentOriginal6b8135c2c8cb1493dc3034248d76b61c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6b8135c2c8cb1493dc3034248d76b61c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.download','data' => ['class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.download'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-5 w-5']); ?>
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
    </div>
    <div
        tabindex="0"
        class="dropdown-content card card-sm bg-base-100 z-1 shadow-md"
    >
        <div class="card-body">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('xlsx', data_get($setUp, 'exportable.type'))): ?>
                <div class="flex items-center gap-2">
                    <span class="w-12"><?php echo app('translator')->get('XLSX'); ?></span>
                    <button
                        wire:click.prevent="exportToXLS"
                        x-on:click="open = false"
                        href="#"
                        class="btn btn-sm"
                    >
                        <span class="export-count text-xs">(<?php echo e($this->total); ?>)</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($enabledFilters) === 0): ?>
                            <?php echo app('translator')->get('livewire-powergrid::datatable.labels.all'); ?>
                        <?php else: ?>
                            <?php echo app('translator')->get('livewire-powergrid::datatable.labels.filtered'); ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($checkbox): ?>
                        <button
                            wire:click.prevent="exportToXLS(true)"
                            x-on:click="open = false"
                            x-bind:disabled="countChecked.length === 0"
                            :class="{ 'cursor-not-allowed': countChecked.length === 0 }"
                            class="btn btn-sm"
                        >
                            <span
                                class="export-count text-xs"
                                x-text="`(${countChecked.length})`"
                            ></span> <?php echo app('translator')->get('livewire-powergrid::datatable.labels.selected'); ?>
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('csv', data_get($setUp, 'exportable.type'))): ?>
                <div class="flex items-center gap-2">
                    <span class="w-12"><?php echo app('translator')->get('Csv'); ?></span>
                    <button
                        wire:click.prevent="exportToCsv"
                        x-on:click="open = false"
                        class="btn btn-sm"
                    >
                        <span class="export-count text-xs">(<?php echo e($this->total); ?>)</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($enabledFilters) === 0): ?>
                            <?php echo app('translator')->get('livewire-powergrid::datatable.labels.all'); ?>
                        <?php else: ?>
                            <?php echo app('translator')->get('livewire-powergrid::datatable.labels.filtered'); ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($checkbox): ?>
                        <button
                            wire:click.prevent="exportToCsv(true)"
                            x-on:click="open = false"
                            x-bind:disabled="countChecked.length === 0"
                            :class="{ 'cursor-not-allowed': countChecked.length === 0 }"
                            class="btn btn-sm"
                        >
                            <span
                                class="export-count text-xs"
                                x-text="`(${countChecked.length})`"
                            ></span> <?php echo app('translator')->get('livewire-powergrid::datatable.labels.selected'); ?>
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\header\export.blade.php ENDPATH**/ ?>