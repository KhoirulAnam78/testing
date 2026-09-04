<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Kelola Blok</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Akademik</a></li>
                    <li class="breadcrumb-item active">Blok</li>
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
                            <div class="text-muted small">Kelola susunan mata kuliah, kegiatan, materi, dan periode setiap blok.</div>
                        </div>
                        <div class="col-md-4 text-md-end mt-2 mt-md-0">
                            <a href="{{ route('blok.add_edit', ['id' => 'add']) }}" wire:navigate class="btn btn-primary btn-sm">
                                <i class="ri-add-box-fill"></i> Tambah
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <livewire:alert/>
                    <livewire:table-blok lazy />
                </div>
            </div>
        </div>
    </div>
</div>
