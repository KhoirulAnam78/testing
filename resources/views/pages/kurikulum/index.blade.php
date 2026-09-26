<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('kurikulum:'), 403);
    }
}; ?>

<div>
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">Kurikulum</h4>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item">Akademik</li><li class="breadcrumb-item active">Kurikulum</li></ol>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Kurikulum</h5>
            <a href="{{ route('kurikulum.add_edit', ['id' => 'add']) }}" wire:navigate class="btn btn-primary btn-sm"><i class="ri-add-box-fill"></i> Tambah</a>
        </div>
        <div class="card-body"><livewire:alert/><livewire:table-kurikulum lazy /></div>
    </div>
</div>