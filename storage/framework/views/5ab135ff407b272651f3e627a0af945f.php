<tbody
    x-cloak
    expand
    <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = ''.e('expand-' . substr($rowId, 0, 6)).''; ?>wire:key="<?php echo e('expand-' . substr($rowId, 0, 6)); ?>"
    x-show="hasHiddenElements"
>
    <tr
        x-show="expanded == '<?php echo e($rowId); ?>'"
        x-transition
        class="text-pg-primary-500 border-pg-primary-100 dark:text-pg-primary-200 break-words w-full text-sm"
    >
        <td colspan="999">
            <div class="flex gap-x-6 gap-y-2 flex-wrap p-2 responsive-row-expand-container"></div>
        </td>
    </tr>
</tbody>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\expand-container.blade.php ENDPATH**/ ?>