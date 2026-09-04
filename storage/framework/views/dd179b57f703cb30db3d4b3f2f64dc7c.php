<div class="p-2 bg-white border border-pg-primary-200">
    <div>Id <?php echo e($id); ?></div>
    <div>Options <?php echo json_encode($options, 15, 512) ?></div>
    <?php
        \Illuminate\Support\Facades\Log::info($id);
    ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\tests\detail.blade.php ENDPATH**/ ?>