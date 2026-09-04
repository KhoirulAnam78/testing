<tbody>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php
            $rowId = data_get($row, $this->realPrimaryKey);
            $class = theme_style($theme, 'table.body.tr');
        ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($setUp['detail'])): ?>
            <tr class="<?php echo e($class); ?>">
                <?php echo $__env->make('livewire-powergrid::components.row', [
                    'rowIndex' => $loop->index + 1,
                    'childIndex' => $childIndex,
                    'parentId' => $parentId
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </tr>

            <?php
                $hasDetailView = (bool) data_get(
                    collect($row->__powergrid_rules)->where('apply', true)->last(),
                    'detailView',
                );

                if ($hasDetailView) {
                    $detailView = data_get($row->__powergrid_rules, '0.detailView');
                    $rulesValues = data_get($row->__powergrid_rules, '0.options', []);
                } else {
                    $detailView = data_get($setUp, 'detail.view');
                    $rulesValues = data_get($setUp, 'detail.options', []);
                }
            ?>
            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('powergrid-detail', ['view' => $detailView,'options' => $rulesValues,'row-id' => $rowId,'tr-class' => ''.e($class).'','row' => $row->toArray(),'collapse-others' => data_get($setUp, 'detail.collapseOthers', false)]);

$__keyOuter = $__key ?? null;

$__key = 'powergrid-lazy-child-detail-'.e($rowId).'';
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2672063320-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
        <?php else: ?>
            <tr
                class="<?php echo e($class); ?>"
            >
                <?php echo $__env->make('livewire-powergrid::components.row', [
                    'rowIndex' => $loop->index + 1,
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </tr>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php echo $__env->renderWhen(isset($setUp['responsive']), 'livewire-powergrid::components.expand-container', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</tbody>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\livewire\lazy-child.blade.php ENDPATH**/ ?>