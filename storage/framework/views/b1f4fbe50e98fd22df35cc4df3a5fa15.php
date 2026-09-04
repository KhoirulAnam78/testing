<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'header.showMessageSoftDeletes') &&
        ($softDeletes === 'withTrashed' || $softDeletes === 'onlyTrashed')): ?>
    <div
        class="alert alert-warning my-1"
        role="alert"
    >
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($softDeletes === 'withTrashed'): ?>
            <?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.message_with_trashed'); ?>
        <?php else: ?>
            <?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.message_only_trashed'); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views/vendor/livewire-powergrid/components/frameworks/bootstrap5/header/message-soft-deletes.blade.php ENDPATH**/ ?>