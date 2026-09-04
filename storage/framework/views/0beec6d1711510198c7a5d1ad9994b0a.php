<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'header.softDeletes')): ?>
    <div class="btn-group">
        <button
            class="btn btn-secondary btn-sm dropdown-toggle"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false"
        >
            <span>
                <?php if (isset($component)) { $__componentOriginalf86e8445c3b4b21e8fd571a52134e584 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf86e8445c3b4b21e8fd571a52134e584 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.trash','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.trash'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf86e8445c3b4b21e8fd571a52134e584)): ?>
<?php $attributes = $__attributesOriginalf86e8445c3b4b21e8fd571a52134e584; ?>
<?php unset($__attributesOriginalf86e8445c3b4b21e8fd571a52134e584); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf86e8445c3b4b21e8fd571a52134e584)): ?>
<?php $component = $__componentOriginalf86e8445c3b4b21e8fd571a52134e584; ?>
<?php unset($__componentOriginalf86e8445c3b4b21e8fd571a52134e584); ?>
<?php endif; ?>
            </span>
        </button>
        <ul class="dropdown-menu">
            <li wire:click="$dispatch('pg:softDeletes-<?php echo e($tableName); ?>', {softDeletes: ''})">
                <a
                    class="dropdown-item"
                    href="#"
                >
                    <?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.without_trashed'); ?>
                </a>
            </li>
            <li wire:click="$dispatch('pg:softDeletes-<?php echo e($tableName); ?>', {softDeletes: 'withTrashed'})">
                <a
                    class="dropdown-item"
                    href="#"
                >
                    <?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.with_trashed'); ?>
                </a>
            </li>
            <li wire:click="$dispatch('pg:softDeletes-<?php echo e($tableName); ?>', {softDeletes: 'onlyTrashed'})">
                <a
                    class="dropdown-item"
                    href="#"
                >
                    <?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.only_trashed'); ?>
                </a>
            </li>
        </ul>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views/vendor/livewire-powergrid/components/frameworks/bootstrap5/header/soft-deletes.blade.php ENDPATH**/ ?>