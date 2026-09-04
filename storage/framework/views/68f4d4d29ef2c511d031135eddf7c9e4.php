<div class="dropdown">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'header.toggleColumns')): ?>
        <div class="btn-group">
            <button
                data-cy="toggle-columns-<?php echo e($tableName); ?>"
                class="btn btn-secondary dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <?php if (isset($component)) { $__componentOriginal491d64c78bef44602650443184da8c52 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal491d64c78bef44602650443184da8c52 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.eye-off','data' => ['width' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.eye-off'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['width' => '18']); ?>
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
<?php endif; ?>
            </button>
            <ul class="dropdown-menu">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->visibleColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li
                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'toggle-column-'.e(data_get($column, 'isAction') ? 'actions' : data_get($column, 'field')).''; ?>wire:key="toggle-column-<?php echo e(data_get($column, 'isAction') ? 'actions' : data_get($column, 'field')); ?>"
                        data-cy="toggle-field-<?php echo e(data_get($column, 'isAction') ? 'actions' : data_get($column, 'field')); ?>"
                        wire:click="$dispatch('pg:toggleColumn-<?php echo e($tableName); ?>', { field: '<?php echo e(data_get($column, 'field')); ?>'})"
                    >
                        <a
                            class="dropdown-item"
                            href="#"
                        >
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($column, 'hidden') === false): ?>
                                <?php if (isset($component)) { $__componentOriginal44e829c8d9d7b7526c011eb87286160d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal44e829c8d9d7b7526c011eb87286160d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.eye','data' => ['width' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.eye'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['width' => '18']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal44e829c8d9d7b7526c011eb87286160d)): ?>
<?php $attributes = $__attributesOriginal44e829c8d9d7b7526c011eb87286160d; ?>
<?php unset($__attributesOriginal44e829c8d9d7b7526c011eb87286160d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal44e829c8d9d7b7526c011eb87286160d)): ?>
<?php $component = $__componentOriginal44e829c8d9d7b7526c011eb87286160d; ?>
<?php unset($__componentOriginal44e829c8d9d7b7526c011eb87286160d); ?>
<?php endif; ?>
                            <?php else: ?>
                                <?php if (isset($component)) { $__componentOriginal491d64c78bef44602650443184da8c52 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal491d64c78bef44602650443184da8c52 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.eye-off','data' => ['width' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.eye-off'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['width' => '18']); ?>
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
<?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php echo data_get($column, 'title'); ?>

                        </a>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\bootstrap5\header\toggle-columns.blade.php ENDPATH**/ ?>