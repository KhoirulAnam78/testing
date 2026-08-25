@props(['save', 'reset'])

<div class="d-flex gap-2">
    <button type="button" class="btn btn-primary btn-sm" wire:click="{{ $save }}" wire:loading.attr="disabled">
        <i class="ri-save-line"></i> SIMPAN
    </button>
    <button type="button" class="btn btn-light btn-sm" wire:click="{{ $reset }}">
        Reset
    </button>
</div>
