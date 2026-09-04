<?php
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
?>




<div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <a href="<?php echo e(url('/')); ?>" class="d-inline-flex align-items-center gap-2 mb-4">
                                <span class="avatar-sm">
                                    <span class="avatar-title rounded bg-primary-subtle text-primary">
                                        <i class="ri-key-2-line fs-4"></i>
                                    </span>
                                </span>
                                <span class="fw-semibold text-body">Sistem Blok FK UIN Jambi</span>
                            </a>
                            <h4 class="text-primary mb-1">Reset Password</h4>
                            <p class="text-muted mb-0">Masukkan email akun untuk menerima tautan reset password.</p>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
                            <div class="alert alert-success border-0 alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                <i class="ri-checkbox-circle-line align-bottom me-1"></i> <?php echo e(session('status')); ?>

                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning border-0 alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                <i class="ri-mail-send-line align-bottom me-1"></i>
                                Instruksi reset akan dikirim jika email terdaftar di sistem.
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <form class="form-horizontal" wire:submit="sendPasswordResetLink">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="position-relative">
                                    <input type="email" wire:model="email"
                                        class="form-control pe-5 <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                        id="email" placeholder="nama@kampus.ac.id" autocomplete="username" autofocus>
                                    <span class="position-absolute top-50 end-0 translate-middle-y me-3 text-muted">
                                        <i class="ri-mail-line"></i>
                                    </span>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->get('email')): ?>
                                    <ul class="text-danger fs-13 mt-1 mb-0 ps-3">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = (array) $errors->get('email'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <li><?php echo e($message); ?></li>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </ul>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <button class="btn btn-primary w-100" type="submit" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="sendPasswordResetLink">
                                    <i class="ri-send-plane-line align-bottom me-1"></i> Kirim Tautan Reset
                                </span>
                                <span wire:loading wire:target="sendPasswordResetLink">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Mengirim...
                                </span>
                            </button>
                        </form>

                        <div class="mt-4 text-center">
                            <a href="<?php echo e(route('login')); ?>" wire:navigate class="text-muted">
                                <i class="ri-arrow-left-line align-bottom me-1"></i> Kembali ke login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/9f59791a.blade.php ENDPATH**/ ?>