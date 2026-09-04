<?php
    $inputAttributes = new \Illuminate\View\ComponentAttributeBag([
        'class' => theme_style($theme, 'radio.input'),
    ]);

    $rules = collect($row->__powergrid_rules)
        ->where('apply', true)
        ->where('forAction', \PowerComponents\LivewirePowerGrid\Components\Rules\RuleManager::TYPE_RADIO)
        ->last();

    if (isset($rules['attributes'])) {
        foreach ($rules['attributes'] as $key => $value) {
            $inputAttributes = $inputAttributes->merge([
                $key => $value,
            ]);
        }
    }

    $disable = (bool) data_get($rules, 'disable');
    $hide = (bool) data_get($rules, 'hide');
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hide): ?>
    <td
        class="<?php echo e(theme_style($theme, 'radio.td')); ?>"
    >
    </td>
<?php elseif($disable): ?>
    <td
        class="<?php echo e(theme_style($theme, 'radio.td')); ?>"
    >
        <div class="<?php echo e(theme_style($theme, 'radio.base')); ?>">
            <label class="<?php echo e(theme_style($theme, 'radio.label')); ?>">
                <input
                    <?php echo e($inputAttributes); ?>

                    disabled
                    type="radio"
                >
            </label>
        </div>
    </td>
<?php else: ?>
    <td
        class="<?php echo e(theme_style($theme, 'radio.th')); ?>"
    >
        <div class="<?php echo e(theme_style($theme, 'radio.base')); ?>">
            <label class="<?php echo e(theme_style($theme, 'radio.label')); ?>">
                <input
                    type="radio"
                    <?php echo e($inputAttributes); ?>

                    wire:model.live="selectedRow"
                    value="<?php echo e($attribute); ?>"
                >
            </label>
        </div>
    </td>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\radio-row.blade.php ENDPATH**/ ?>