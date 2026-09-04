<?php

use App\Models\Blok;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('blok-operasional:')
                || Blok::query()->dapatDikelolaOleh(auth()->user())->exists(),
            403
        );
    }
}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Operasional Blok</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Akademik</a></li>
                    <li class="breadcrumb-item active">Operasional Blok</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-1">Daftar Blok</h5>
                            <div class="text-muted small">
                                Pilih <span class="fw-semibold">Kelola</span> untuk mengatur peserta, kelompok belajar, dan dosen pengampu per pertemuan.
                                Susunan kegiatan dan materi tetap diatur dari menu Blok.
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="<?php echo e(route('blok.index')); ?>" wire:navigate class="btn btn-soft-primary btn-sm">
                                <i class="ri-layout-grid-line"></i> Kelola Susunan Blok
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('alert', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3337907605-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('table-blok-operasional', ['lazy' => true]);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3337907605-1', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\blok-operasional\index.blade.php ENDPATH**/ ?>