<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['save', 'reset']));

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

foreach (array_filter((['save', 'reset']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="d-flex gap-2">
    <button type="button" class="btn btn-primary btn-sm" wire:click="<?php echo e($save); ?>" wire:loading.attr="disabled">
        <i class="ri-save-line"></i> SIMPAN
    </button>
    <button type="button" class="btn btn-light btn-sm" wire:click="<?php echo e($reset); ?>">
        Reset
    </button>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\tab-save-button.blade.php ENDPATH**/ ?>