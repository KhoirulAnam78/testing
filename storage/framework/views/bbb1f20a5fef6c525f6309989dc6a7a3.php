<div>
    <?php if ($__env->exists(data_get($setUp, 'header.includeViewOnTop'))) echo $__env->make(data_get($setUp, 'header.includeViewOnTop'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="dt--top-section">
        <div class="row">
            <div class="col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center">
                <div x-data="pgRenderActions">
                    <span class="<?php echo e(theme_style($theme, 'table.layout.actions')); ?>" x-html="toHtml"></span>
                </div>

                <div class="me-1">
                    <?php echo $__env->renderWhen(data_get($setUp, 'exportable'), data_get($theme, 'root') . '.header.export', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
                </div>

                <?php echo $__env->make(data_get($theme, 'root') . '.header.toggle-columns', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php if ($__env->exists(data_get($theme, 'root') . '.header.soft-deletes')) echo $__env->make(data_get($theme, 'root') . '.header.soft-deletes', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('livewire-powergrid.filter') === 'outside'): ?>
                    <?php echo $__env->make(data_get($theme, 'root') . '.header.filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php echo $__env->renderWhen(boolval(data_get($setUp, 'header.wireLoading')),
                    data_get($theme, 'root') . '.header.loading', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
            </div>
            <div class="col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3">
                <?php echo $__env->make(data_get($theme, 'root') . '.header.search', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        </div>
    </div>
    <?php echo $__env->renderWhen(data_get($setUp, 'exportable.batchExport.queues', 0), data_get($theme, 'root') . '.header.batch-exporting', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
    <?php if ($__env->exists(data_get($theme, 'root') . '.header.enabled-filters')) echo $__env->make(data_get($theme, 'root') . '.header.enabled-filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->renderWhen($multiSort, data_get($theme, 'root') . '.header.multi-sort', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
    <?php if ($__env->exists(data_get($setUp, 'header.includeViewOnBottom'))) echo $__env->make(data_get($setUp, 'header.includeViewOnBottom'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if ($__env->exists(data_get($theme, 'root') . '.header.message-soft-deletes')) echo $__env->make(data_get($theme, 'root') . '.header.message-soft-deletes', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\bootstrap5\header.blade.php ENDPATH**/ ?>