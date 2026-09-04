<div class="p-2 bg-white border border-pg-primary-200">
    <div>Id <?php echo e($id); ?></div>
    <div>Options <?php echo json_encode($options, 15, 512) ?></div>

    <div class="flex justify-end">
        <button
            wire:click.prevent="toggleDetail('<?php echo e($id); ?>')"
            class="p-1 text-xs bg-red-600 text-white rounded-lg"
        >Close</button>
    </div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\tests\detail-rules.blade.php ENDPATH**/ ?>