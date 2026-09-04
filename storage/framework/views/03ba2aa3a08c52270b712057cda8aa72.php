<div class="md:flex md:flex-row w-full">
    <div x-data="pgRenderActions">
        <span class="pg-actions" x-html="toHtml"></span>
    </div>
    <div class="flex flex-row justify-center items-center text-sm">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($exportOptions) > 0): ?>
            <div class="mr-2 mt-2 sm:mt-0">
                <?php echo $__env->make(data_get($theme, 'root') . '.export', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if ($__env->exists(data_get($theme, 'root') . '.toggle-columns')) echo $__env->make(data_get($theme, 'root') . '.toggle-columns', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>

    <!-- LOADING -->
    <?php echo $__env->make(data_get($theme, 'root') . '.loading', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\actions.blade.php ENDPATH**/ ?>