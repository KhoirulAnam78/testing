<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'target' => null,
    'message' => 'Memuat halaman...',
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
    'target' => null,
    'message' => 'Memuat halaman...',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    wire:loading.delay.flex
    <?php if($target): ?> wire:target="<?php echo e($target); ?>" <?php endif; ?>
    class="position-fixed top-0 start-0 w-100 h-100 align-items-center justify-content-center"
    style="display: none; z-index: 2000; background: rgba(0, 0, 0, .25); cursor: wait;"
    role="status"
    aria-live="polite"
    aria-busy="true"
>
    <div class="card border-0 shadow">
        <div class="card-body d-flex align-items-center gap-3 px-4 py-3">
            <span class="spinner-border text-primary" aria-hidden="true"></span>
            <span class="fw-semibold"><?php echo e($message); ?></span>
        </div>
    </div>
</div><?php /**PATH D:\laragon\www\sistem-blok\resources\views/components/full-page-loading.blade.php ENDPATH**/ ?>