
<tr class="<?php echo e(theme_style($theme, 'table.body.tr'). ' '.theme_style($theme, 'table.body.trSummarize')); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'detail.showCollapseIcon')): ?>
        <td class="<?php echo e(theme_style($theme, 'table.body.td')); ?>"></td>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($checkbox): ?>
        <td class="<?php echo e(theme_style($theme, 'table.body.td')); ?>"></td>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->visibleColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <td class="<?php echo e(theme_style($theme, 'table.body.tdSummarize') . ' '.data_get($column, 'bodyClass') ?? ''); ?>"
            style="<?php echo e(data_get($column, 'hidden') === true ? 'display:none;': ''); ?> <?php echo e(data_get($column, 'bodyStyle') ?? ''); ?>">
            <?php echo $__env->make('livewire-powergrid::components.summarize', [
                'sum' => data_get($column, 'properties.summarize.sum.header') ? data_get($column, 'properties.summarize_values.sum') : null,
                'labelSum' => data_get($column, 'properties.summarize.sum.label'),

                'count' => data_get($column, 'properties.summarize.count.header') ? data_get($column, 'properties.summarize_values.count') : null,
                'labelCount' => data_get($column, 'properties.summarize.count.label'),

                'min' => data_get($column, 'properties.summarize.min.header') ? data_get($column, 'properties.summarize_values.min') : null,
                'labelMin' => data_get($column, 'properties.summarize.min.label'),

                'max' => data_get($column, 'properties.summarize.max.header') ? data_get($column, 'properties.summarize_values.max') : null,
                'labelMax' => data_get($column, 'properties.summarize.max.label'),

                'avg' => data_get($column, 'properties.summarize.avg.header') ? data_get($column, 'properties.summarize_values.avg') : null,
                'labelAvg' => data_get($column, 'properties.summarize.avg.label'),
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </td>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($actions) && count($actions)): ?>
        <th class="<?php echo e(theme_style($theme, 'table.header.th') .' '. data_get($column, 'headerClass')); ?>" scope="col"
           colspan="<?php echo e(count($actions)); ?>">
        </th>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tr>

<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\table-header.blade.php ENDPATH**/ ?>