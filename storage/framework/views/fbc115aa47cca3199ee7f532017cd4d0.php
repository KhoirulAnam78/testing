<?php
    $columns = collect($columns)->map(function ($column) {
        return data_forget($column, 'rawQueries');
    });
?>

<div
    class="flex flex-col"
    <?php if($deferLoading): ?> wire:init="fetchDatasource" <?php endif; ?>
>
    <div
        id="power-grid-table-container"
        class="<?php echo e(theme_style($theme, 'table.layout.container')); ?>"
    >
        <div
            id="power-grid-table-base"
            class="<?php echo e(theme_style($theme, 'table.layout.base')); ?>"
        >
            <?php echo $__env->make(theme_style($theme, 'layout.header'), [
                'enabledFilters' => $enabledFilters,
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('livewire-powergrid.filter') === 'outside'): ?>
                <?php
                    $filtersFromColumns = $columns
                        ->filter(fn($column) => filled(data_get($column, 'filters')));
                ?>

                <?php echo $__env->renderWhen(
                    $filtersFromColumns->count() > 0,
                    'livewire-powergrid::components.frameworks.daisyui.filter'
                , array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div
                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'overflow-auto' => $readyToLoad,
                    'overflow-hidden' => !$readyToLoad,
                    theme_style($theme, 'table.layout.div'),
                ]); ?>"
            >
                <?php echo $__env->make($table, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

            <?php echo $__env->make(theme_style($theme, 'footer.view'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </div>
    <?php $__env->startPush('styles'); ?>
    <!-- PowerGrid daisy style -->
    <style>
        [wire\:target^="toggleDetail"] svg {
            @apply text-base-content
        }
    </style>
    <!-- END PowerGrid daisy style -->
    <?php $__env->stopPush(); ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\table-base.blade.php ENDPATH**/ ?>