<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'class' => null,
    'options' => [],
    'option-label' => null,
    'option-value' => null,
    'placeholder' => null,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'class' => null,
    'options' => [],
    'option-label' => null,
    'option-value' => null,
    'placeholder' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div>
    <div>class: <?php echo e($class); ?></div>
    <div>options: <?php echo e(json_encode($options)); ?></div>
    <div>option-label: <?php echo e($optionLabel); ?></div>
    <div>option-value: <?php echo e($optionValue); ?></div>
    <div>placeholder: <?php echo e($placeholder); ?></div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\tests\dynamic-select.blade.php ENDPATH**/ ?>