<?php
    $columns = collect($columns)->map(function ($column) {
        return data_forget($column, 'rawQueries');
    });
?>
<div <?php if($deferLoading): ?> wire:init="fetchDatasource" <?php endif; ?>>
    <div class="col-md-12">
        <?php echo $__env->make(theme_style($theme, 'layout.header'), [
            'enabledFilters' => $enabledFilters,
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('livewire-powergrid.filter') === 'outside'): ?>
        <?php
            $filtersFromColumns = $columns
                ->filter(fn($column) => filled(data_get($column, 'filters')));
        ?>

        <?php echo $__env->renderWhen(
            $filtersFromColumns->count() > 0,
            'livewire-powergrid::components.frameworks.bootstrap5.filter'
        , array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div
        class="<?php echo e(theme_style($theme, 'table.layout.div')); ?>"
    >
        <?php echo $__env->make($table, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
    <div class="row">
        <div class="col-12 overflow-auto">
            <?php echo $__env->make(theme_style($theme, 'footer.view'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views/vendor/livewire-powergrid/components/frameworks/bootstrap5/table-base.blade.php ENDPATH**/ ?>