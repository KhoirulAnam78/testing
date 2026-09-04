<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'inline' => true,
    'filter' => null,
    'tableName' => null,
    'multiple' => true,
    'initialValues' => [],
    'options' => [],
    'title' => '',
    'theme' => null,
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
    'inline' => true,
    'filter' => null,
    'tableName' => null,
    'multiple' => true,
    'initialValues' => [],
    'options' => [],
    'title' => '',
    'theme' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $framework = config('livewire-powergrid.plugins.select');
    $rawCollection = collect(data_get($filter, 'dataSource') ?? data_get($filter, 'computedDatasource'));

    // Determine if the collection is grouped (optgroup)
    $isGrouped =
        $rawCollection->first() &&
        is_array($rawCollection->first()) &&
        array_key_exists('options', $rawCollection->first());

    $collection = $isGrouped
        ? $rawCollection // optgroup structure, don't transform
    : $rawCollection->transform(function ($entry) use ($filter) {
        if (is_array($entry)) {
            $entry = collect($entry);
        }
        return $entry->only([data_get($filter, 'optionValue'), data_get($filter, 'optionLabel')]);
    });

$params = [
    'tableName' => $tableName,
    'label' => $title,
    'dataField' => data_get($filter, 'field'),
    'optionValue' => data_get($filter, 'optionValue'),
    'optionLabel' => data_get($filter, 'optionLabel'),
    'options' => data_get($filter, 'params'),
    'initialValues' => $initialValues,
    'appliedFilters' => data_get($this->filters, 'multi_select', []),
    'framework' => $framework[config('livewire-powergrid.plugins.select.default')],
];

if (\Illuminate\Support\Arr::has($filter, ['url', 'method'])) {
    $params['asyncData'] = [
        'url' => data_get($filter, 'url'),
        'method' => data_get($filter, 'method'),
        'parameters' => data_get($filter, 'parameters'),
        'headers' => data_get($filter, 'headers'),
    ];
}

$alpineData =
    $framework['default'] == 'tom'
        ? 'pgTomSelect(' . \Illuminate\Support\Js::from($params) . ')'
        : 'pgSlimSelect(' . \Illuminate\Support\Js::from($params) . ')';
?>

<div
    x-cloak
    wire:ignore
    x-data="<?php echo e($alpineData); ?>"
>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($filter)): ?>
        <div class="<?php echo e(theme_style($theme, 'filterSelect.base')); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$inline): ?>
                <label class="block text-sm font-semibold text-pg-primary-700 dark:text-pg-primary-300">
                    <?php echo e($title); ?>

                </label>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <select
                <?php if($multiple): ?> multiple <?php endif; ?>
                class="<?php echo e(theme_style($theme, 'filterMultiSelect.select')); ?>"
                wire:model="filters.multi_select.<?php echo e(data_get($filter, 'field')); ?>.values"
                x-ref="select_picker_<?php echo e(data_get($filter, 'field')); ?>_<?php echo e($tableName); ?>"
            >
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!data_get($params, 'options.disableOptionAll', false)): ?>
                    <option value=""><?php echo e(trans('livewire-powergrid::datatable.multi_select.all')); ?></option>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(blank(data_get($params, 'asyncData', []))): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $collection->toArray(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($item['options']) && isset($item['label'])): ?>
                            <optgroup label="<?php echo e($item['label']); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $item['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <option
                                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'multi-select-option-'.e($loop->parent->index).'-'.e($loop->index).''; ?>wire:key="multi-select-option-<?php echo e($loop->parent->index); ?>-<?php echo e($loop->index); ?>"
                                        value="<?php echo e(data_get($subItem, data_get($filter, 'optionValue'))); ?>"
                                    >
                                        <?php echo e(data_get($subItem, data_get($filter, 'optionLabel'))); ?>

                                    </option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </optgroup>
                        <?php else: ?>
                            <option
                                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'multi-select-option-'.e($loop->index).''; ?>wire:key="multi-select-option-<?php echo e($loop->index); ?>"
                                value="<?php echo e(data_get($item, data_get($filter, 'optionValue'))); ?>"
                            >
                                <?php echo e(data_get($item, data_get($filter, 'optionLabel'))); ?>

                            </option>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\inputs\select.blade.php ENDPATH**/ ?>