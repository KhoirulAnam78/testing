<td
    x-data="() => {
        return {
            collapsed: false,
            loading: false,
            collapseOthers: <?php echo \Illuminate\Support\Js::from(data_get($setUp, 'detail.collapseOthers', false))->toHtml() ?>,
            toggleDetail() {
                const isOpen = this.collapsed;
    
                if (this.collapseOthers) {
                    this.$dispatch('pg-toggle-detail-<?php echo e($tableName); ?>-hidden-all');
                    expandedId = '<?php echo e($rowId); ?>';
                }
    
                this.loading = true;
                this.collapsed = !isOpen;
    
                this.$dispatch('pg-toggle-detail-<?php echo e($tableName); ?>-<?php echo e($rowId); ?>', {
                    collapsed: this.collapsed
                });
            }
        }
    }"
    x-on:pg-toggle-detail-<?php echo e($tableName); ?>-hidden-all.window="collapsed = false"
    x-on:pg-toggle-detail-<?php echo e($tableName); ?>-loaded.window="loading = false;"
    class="<?php echo e(theme_style($theme, 'table.body.td')); ?>"
>
    <div x-on:click="toggleDetail()">
        <div>
            <?php if ($__env->exists(data_get($setUp, 'detail.viewIcon'))) echo $__env->make(data_get($setUp, 'detail.viewIcon'), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (data_get($setUp, 'detail.viewIcon'))): ?>
                <div
                    x-bind:class='{
                        "bs5-rotate-90": collapsed && expandedId === "<?php echo e($rowId); ?>",
                        "bs5-rotate-0": !collapsed
                    }'>
                    <?php if (isset($component)) { $__componentOriginala9a1a3fbd29f77413ac389af21d329ee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala9a1a3fbd29f77413ac389af21d329ee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.icons.arrow','data' => ['class' => 'bs5-w-h-1_5em','style' => 'cursor:pointer']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::icons.arrow'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'bs5-w-h-1_5em','style' => 'cursor:pointer']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala9a1a3fbd29f77413ac389af21d329ee)): ?>
<?php $attributes = $__attributesOriginala9a1a3fbd29f77413ac389af21d329ee; ?>
<?php unset($__attributesOriginala9a1a3fbd29f77413ac389af21d329ee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala9a1a3fbd29f77413ac389af21d329ee)): ?>
<?php $component = $__componentOriginala9a1a3fbd29f77413ac389af21d329ee; ?>
<?php unset($__componentOriginala9a1a3fbd29f77413ac389af21d329ee); ?>
<?php endif; ?>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </td>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\bootstrap5\toggle-detail.blade.php ENDPATH**/ ?>