<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'inline' => null,
    'filter' => null,
    'column' => '',
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
    'inline' => null,
    'filter' => null,
    'column' => '',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php

    $fieldClassName = data_get($filter, 'className');

    $field = data_get($filter, 'field');

    $componentAttributes = (array) data_get($filter, 'attributes');

    $defaultAttributes = $fieldClassName::getWireAttributes(
        $field,
        array_merge($filter, ['title' => data_get($column, 'title'), 'placeholder' => data_get($column, 'placeholder')])
    );

    $filterClasses = theme_style($theme, 'filterNumber.input');

    $placeholder = data_get($filter, 'placeholder');

    $params = array_merge([...data_get($filter, 'attributes'), ...$defaultAttributes, $filterClasses], $filter);
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($params['component']): ?>
    <?php unset($params['attributes']); ?>

    <?php if (isset($component)) { $__componentOriginal511d4862ff04963c3c16115c05a86a9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal511d4862ff04963c3c16115c05a86a9d = $attributes; } ?>
<?php $component = Illuminate\View\DynamicComponent::resolve(['component' => $params['component']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dynamic-component'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\DynamicComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['attributes' => new \Illuminate\View\ComponentAttributeBag($params)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $attributes = $__attributesOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $component = $__componentOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__componentOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
<?php else: ?>
    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
        'space-y-1' => !$inline,
        theme_style($theme, 'filterNumber.base')
    ]); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$inline): ?>
            <label class="label">
                <?php echo e($title); ?>

            </label>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
            'w-full space-y-2 sm:flex gap-3 sm:space-y-0' => !$inline,
            'flex flex-col space-y-1.5' => $inline,
        ]); ?>">
            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['pl-0 w-full sm:w-1/2' => !$inline]); ?>">
                <input
                    <?php echo e($defaultAttributes['inputStartAttributes']); ?>

                    style="<?php echo e(data_get($column, 'headerStyle')); ?>"
                    type="text"
                    class="<?php echo e($filterClasses); ?>"
                    placeholder="<?php echo e($placeholder['min'] ?? __('Min')); ?>"
                >
            </div>
            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['pl-0 w-full sm:w-1/2' => !$inline]); ?>">
                <input
                    <?php echo e($defaultAttributes['inputEndAttributes']); ?>

                    <?php if($inline): ?> style="<?php echo e(data_get($column, 'headerStyle')); ?>" <?php endif; ?>
                    type="text"
                    class="<?php echo e($filterClasses); ?>"
                    placeholder="<?php echo e($placeholder['max'] ?? __('Max')); ?>"
                >
            </div>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\filters\number.blade.php ENDPATH**/ ?>