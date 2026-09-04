<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($setUp, 'header.showMessageSoftDeletes') &&
        ($softDeletes === 'withTrashed' || $softDeletes === 'onlyTrashed')): ?>
    <div class="bg-warning alert mb-3">
        <div class="flex">
            <div class="ml-3">
                <p class="text-sm text-warning-content">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($softDeletes === 'withTrashed'): ?>
                        <?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.message_with_trashed'); ?>
                    <?php else: ?>
                        <?php echo app('translator')->get('livewire-powergrid::datatable.soft_deletes.message_only_trashed'); ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
            </div>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\laragon\www\sistem-blok\vendor\power-components\livewire-powergrid\resources\views\components\frameworks\daisyui\header\message-soft-deletes.blade.php ENDPATH**/ ?>