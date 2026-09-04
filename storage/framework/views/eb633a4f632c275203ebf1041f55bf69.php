<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'header.searchInput')): ?>
    <label class="input relative group">
        <?php if (isset($component)) { $__componentOriginal2523372fb791360073597b6ef440d3e5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2523372fb791360073597b6ef440d3e5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.search','data' => ['class' => ''.e(theme_style($theme, 'searchBox.iconSearch')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.search'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => ''.e(theme_style($theme, 'searchBox.iconSearch')).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2523372fb791360073597b6ef440d3e5)): ?>
<?php $attributes = $__attributesOriginal2523372fb791360073597b6ef440d3e5; ?>
<?php unset($__attributesOriginal2523372fb791360073597b6ef440d3e5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2523372fb791360073597b6ef440d3e5)): ?>
<?php $component = $__componentOriginal2523372fb791360073597b6ef440d3e5; ?>
<?php unset($__componentOriginal2523372fb791360073597b6ef440d3e5); ?>
<?php endif; ?>

        <input
            wire:model.live.debounce.700ms="search"
            type="search"
            class="<?php echo e(theme_style($theme, 'searchBox.input')); ?>"
            placeholder="<?php echo e(trans('livewire-powergrid::datatable.placeholders.search')); ?>"
        />

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search): ?>
            <span class="absolute opacity-0 group-hover:opacity-100 transition-all inset-y-0 right-0 flex items-center">
                <span class="p-2 rounded-full focus:outline-none focus:shadow-outline cursor-pointer">
                    <a
                        class="cursor-pointer"
                        wire:click.prevent="$set('search','')"
                    >
                        <?php if (isset($component)) { $__componentOriginal7d60ff8e342013a80b7e0c15ae7afd01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7d60ff8e342013a80b7e0c15ae7afd01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.x','data' => ['class' => 'w-4 h-4 '.e(theme_style($theme, 'searchBox.iconClose')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.x'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4 '.e(theme_style($theme, 'searchBox.iconClose')).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7d60ff8e342013a80b7e0c15ae7afd01)): ?>
<?php $attributes = $__attributesOriginal7d60ff8e342013a80b7e0c15ae7afd01; ?>
<?php unset($__attributesOriginal7d60ff8e342013a80b7e0c15ae7afd01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7d60ff8e342013a80b7e0c15ae7afd01)): ?>
<?php $component = $__componentOriginal7d60ff8e342013a80b7e0c15ae7afd01; ?>
<?php unset($__componentOriginal7d60ff8e342013a80b7e0c15ae7afd01); ?>
<?php endif; ?>
                    </a>
                </span>
            </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </label>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\header\search.blade.php ENDPATH**/ ?>