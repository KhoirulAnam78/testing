<div class="hidden lg:!block">
    <div
        wire:loading
        wire:target.except="toggleDetail"
        class="mt-2 hidden"
    >
        <?php if (isset($component)) { $__componentOriginal0082464271674f1827774a0300cf980d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0082464271674f1827774a0300cf980d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.loading','data' => ['class' => 'text-base-content h-5 w-5 animate-spin']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.loading'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'text-base-content h-5 w-5 animate-spin']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0082464271674f1827774a0300cf980d)): ?>
<?php $attributes = $__attributesOriginal0082464271674f1827774a0300cf980d; ?>
<?php unset($__attributesOriginal0082464271674f1827774a0300cf980d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0082464271674f1827774a0300cf980d)): ?>
<?php $component = $__componentOriginal0082464271674f1827774a0300cf980d; ?>
<?php unset($__componentOriginal0082464271674f1827774a0300cf980d); ?>
<?php endif; ?>
    </div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\header\loading.blade.php ENDPATH**/ ?>