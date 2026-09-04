<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['edit', 'delete', 'id', 'confirm']));

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

foreach (array_filter((['edit', 'delete', 'id', 'confirm']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<button type="button" class="btn btn-info btn-sm mb-2" wire:click="<?php echo e($edit); ?>('<?php echo e(\Illuminate\Support\Facades\Crypt::encrypt($id)); ?>')">
    <i class="ri-file-edit-line"></i> Kelola
</button>
<button type="button" class="btn btn-danger btn-sm mb-2" wire:click="<?php echo e($delete); ?>('<?php echo e(\Illuminate\Support\Facades\Crypt::encrypt($id)); ?>')" wire:confirm="<?php echo e($confirm); ?>">
    <i class="ri-delete-bin-line"></i> Hapus
</button>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\row-actions.blade.php ENDPATH**/ ?>