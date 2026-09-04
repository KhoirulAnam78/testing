<tr
    x-data="{ collapsed: <?php if ((object) ('show') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('show'->value()); ?>')<?php echo e('show'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('show'); ?>')<?php endif; ?>, collapseOthers: <?php if ((object) ('collapseOthers') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('collapseOthers'->value()); ?>')<?php echo e('collapseOthers'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('collapseOthers'); ?>')<?php endif; ?> }"
    class="<?php echo e($trClass); ?>"
>
    <td
        x-show="collapsed || (collapsed && collapseOthers && expandedId == '<?php echo e($rowId); ?>')"
        colspan="999"
    >
        <?php echo $__env->renderWhen($show, $view, [
            'id' => $rowId,
            'options' => $options,
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
    </td>
</tr>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\livewire\detail.blade.php ENDPATH**/ ?>