<?php

use Livewire\Component;

new class extends Component
{
};
?>
<div>
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <div class="d-flex gap-2">
                <i class="ri-checkbox-circle-line fs-18"></i>
                <div>
                    <strong>Berhasil</strong>
                    <div>{{ session('success') }}</div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @elseif (session()->has('failed'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <div class="d-flex gap-2">
                <i class="ri-error-warning-line fs-18"></i>
                <div>
                    <strong>Gagal</strong>
                    <div>{{ session('failed') }}</div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
</div>
