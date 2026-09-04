<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'header.softDeletes')): ?>
    <div
        class="dropdown"
    >
        <div
            tabindex="0"
            role="button"
            class="btn btn-sm btn-primary"
        >
            <?php if (isset($component)) { $__componentOriginalf86e8445c3b4b21e8fd571a52134e584 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf86e8445c3b4b21e8fd571a52134e584 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.trash','data' => ['class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.trash'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-5 w-5']); ?>
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
        </div>
        <div
            tabindex="0"
            class="dropdown-content bg-base-100 z-1 shadow-md"
        >
            <ul class="menu bg-base-200 rounded-box w-56">
                <li>
                    <a x-on:click="$wire.dispatch('pg:softDeletes-<?php echo e($tableName); ?>', {softDeletes: ''});"><?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.without_trashed'); ?></a>
                </li>
                <li>
                    <a x-on:click="$wire.dispatch('pg:softDeletes-<?php echo e($tableName); ?>', {softDeletes: 'withTrashed'});"><?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.with_trashed'); ?></a>
                </li>
                <li>
                    <a x-on:click="$wire.dispatch('pg:softDeletes-<?php echo e($tableName); ?>', {softDeletes: 'onlyTrashed'});"><?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.only_trashed'); ?></a>
                </li>
            </ul>

        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\header\soft-deletes.blade.php ENDPATH**/ ?>