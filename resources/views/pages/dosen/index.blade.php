<?php

use App\Exports\ArrayTemplateExport;
use App\Imports\DosenImport;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public $importFile;

    public function import()
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'importFile.required' => 'File import wajib dipilih.',
            'importFile.mimes' => 'File import harus berformat xlsx, xls, atau csv.',
        ]);

        Excel::import(new DosenImport(), $this->importFile);

        $this->reset('importFile');
        session()->flash('success', 'Berhasil import data dosen');

        return $this->redirect(route('dosen.index'), navigate: true);
    }

    public function template()
    {
        return Excel::download(new ArrayTemplateExport([
            ['nidn', 'nip', 'nama', 'email', 'no_hp', 'gelar_depan', 'gelar_belakang', 'bidang_keahlian', 'kode_prodi', 'status'],
            ['1234567890', '198001012006041001', 'Nama Dosen Contoh', 'dosen@example.com', '081234567890', 'dr.', 'M.Kes.', 'Ilmu Kedokteran Dasar', 'PSPD', 'aktif'],
        ]), 'template-import-dosen.xlsx');
    }
}; ?>

<div>
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Kelola Dosen</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Akademik</a></li>
                    <li class="breadcrumb-item active">Dosen</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-6"><h5>Daftar Dosen</h5></div>
                        <div class="col-6 text-end d-flex justify-content-end gap-2">
                            <button type="button" wire:click="template" wire:loading.attr="disabled" wire:target="template,import" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-file-excel-2-line"></i> Template
                            </button>
                            <a href="{{ route('dosen.add_edit', ['id' => 'add']) }}" wire:navigate wire:loading.class="pe-none disabled" wire:target="template,import" class="btn btn-primary btn-sm">
                                <i class="ri-add-box-fill"></i> Tambah
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <livewire:alert/>
                    <form wire:submit="import" class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 mb-3">
                        <label class="form-label mb-0 text-muted flex-shrink-0" style="min-width: 110px;">Import Dosen</label>
                        <div class="flex-grow-1">
                            <input type="file" class="form-control form-control-sm" wire:model="importFile" wire:loading.attr="disabled" wire:target="import" accept=".xlsx,.xls,.csv">
                            @error('importFile') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                            @error('import_dosen') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="flex-shrink-0">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100" wire:loading.attr="disabled" wire:target="template,import">
                                <span wire:loading.remove wire:target="import"><i class="ri-upload-2-line"></i> Import</span>
                                <span wire:loading wire:target="import">Memproses...</span>
                            </button>
                        </div>
                    </form>
                    <livewire:table-dosen lazy />
                </div>
            </div>
        </div>
    </div>
</div>
