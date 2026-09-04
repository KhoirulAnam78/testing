<?php

use App\Models\Menu;
use App\Models\Blok;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    public $menu;
    public bool $dapatKelolaBlok = false;

    public function mount(): void
    {
        $this->menu = Menu::with('childs_main_permission')
            ->join('permissions as b', 'b.menu_id', 'menus.id')
            ->where('b.main_permission', 1)
            ->whereNull('menus.parent_id')
            ->orderBy('position')
            ->select('menus.*', 'b.name as nama_permission')
            ->get();
        $this->dapatKelolaBlok = Blok::query()->dapatDikelolaOleh(auth()->user())->exists();
    }
}; ?>

<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="{{ route('dashboard') }}" wire:navigate class="logo logo-dark">
            <span class="logo-sm">
                <span class="siakad-brand-mark mx-auto">
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
                <span class="siakad-brand-mark mx-auto">
                    <img src="{{ asset('assets/images/favicon/favicon-uinjambi.svg') }}" alt="Logo UIN Jambi">
                </span>
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2">
                    <span class="siakad-brand-mark">
                        <img src="{{ asset('assets/images/favicon/favicon-uinjambi.svg') }}" alt="Logo UIN Jambi">
                    </span>
                    <span class="siakad-brand-text fw-semibold text-white">
                        Sistem Blok
                        <span class="d-block siakad-brand-subtitle">FK UIN Jambi</span>
                    </span>
                </span>
            </span>
        </a>

        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover" aria-label="Perkecil menu">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">
            <div id="two-column-menu"></div>

            <ul class="navbar-nav" id="navbar-nav">
                <li class="menu-title">
                    <span>Menu Utama</span>
                </li>

                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" wire:navigate
                        class="nav-link {{ Route::is('dashboard') ? 'active' : '' }}">
                        <i class="ri-dashboard-2-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                @if ($menu)
                    @foreach ($menu as $m)
                        @if (auth()->user()->can($m->nama_permission) || ($m->name === 'Kelola Blok' && $dapatKelolaBlok))
                            @php
                                $routeName = request()->route()?->getName();
                                $childRoutes = $m->childs_main_permission->pluck('route')->filter();
                                $childRoutePatterns = $childRoutes
                                    ->map(fn ($route) => Str::before($route, '.') . '.*')
                                    ->unique();
                                $isActiveGroup = $childRoutes
                                    ->merge($childRoutePatterns)
                                    ->contains(fn ($route) => Route::is($route));
                                $collapseId = 'sidebar-' . Str::slug($m->nama_permission);
                            @endphp

                            <li class="nav-item">
                                <a class="nav-link menu-link {{ $isActiveGroup ? 'active' : 'collapsed' }}"
                                    href="#{{ $collapseId }}" data-bs-toggle="collapse" role="button"
                                    aria-expanded="{{ $isActiveGroup ? 'true' : 'false' }}"
                                    aria-controls="{{ $collapseId }}">
                                    {!! $m->icon !!}
                                    <span>{{ $m->name }}</span>
                                </a>

                                <div class="collapse menu-dropdown {{ $isActiveGroup ? 'show' : '' }}"
                                    id="{{ $collapseId }}">
                                    <ul class="nav nav-sm flex-column">
                                        @foreach ($m->childs_main_permission as $i)
                                            @if (auth()->user()->can($i->main_permission->name) || (in_array($i->route, ['blok-operasional.index', 'dpna-blok.index'], true) && $dapatKelolaBlok))
                                                @php
                                                    $childRoutePattern = Str::before($i->route, '.') . '.*';
                                                    $isActiveChild = Route::is($i->route) || Route::is($childRoutePattern);
                                                @endphp
                                                <li class="nav-item">
                                                    <a href="{{ route($i->route) }}" wire:navigate
                                                        class="nav-link {{ $isActiveChild ? 'active' : '' }}">
                                                        {{ $i->name }}
                                                    </a>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            </li>
                        @endif
                    @endforeach
                @endif
            </ul>
        </div>
    </div>

    <div class="sidebar-background"></div>
</div>
