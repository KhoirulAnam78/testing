<div
    <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'toggle-filters-'.e($tableName).''; ?>wire:key="toggle-filters-<?php echo e($tableName); ?>"
    id="toggle-filters"
    class="flex mr-2 mt-2 sm:mt-0 gap-3"
>
    <button
        wire:click="toggleFilters"
        type="button"
        class="btn btn-sm btn-primary"
    >
        <?php if (isset($component)) { $__componentOriginal741000299fce87de7e024358cf3b3f95 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal741000299fce87de7e024358cf3b3f95 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.filter','data' => ['class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.filter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-4 w-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal741000299fce87de7e024358cf3b3f95)): ?>
<?php $attributes = $__attributesOriginal741000299fce87de7e024358cf3b3f95; ?>
<?php unset($__attributesOriginal741000299fce87de7e024358cf3b3f95); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal741000299fce87de7e024358cf3b3f95)): ?>
<?php $component = $__componentOriginal741000299fce87de7e024358cf3b3f95; ?>
<?php unset($__componentOriginal741000299fce87de7e024358cf3b3f95); ?>
<?php endif; ?>
    </button>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\header\filters.blade.php ENDPATH**/ ?>