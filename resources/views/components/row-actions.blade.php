@props(['edit', 'delete', 'id', 'confirm'])

<button type="button" class="btn btn-info btn-sm mb-2" wire:click="{{ $edit }}('{{ \Illuminate\Support\Facades\Crypt::encrypt($id) }}')">
    <i class="ri-file-edit-line"></i> Kelola
</button>
<button type="button" class="btn btn-danger btn-sm mb-2" wire:click="{{ $delete }}('{{ \Illuminate\Support\Facades\Crypt::encrypt($id) }}')" wire:confirm="{{ $confirm }}">
    <i class="ri-delete-bin-line"></i> Hapus
</button>
