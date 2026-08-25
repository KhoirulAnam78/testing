<?php

use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: false);
    }

    public function stopLoginAs(): void
    {
        $originalUserId = session()->pull('login_as_original_user_id');
        abort_unless($originalUserId, 403);

        $originalUser = User::find($originalUserId);

        if (! $originalUser) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            $this->redirect('/', navigate: false);

            return;
        }

        Auth::login($originalUser);
        request()->session()->regenerate();

        $this->redirectRoute('users.index', navigate: false);
    }
}; ?>

<header id="page-topbar">
    @if (session()->has('login_as_original_user_id'))
        <div class="alert alert-warning rounded-0 border-0 mb-0 py-2 text-center alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-user-shared-line me-1"></i>
            Login As <strong>{{ auth()->user()->name }}</strong>
            <button type="button" class="btn btn-dark btn-sm ms-2" wire:click="stopLoginAs">
                Kembali ke akun asli
            </button>
        </div>
    @endif

    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex align-items-center">
                <div class="navbar-brand-box horizontal-logo">
                    <a href="{{ route('dashboard') }}" wire:navigate class="logo logo-dark">
                        <span class="logo-sm">
                            <span class="siakad-brand-mark">
                                <img src="{{ asset('assets/images/favicon/favicon-uinjambi.svg') }}" alt="Logo UIN Jambi">
                            </span>
                        </span>
                        <span class="logo-lg">
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="siakad-brand-mark">
                                    <img src="{{ asset('assets/images/favicon/favicon-uinjambi.svg') }}" alt="Logo UIN Jambi">
                                </span>
                                <span class="siakad-brand-text fw-semibold text-body">
                                    Sistem Blok
                                    <span class="d-block siakad-brand-subtitle">FK UIN Jambi</span>
                                </span>
                            </span>
                        </span>
                    </a>

                    <a href="{{ route('dashboard') }}" wire:navigate class="logo logo-light">
                        <span class="logo-sm">
                            <span class="siakad-brand-mark">
                                <img src="{{ asset('assets/images/favicon/favicon-uinjambi.svg') }}" alt="Logo UIN Jambi">
                            </span>
                        </span>
                        <span class="logo-lg">
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="siakad-brand-mark">
                                    <img src="{{ asset('assets/images/favicon/favicon-uinjambi.svg') }}" alt="Logo UIN Jambi">
                                </span>
                                <span class="siakad-brand-text fw-semibold text-body">
                                    Sistem Blok
                                    <span class="d-block siakad-brand-subtitle">FK UIN Jambi</span>
                                </span>
                            </span>
                        </span>
                    </a>
                </div>

                <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger"
                    id="topnav-hamburger-icon" aria-label="Buka tutup menu">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>

                <div class="d-none d-md-block ms-2">
                    <p class="topbar-org mb-0">
                        <b>Universitas Islam Negeri Sulthan Thaha Saifuddin Jambi</b>
                        Fakultas Kedokteran &middot; Pendidikan Dokter
                    </p>
                </div>
            </div>

            <div class="d-flex align-items-center">
                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn" id="page-header-user-dropdown" data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <span class="avatar-sm">
                                <span class="avatar-title rounded-circle bg-primary-subtle text-primary">
                                    <i class="ri-user-3-line fs-18"></i>
                                </span>
                            </span>
                            <span class="text-start ms-xl-2">
                                <span class="d-none d-xl-inline-block ms-1 fw-semibold user-name-text">
                                    {{ auth()->user()->name }}
                                </span>
                                <span class="d-none d-xl-block ms-1 fs-12 user-name-sub-text">
                                    {{ auth()->user()->email }}
                                </span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Halo, {{ auth()->user()->name }}</h6>
                        <a class="dropdown-item" href="{{ route('dashboard') }}" wire:navigate>
                            <i class="ri-dashboard-line text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">Dashboard</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        @if (session()->has('login_as_original_user_id'))
                            <button class="dropdown-item" wire:click="stopLoginAs">
                                <i class="ri-arrow-go-back-line text-muted fs-16 align-middle me-1"></i>
                                <span class="align-middle">Kembali ke akun asli</span>
                            </button>
                        @endif
                        <button class="dropdown-item" wire:click="logout">
                            <i class="ri-logout-box-r-line text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">Keluar</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
