<?php
    $value = (int) $row->{data_get($column, 'field')};
    $trueValue = data_get($column, 'toggleable')['default'][0];
    $falseValue = data_get($column, 'toggleable')['default'][1];
?>

<div class="flex flex-row justify-center">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showToggleable): ?>
        <?php
            $params = [
                'id' => data_get($row, $this->realPrimaryKey),
                'isHidden' => !$showToggleable,
                'tableName' => $tableName,
                'field' => data_get($column, 'field'),
                'toggle' => $value,
                'trueValue' => $trueValue,
                'falseValue' => $falseValue,
            ];
        ?>
        <input
            x-data="pgToggleable(<?php echo \Illuminate\Support\Js::from($params)->toHtml() ?>)"
            type="checkbox"
            class="toggle toggle-sm toggle-success"
            :checked="toggle"
            x-on:click="save"
        />
    <?php else: ?>
        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
            'text-xs px-4 w-auto py-1 text-center rounded-md',
            'bg-error text-error-content' => $value === 0,
            'bg-info text-info-content' => $value === 1,
        ]); ?>">
            <?php echo e($value === 0 ? $falseValue : $trueValue); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\toggleable.blade.php ENDPATH**/ ?>