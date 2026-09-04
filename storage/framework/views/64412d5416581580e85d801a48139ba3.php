<?php
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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
                            <span class="avatar-sm d-inline-flex mb-3">
                                <span class="avatar-title rounded bg-primary-subtle text-primary">
                                    <i class="ri-mail-check-line fs-4"></i>
                                </span>
                            </span>
                            <h4 class="text-primary mb-1">Verifikasi Email</h4>
                            <p class="text-muted mb-0">
                                Klik tautan verifikasi yang sudah dikirim ke email akun Anda.
                            </p>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status') == 'verification-link-sent'): ?>
                            <div class="alert alert-success border-0 alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                <i class="ri-checkbox-circle-line align-bottom me-1"></i>
                                Tautan verifikasi baru sudah dikirim ke email Anda.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info border-0 alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                <i class="ri-information-line align-bottom me-1"></i>
                                Jika email belum masuk, kirim ulang tautan verifikasi dari tombol di bawah.
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" wire:click="sendVerification" wire:loading.attr="disabled"
                                wire:target="sendVerification">
                                <span wire:loading.remove wire:target="sendVerification">
                                    <i class="ri-send-plane-line align-bottom me-1"></i> Kirim Ulang Verifikasi
                                </span>
                                <span wire:loading wire:target="sendVerification">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Mengirim...
                                </span>
                            </button>

                            <button wire:click="logout" type="button" class="btn btn-light">
                                <i class="ri-logout-box-r-line align-bottom me-1"></i> Keluar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/643a1ba7.blade.php ENDPATH**/ ?>