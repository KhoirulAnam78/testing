<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('semester:'), 403);
    }
}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Semester</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Akademik</a></li>
                    <li class="breadcrumb-item active">Semester</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0">Daftar Semester</h5>
                        @can('semester:tambah')
                            <a href="{{ route('semester.add_edit', ['id' => 'add']) }}" wire:navigate class="btn btn-primary btn-sm">
                                <i class="ri-add-box-fill"></i> Tambah
                            </a>
                        @endcan
                    </div>
                    <livewire:alert/>
                    <livewire:table-semester lazy />
                </div>
            </div>
        </div>
    </div>
</div>
