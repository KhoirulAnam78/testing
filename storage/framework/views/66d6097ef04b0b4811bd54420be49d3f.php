<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'detail.state.' . $rowId)): ?>
    <?php
        $detailView = (bool) data_get(
            collect($row->__powergrid_rules)
                ->where('apply', true)
                ->last(),
            'detailView',
        );
    ?>

    <td colspan="999">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($detailView): ?>
            <?php echo $__env->renderWhen(data_get($setUp, 'detail.state.' . $row->{$this->realPrimaryKey}),
                $rulesValues['detailView'][0]['detailView'],
                [
                    'id' => data_get($row, $this->realPrimaryKey),
                    'options' => array_merge(
                        data_get($setUp, 'detail.options'),
                        $rulesValues['detailView']['0']['options']),
                ]
            , array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
        <?php else: ?>
            <?php echo $__env->renderWhen(data_get($setUp, 'detail.state.' . $row->{$this->realPrimaryKey}),
                data_get($setUp, 'detail.view'),
                [
                    'id' => data_get($row, $this->realPrimaryKey),
                    'options' => data_get($setUp, 'detail.options'),
                ]
            , array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </td>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\table\detail.blade.php ENDPATH**/ ?>