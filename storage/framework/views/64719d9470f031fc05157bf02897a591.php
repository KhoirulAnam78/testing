<?php if (isset($component)) { $__componentOriginal141c94207ae7f4a63273652a9801d02f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal141c94207ae7f4a63273652a9801d02f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'livewire-powergrid::components.editable','data' => ['tableName' => $tableName,'primaryKey' => $this->realPrimaryKey,'row' => $row,'field' => $field,'theme' => $theme,'currentTable' => $currentTable,'showErrorBag' => $showErrorBag]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('livewire-powergrid::editable'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tableName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tableName),'primaryKey' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($this->realPrimaryKey),'row' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($row),'field' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($field),'theme' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($theme),'currentTable' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentTable),'showErrorBag' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($showErrorBag)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('input', null, []); ?> 
        <div
            x-ref="editable"
            x-text="content"
            value="<?php echo e(html_entity_decode($row->{$field}, ENT_QUOTES, 'utf-8')); ?>"
            placeholder="<?php echo e(html_entity_decode($row->{$field}, ENT_QUOTES, 'utf-8')); ?>"
            contenteditable
            class="pg-single-line <?php echo e(theme_style($theme, 'editable.input')); ?>"
            <?php if(data_get($editable, 'saveOnMouseOut')): ?> x-on:mousedown.outside="save()" <?php endif; ?>
            x-on:keydown.enter="save()"
            :id="`editable-` + dataField + `-` + id"
            x-on:keydown.esc="cancel"
        >
        </div>
     <?php $__env->endSlot(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal141c94207ae7f4a63273652a9801d02f)): ?>
<?php $attributes = $__attributesOriginal141c94207ae7f4a63273652a9801d02f; ?>
<?php unset($__attributesOriginal141c94207ae7f4a63273652a9801d02f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal141c94207ae7f4a63273652a9801d02f)): ?>
<?php $component = $__componentOriginal141c94207ae7f4a63273652a9801d02f; ?>
<?php unset($__componentOriginal141c94207ae7f4a63273652a9801d02f); ?>
<?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\bootstrap5\editable.blade.php ENDPATH**/ ?>