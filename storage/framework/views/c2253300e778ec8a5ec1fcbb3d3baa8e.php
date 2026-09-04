<?php
use App\Models\Blok;
use Livewire\Attributes\Layout;
use Livewire\Component;
?>

<div>
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">DPNA Blok</h4>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item active">Kelola Blok / DPNA Blok</li></ol>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="mb-1">Daftar Peserta dan Nilai Akhir Blok</h5><div class="text-muted small">Pilih blok untuk mengatur sumber, bobot, dan melihat rekap DPNA.</div></div>
        <div class="card-body"><?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('table-dpna-blok', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2101199678-0', $__key);

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
?></div>
    </div>
</div><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/8bec34dd.blade.php ENDPATH**/ ?>