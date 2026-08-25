{{--
 Description: Dilarang menghapus atau memodifikasi watermark ini
 Author: Khoirul Anam
 Date: 2026-01-27 14:03:19
 LastEditTime: 2026-03-20 15:37:20
 LastEditors: Khoirulanam
 Copyright (c) 2026 Khoirulanam4580@gmail.com
--}}
<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink($this->only('email'));

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <a href="{{ url('/') }}" class="d-inline-flex align-items-center gap-2 mb-4">
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

                        @if (session('status'))
                            <div class="alert alert-success border-0 alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                <i class="ri-checkbox-circle-line align-bottom me-1"></i> {{ session('status') }}
                            </div>
                        @else
                            <div class="alert alert-warning border-0 alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                <i class="ri-mail-send-line align-bottom me-1"></i>
                                Instruksi reset akan dikirim jika email terdaftar di sistem.
                            </div>
                        @endif

                        <form class="form-horizontal" wire:submit="sendPasswordResetLink">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="position-relative">
                                    <input type="email" wire:model="email"
                                        class="form-control pe-5 @error('email') is-invalid @enderror"
                                        id="email" placeholder="nama@kampus.ac.id" autocomplete="username" autofocus>
                                    <span class="position-absolute top-50 end-0 translate-middle-y me-3 text-muted">
                                        <i class="ri-mail-line"></i>
                                    </span>
                                </div>
                                @if ($errors->get('email'))
                                    <ul class="text-danger fs-13 mt-1 mb-0 ps-3">
                                        @foreach ((array) $errors->get('email') as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                @endif
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
                            <a href="{{ route('login') }}" wire:navigate class="text-muted">
                                <i class="ri-arrow-left-line align-bottom me-1"></i> Kembali ke login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
