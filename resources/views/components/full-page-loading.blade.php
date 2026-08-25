@props([
    'target' => null,
    'message' => 'Memuat halaman...',
])

<div
    wire:loading.delay.flex
    @if ($target) wire:target="{{ $target }}" @endif
    class="position-fixed top-0 start-0 w-100 h-100 align-items-center justify-content-center"
    style="display: none; z-index: 2000; background: rgba(0, 0, 0, .25); cursor: wait;"
    role="status"
    aria-live="polite"
    aria-busy="true"
>
    <div class="card border-0 shadow">
        <div class="card-body d-flex align-items-center gap-3 px-4 py-3">
            <span class="spinner-border text-primary" aria-hidden="true"></span>
            <span class="fw-semibold">{{ $message }}</span>
        </div>
    </div>
</div>