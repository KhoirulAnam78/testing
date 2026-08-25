{{--
 Description: Dilarang menghapus atau memodifikasi watermark ini
 Author: Khoirul Anam
 Date: 2026-01-27 14:03:19
 LastEditTime: 2026-03-20 15:27:20
 LastEditors: Khoirulanam
 Copyright (c) 2026 Khoirulanam4580@gmail.com
--}}
<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: false);
    }
}; ?>

<div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-xl-6">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <a href="{{ url('/') }}" class="d-inline-flex align-items-center gap-2 mb-4">
                                <span class="avatar-sm">
                                    <span class="avatar-title rounded bg-primary-subtle text-primary">
                                        <i class="ri-user-add-line fs-4"></i>
                                    </span>
                                </span>
                                <span class="fw-semibold text-body">Sistem Blok FK UIN Jambi</span>
                            </a>
                            <h4 class="text-primary mb-1">Registrasi Akun</h4>
                            <p class="text-muted mb-0">Form ini hanya digunakan jika registrasi mandiri diaktifkan.</p>
                        </div>

                        <div class="alert alert-info border-0 alert-dismissible fade show" role="alert">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                            <i class="ri-information-line align-bottom me-1"></i>
                            Pada operasional akademik, akun biasanya dibuat oleh administrator sistem.
                        </div>

                        <form class="form-horizontal" wire:submit="register">
                            <div class="mb-3">
                                <label class="form-label" for="name">Nama Lengkap</label>
                                <input type="text" required wire:model="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" placeholder="Nama pengguna" autocomplete="name">
                                @if ($errors->get('name'))
                                    <ul class="text-danger fs-13 mt-1 mb-0 ps-3">
                                        @foreach ((array) $errors->get('name') as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" required wire:model="username"
                                    class="form-control @error('username') is-invalid @enderror"
                                    id="username" name="username" placeholder="Username" autocomplete="username">
                                @error('username') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" required wire:model="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    id="email" name="email" placeholder="nama@kampus.ac.id" autocomplete="username">
                                @if ($errors->get('email'))
                                    <ul class="text-danger fs-13 mt-1 mb-0 ps-3">
                                        @foreach ((array) $errors->get('email') as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="password">Password</label>
                                <input type="password" required wire:model="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    id="password" name="password" placeholder="Masukkan password"
                                    autocomplete="new-password">
                                @if ($errors->get('password'))
                                    <ul class="text-danger fs-13 mt-1 mb-0 ps-3">
                                        @foreach ((array) $errors->get('password') as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="password_confirmation">Konfirmasi Password</label>
                                <input type="password" required wire:model="password_confirmation"
                                    class="form-control @error('password_confirmation') is-invalid @enderror"
                                    id="password_confirmation" name="password_confirmation"
                                    placeholder="Ulangi password" autocomplete="new-password">
                                @if ($errors->get('password_confirmation'))
                                    <ul class="text-danger fs-13 mt-1 mb-0 ps-3">
                                        @foreach ((array) $errors->get('password_confirmation') as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <button class="btn btn-primary w-100" type="submit" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="register">
                                    <i class="ri-user-add-line align-bottom me-1"></i> Daftar
                                </span>
                                <span wire:loading wire:target="register">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Memproses...
                                </span>
                            </button>

                            <div class="mt-4 text-center">
                                <a href="{{ route('login') }}" wire:navigate class="text-muted">
                                    <i class="ri-arrow-left-line align-bottom me-1"></i> Sudah punya akun? Masuk
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
