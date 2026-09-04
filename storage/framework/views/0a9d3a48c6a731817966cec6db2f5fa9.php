<?php
use App\Models\Menu;
use App\Models\Blok;
use Illuminate\Support\Str;
use Livewire\Component;
?>

<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="<?php echo e(route('dashboard')); ?>" wire:navigate class="logo logo-dark">
            <span class="logo-sm">
                <span class="siakad-brand-mark mx-auto">
                    <img src="<?php echo e(asset('assets/images/favicon/favicon-uinjambi.svg')); ?>" alt="Logo UIN Jambi">
                </span>
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2">
                    <span class="siakad-brand-mark">
                        <img src="<?php echo e(asset('assets/images/favicon/favicon-uinjambi.svg')); ?>" alt="Logo UIN Jambi">
                    </span>
                    <span class="siakad-brand-text fw-semibold text-body">
                        Sistem Blok
                        <span class="d-block siakad-brand-subtitle">FK UIN Jambi</span>
                    </span>
                </span>
            </span>
        </a>

        <a href="<?php echo e(route('dashboard')); ?>" wire:navigate class="logo logo-light">
            <span class="logo-sm">
                <span class="siakad-brand-mark mx-auto">
                    <img src="<?php echo e(asset('assets/images/favicon/favicon-uinjambi.svg')); ?>" alt="Logo UIN Jambi">
                </span>
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2">
                    <span class="siakad-brand-mark">
                        <img src="<?php echo e(asset('assets/images/favicon/favicon-uinjambi.svg')); ?>" alt="Logo UIN Jambi">
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
                    <a href="<?php echo e(route('dashboard')); ?>" wire:navigate
                        class="nav-link <?php echo e(Route::is('dashboard') ? 'active' : ''); ?>">
                        <i class="ri-dashboard-2-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($menu): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->can($m->nama_permission) || ($m->name === 'Kelola Blok' && $dapatKelolaBlok)): ?>
                            <?php
                                $routeName = request()->route()?->getName();
                                $childRoutes = $m->childs_main_permission->pluck('route')->filter();
                                $childRoutePatterns = $childRoutes
                                    ->map(fn ($route) => Str::before($route, '.') . '.*')
                                    ->unique();
                                $isActiveGroup = $childRoutes
                                    ->merge($childRoutePatterns)
                                    ->contains(fn ($route) => Route::is($route));
                                $collapseId = 'sidebar-' . Str::slug($m->nama_permission);
                            ?>

                            <li class="nav-item">
                                <a class="nav-link menu-link <?php echo e($isActiveGroup ? 'active' : 'collapsed'); ?>"
                                    href="#<?php echo e($collapseId); ?>" data-bs-toggle="collapse" role="button"
                                    aria-expanded="<?php echo e($isActiveGroup ? 'true' : 'false'); ?>"
                                    aria-controls="<?php echo e($collapseId); ?>">
                                    <?php echo $m->icon; ?>

                                    <span><?php echo e($m->name); ?></span>
                                </a>

                                <div class="collapse menu-dropdown <?php echo e($isActiveGroup ? 'show' : ''); ?>"
                                    id="<?php echo e($collapseId); ?>">
                                    <ul class="nav nav-sm flex-column">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $m->childs_main_permission; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->can($i->main_permission->name) || (in_array($i->route, ['blok-operasional.index', 'dpna-blok.index'], true) && $dapatKelolaBlok)): ?>
                                                <?php
                                                    $childRoutePattern = Str::before($i->route, '.') . '.*';
                                                    $isActiveChild = Route::is($i->route) || Route::is($childRoutePattern);
                                                ?>
                                                <li class="nav-item">
                                                    <a href="<?php echo e(route($i->route)); ?>" wire:navigate
                                                        class="nav-link <?php echo e($isActiveChild ? 'active' : ''); ?>">
                                                        <?php echo e($i->name); ?>

                                                    </a>
                                                </li>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </ul>
                                </div>
                            </li>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="sidebar-background"></div>
</div><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/3e112fcf.blade.php ENDPATH**/ ?>