<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'rowIndex' => 0,
    'childIndex' => null,
    'parentId' => null,
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
    'rowIndex' => 0,
    'childIndex' => null,
    'parentId' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php echo $__env->renderWhen(isset($setUp['responsive']), data_get($theme, 'root') . '.toggle-detail-responsive', [
    'view' => data_get($setUp, 'detail.viewIcon') ?? null,
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>

<?php
    $defaultCollapseIcon = data_get($theme, 'root') . '.toggle-detail';
?>

<?php echo $__env->renderWhen(data_get($setUp, 'detail.showCollapseIcon'),
    data_get(collect($row->__powergrid_rules)->last(), 'toggleDetailView') ?? $defaultCollapseIcon,
    [
        'view' => data_get($setUp, 'detail.viewIcon') ?? null,
    ]
, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>

<?php echo $__env->renderWhen($radio && $radioAttribute, 'livewire-powergrid::components.radio-row', [
    'attribute' => $row->{$radioAttribute},
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>

<?php echo $__env->renderWhen($checkbox && $checkboxAttribute, 'livewire-powergrid::components.checkbox-row', [
    'attribute' => $row->{$checkboxAttribute},
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
    <?php
        $field = data_get($column, 'field');
        $content = $row->{$field} ?? '';
        $templateContent = null;

        if (is_array($content)) {
            $template = data_get($column, 'template');
            $templateContent = $content;
            $content = '';
        }

        $contentClassField = data_get($column, 'contentClassField');

        if ($content instanceof \UnitEnum) {
            $content = $content instanceof \BackedEnum 
                ? $content->value 
                : $content->name;
        }

        $content = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $content ?? '');
        $field = data_get($column, 'dataField', data_get($column, 'field'));

        $contentClass = data_get($column, 'contentClasses');

        if (is_array(data_get($column, 'contentClasses'))) {
            $contentClass = array_key_exists($content, data_get($column, 'contentClasses'))
                ? data_get($column, 'contentClasses')[$content]
                : '';
        }
    ?>
    <td
        class="<?php echo \Illuminate\Support\Arr::toCssClasses([
            theme_style($theme, 'table.body.td'),
            data_get($column, 'bodyClass'),
        ]); ?>"
        style="<?php echo \Illuminate\Support\Arr::toCssStyles([
            'display:none' => data_get($column, 'hidden'),
            data_get($column, 'bodyStyle'),
        ]) ?>"
        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'row-'.e(substr($rowId, 0, 6)).'-'.e($field).'-'.e($childIndex ?? 0).''; ?>wire:key="row-<?php echo e(substr($rowId, 0, 6)); ?>-<?php echo e($field); ?>-<?php echo e($childIndex ?? 0); ?>"
        data-column="<?php echo e(data_get($column, 'isAction') ? 'actions' : $field); ?>"
    >
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count(data_get($column, 'customContent')) > 0): ?>
            <?php echo $__env->make(data_get($column, 'customContent.view'), data_get($column, 'customContent.params'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php else: ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($column, 'isAction')): ?>
                <div class="pg-actions">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(method_exists($this, 'actionsFromView') && ($actionsFromView = $this->actionsFromView($row))): ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'actions-view-'.e(data_get($row, $this->realPrimaryKey)).''; ?>wire:key="actions-view-<?php echo e(data_get($row, $this->realPrimaryKey)); ?>">
                            <?php echo $actionsFromView; ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div wire:replace.self>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($column, 'isAction')): ?>
                            <div
                                x-data="pgRenderActions({ rowId: <?php echo \Illuminate\Support\Js::from(data_get($row, $this->realPrimaryKey))->toHtml() ?>, parentId: <?php echo \Illuminate\Support\Js::from($parentId)->toHtml() ?> })"
                                class="<?php echo e(theme_style($theme, 'table.body.tdActionsContainer')); ?>"
                                x-html="toHtml"
                            >
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php
                $showEditOnClick = $this->shouldShowEditOnClick($column, $row);
            ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showEditOnClick === true): ?>
                <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([$contentClassField, $contentClass]); ?>">
                    <?php echo $__env->make(theme_style($theme, 'editable.view') ?? null, [
                        'editable' => data_get($column, 'editable'),
                    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </span>
            <?php elseif(count(data_get($column, 'toggleable')) > 0): ?>
                <?php
                    $showToggleable = $this->shouldShowToggleable($column, $row);
                ?>
                <?php echo $__env->make(theme_style($theme, 'toggleable.view'), ['tableName' => $tableName], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php else: ?>
                <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([$contentClassField, $contentClass]); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($templateContent)): ?>
                        <div
                            x-data="pgRenderRowTemplate({
                                parentId: <?php echo \Illuminate\Support\Js::from($parentId)->toHtml() ?>,
                                templateContent: <?php echo \Illuminate\Support\Js::from($templateContent)->toHtml() ?>
                            })"
                            x-html="rendered"
                        >
                        </div>
                    <?php else: ?>
                        <div><?php echo data_get($column, 'index') ? $rowIndex : $content; ?></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </td>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\row.blade.php ENDPATH**/ ?>