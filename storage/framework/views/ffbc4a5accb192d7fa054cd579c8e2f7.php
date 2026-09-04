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
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-import-dosen">
                                <i class="ri-upload-2-line"></i> Import
                            </button>
                            <a href="<?php echo e(route('dosen.add_edit', ['id' => 'add'])); ?>" wire:navigate wire:loading.class="pe-none disabled" wire:target="template,import" class="btn btn-primary btn-sm">
                                <i class="ri-add-box-fill"></i> Tambah
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('alert', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2932636154-0', $__key);

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
?>
                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('table-dosen', ['lazy' => true]);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2932636154-1', $__key);

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
?>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="modal-import-dosen" tabindex="-1" aria-labelledby="modal-import-dosen-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form wire:submit="import" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-import-dosen-label">Template Import</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <button type="button" wire:click="template" wire:loading.attr="disabled" wire:target="template,import" class="btn btn-outline-secondary btn-sm mb-3 d-block">
                        <i class="ri-file-excel-2-line"></i> Template Import
                    </button>
                    <label for="import-file-dosen" class="form-label">File Import Dosen</label>
                    <input id="import-file-dosen" type="file" class="form-control" wire:model="importFile" wire:loading.attr="disabled" wire:target="import" accept=".xlsx,.xls,.csv">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['importFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['import_dosen'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="template,import">
                        <span wire:loading.remove wire:target="import"><i class="ri-upload-2-line"></i> Import</span>
                        <span wire:loading wire:target="import">Memproses...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\dosen\index.blade.php ENDPATH**/ ?>