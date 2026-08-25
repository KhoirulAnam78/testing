<?php
use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: false);
    }
}; ?>

<div>
    <div class="sheet-wrap">
        <div class="sheet">
            <div class="sheet__band">
                <span><b>Masuk</b></span>
                <span>Akses internal</span>
            </div>

            <div class="sheet__body">
                <h1 class="sheet__title">Masuk ke akun Anda</h1>
                <p class="sheet__note">Gunakan akun yang diberikan pengelola akademik Fakultas Kedokteran.</p>

                <form class="sheet__form" wire:submit="login">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" wire:model="form.username" id="username" name="username"
                            class="form-control @error('form.username') is-invalid @enderror"
                            placeholder="Masukkan username" autocomplete="username" autofocus
                            @error('form.username') aria-invalid="true" aria-describedby="username-error" @enderror>

                        @if ($errors->get('form.username'))
                            <ul class="field-error" id="username-error" role="alert">
                                @foreach ((array) $errors->get('form.username') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="mb-4">
                        <div class="label-row">
                            <label class="form-label" for="password">Password</label>
                            @if (Route::has('password.request'))
                                <a class="link-quiet" href="{{ route('password.request') }}" wire:navigate>
                                    Lupa password?
                                </a>
                            @endif
                        </div>

                        <div class="pw">
                            <input type="password" wire:model="form.password" id="password" name="password"
                                class="form-control @error('form.password') is-invalid @enderror"
                                placeholder="Masukkan password" autocomplete="current-password"
                                @error('form.password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <button class="pw__toggle" type="button" data-toggle-password aria-pressed="false"
                                aria-label="Tampilkan password">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>

                        @if ($errors->get('form.password'))
                            <ul class="field-error" id="password-error" role="alert">
                                @foreach ((array) $errors->get('form.password') as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <button class="btn btn-primary btn-lg w-100" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="login">
                            <i class="ri-login-box-line align-bottom me-1"></i> Masuk
                        </span>
                        <span wire:loading wire:target="login">
                            <span class="spinner-border spinner-border-sm me-1" role="status"
                                aria-hidden="true"></span>
                            Memproses...
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <p class="sheet__foot">
            Pendaftaran akun tidak dibuka. Hubungi pengelola akademik untuk mendapatkan akses.
        </p>
    </div>
</div>
