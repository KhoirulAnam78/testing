<?php
    $inputAttributes = new \Illuminate\View\ComponentAttributeBag([
        'class' => theme_style($theme, 'checkbox.input'),
    ]);

    $rules = collect($row->__powergrid_rules)
        ->where('apply', true)
        ->where('forAction', \PowerComponents\LivewirePowerGrid\Components\Rules\RuleManager::TYPE_CHECKBOX)
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
        class="<?php echo e(theme_style($theme, 'checkbox.th')); ?>"
    >
    </td>
<?php elseif($disable): ?>
    <td
        class="<?php echo e(theme_style($theme, 'checkbox.th')); ?>"
    >
        <div class="<?php echo e(theme_style($theme, 'checkbox.base')); ?>">
            <label class="<?php echo e(theme_style($theme, 'checkbox.label')); ?>">
                <input
                    <?php echo e($inputAttributes); ?>

                    disabled
                    type="checkbox"
                >
            </label>
        </div>
    </td>
<?php else: ?>
    <td
        class="<?php echo e(theme_style($theme, 'checkbox.th')); ?>"
    >
        <div class="<?php echo e(theme_style($theme, 'checkbox.base')); ?>">
            <label class="<?php echo e(theme_style($theme, 'checkbox.label')); ?>">
                <input
                    x-data="{}"
                    type="checkbox"
                    <?php echo e($inputAttributes); ?>

                    x-on:click="window.Alpine.store('pgBulkActions').add($event.target.value, '<?php echo e($tableName); ?>')"
                    wire:model="checkboxValues"
                    value="<?php echo e($attribute); ?>"
                >
            </label>
        </div>
    </td>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\checkbox-row.blade.php ENDPATH**/ ?>