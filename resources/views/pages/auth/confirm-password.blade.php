<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <span class="avatar-sm d-inline-flex mb-3">
                                <span class="avatar-title rounded bg-primary-subtle text-primary">
                                    <i class="ri-shield-keyhole-line fs-4"></i>
                                </span>
                            </span>
                            <h4 class="text-primary mb-1">Konfirmasi Password</h4>
                            <p class="text-muted mb-0">Area ini memerlukan verifikasi ulang sebelum dilanjutkan.</p>
                        </div>

                        <form wire:submit="confirmPassword">
                            <div class="mb-3">
                                <label class="form-label" for="password">Password</label>
                                <input wire:model="password" id="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    type="password" name="password" required autocomplete="current-password"
                                    placeholder="Masukkan password akun">
                                @if ($errors->get('password'))
                                    <ul class="text-danger fs-13 mt-1 mb-0 ps-3">
                                        @foreach ((array) $errors->get('password') as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <button class="btn btn-primary w-100" type="submit" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="confirmPassword">
                                    <i class="ri-check-line align-bottom me-1"></i> Konfirmasi
                                </span>
                                <span wire:loading wire:target="confirmPassword">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Memeriksa...
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
