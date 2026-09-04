<?php

use Livewire\Component;

new class extends Component
{
};
?>
<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <div class="d-flex gap-2">
                <i class="ri-checkbox-circle-line fs-18"></i>
                <div>
                    <strong>Berhasil</strong>
                    <div><?php echo e(session('success')); ?></div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php elseif(session()->has('failed')): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <div class="d-flex gap-2">
                <i class="ri-error-warning-line fs-18"></i>
                <div>
                    <strong>Gagal</strong>
                    <div><?php echo e(session('failed')); ?></div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\alert.blade.php ENDPATH**/ ?>