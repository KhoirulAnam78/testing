<?php
    $params = [
        'id' => data_get($row, $this->realPrimaryKey),
        'isHidden' => !$showToggleable,
        'tableName' => $tableName,
        'field' => data_get($column, 'field'),
        'toggle' => (int) $row->{data_get($column, 'field')},
        'trueValue' => data_get($column, 'toggleable')['default'][0],
        'falseValue' => data_get($column, 'toggleable')['default'][1],
    ];
?>

<div x-data="pgToggleable(<?php echo \Illuminate\Support\Js::from($params)->toHtml() ?>)">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($column, 'toggleable')['enabled'] && $showToggleable === true): ?>
        <div class="form-check form-switch">
            <label>
                <input
                    x-on:click="save()"
                    class="form-check-input"
                    :checked="toggle === 1"
                    type="checkbox"
                >
            </label>
        </div>
    <?php else: ?>
        <div class="text-center">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row->{data_get($column, 'field')} == 0): ?>
                <div
                    x-text="falseValue"
                    style="padding-top: 0.1em; padding-bottom: 0.1rem"
                    class="badge bg-danger"
                >
                </div>
            <?php else: ?>
                <div
                    x-text="trueValue"
                    style="padding-top: 0.1em; padding-bottom: 0.1rem"
                    class="badge bg-success"
                >
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\bootstrap5\toggleable.blade.php ENDPATH**/ ?>