<?php

use App\Models\Blok;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('dpna-blok:') || Blok::query()->dapatDikelolaOleh(auth()->user())->exists(), 403);
    }
}; ?>

<div>
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">DPNA Blok</h4>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item active">Kelola Blok / DPNA Blok</li></ol>
    </div>
    <div class="card">
        <div class="card-header"><h5 class="mb-1">Daftar Peserta dan Nilai Akhir Blok</h5><div class="text-muted small">Pilih blok untuk mengatur sumber, bobot, dan melihat rekap DPNA.</div></div>
        <div class="card-body"><livewire:table-dpna-blok /></div>
    </div>
</div>