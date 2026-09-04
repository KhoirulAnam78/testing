<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'header.toggleColumns')): ?>
    <div x-data="{ open: false, countChecked: <?php if ((object) ('checkboxValues') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('checkboxValues'->value()); ?>')<?php echo e('checkboxValues'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('checkboxValues'); ?>')<?php endif; ?>.live }">
        <div class="dropdown">
            <div
                tabindex="0"
                role="button"
                class="btn btn-sm btn-primary"
            ><?php if (isset($component)) { $__componentOriginal491d64c78bef44602650443184da8c52 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal491d64c78bef44602650443184da8c52 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.eye-off','data' => ['class' => 'w-5 h-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.eye-off'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-5 h-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal491d64c78bef44602650443184da8c52)): ?>
<?php $attributes = $__attributesOriginal491d64c78bef44602650443184da8c52; ?>
<?php unset($__attributesOriginal491d64c78bef44602650443184da8c52); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal491d64c78bef44602650443184da8c52)): ?>
<?php $component = $__componentOriginal491d64c78bef44602650443184da8c52; ?>
<?php unset($__componentOriginal491d64c78bef44602650443184da8c52); ?>
<?php endif; ?></div>
            <div
                tabindex="0"
                class="dropdown-content z-1 bg-base-300 shadow-md"
            >
                <div class="w-56">
                    <ul class="menu menu-md w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->visibleColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li
                                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'toggle-column-'.e(data_get($column, 'isAction') ? 'actions' : data_get($column, 'field')).''; ?>wire:key="toggle-column-<?php echo e(data_get($column, 'isAction') ? 'actions' : data_get($column, 'field')); ?>"
                                data-cy="toggle-field-<?php echo e(data_get($column, 'isAction') ? 'actions' : data_get($column, 'field')); ?>"
                                wire:click="$dispatch('pg:toggleColumn-<?php echo e($tableName); ?>', { field: '<?php echo e(data_get($column, 'field')); ?>'})"
                                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                    '!bg-neutral !text-neutral-content' => data_get($column, 'hidden'),
                                ]); ?>"
                            >
                                <a><?php echo data_get($column, 'title'); ?></a>
                            </li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\header\toggle-columns.blade.php ENDPATH**/ ?>