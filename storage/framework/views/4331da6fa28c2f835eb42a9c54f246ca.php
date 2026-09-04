<?php

use App\Models\MataKuliah;
use App\Models\Prodi;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public $edit_id;
    public $prodi_id;
    public $kode;
    public $nama;
    public $sks = 1;
    public $deskripsi;
    public $status = 'aktif';
    public $prodi = [];

    public function mount($id): void
    {
        $this->prodi = Prodi::orderBy('nama')->get(['id_prodi', 'nama', 'kode']);

        if ($id && $id !== 'add') {
            try {
                $this->edit_id = Crypt::decrypt($id);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                abort(404, 'Enkripsi tidak valid !');
            }

            $mataKuliah = MataKuliah::findOrFail($this->edit_id);
            $this->prodi_id = $mataKuliah->prodi_id;
            $this->kode = $mataKuliah->kode;
            $this->nama = $mataKuliah->nama;
            $this->sks = $mataKuliah->sks;
            $this->deskripsi = $mataKuliah->deskripsi;
            $this->status = $mataKuliah->status;
        }
    }

    public function save()
    {
        $payload = $this->validate([
            'prodi_id' => ['required', 'exists:prodi,id_prodi'],
            'kode' => ['required', 'string', 'max:255', Rule::unique('mata_kuliah', 'kode')->where('prodi_id', $this->prodi_id)->ignore($this->edit_id)],
            'nama' => ['required', 'string', 'max:255'],
            'sks' => ['required', 'numeric', 'min:0.5', 'max:99.9'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'prodi_id.required' => 'Program studi wajib dipilih.',
            'kode.required' => 'Kode mata kuliah wajib diisi.',
            'kode.unique' => 'Kode mata kuliah sudah digunakan pada prodi ini.',
            'nama.required' => 'Nama mata kuliah wajib diisi.',
            'sks.required' => 'SKS wajib diisi.',
            'sks.numeric' => 'SKS harus berupa angka.',
            'status.required' => 'Status wajib dipilih.',
        ]);

        if ($this->edit_id) {
            $existing = MataKuliah::findOrFail($this->edit_id);

            if ((int) $existing->prodi_id !== (int) $payload['prodi_id']) {
                $payload['blok_id'] = null;
            }
        }

        MataKuliah::updateOrCreate(['id' => $this->edit_id], $payload);
        session()->flash('success', $this->edit_id ? 'Berhasil mengubah data' : 'Berhasil menambah data');

        return $this->redirect(route('mata-kuliah.index'), navigate: true);
    }
}; ?>

<form wire:submit="save">
    <?php echo csrf_field(); ?>
    <div class="row">
        <div class="col-sm-12 col-lg-8">
            <div class="card">
                <div class="card-header"><h4><?php echo e($edit_id ? 'Edit Mata Kuliah' : 'Tambah Mata Kuliah'); ?></h4></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Program Studi</label>
                        <select class="form-select" wire:model.live="prodi_id">
                            <option value="">Pilih prodi</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $prodi; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($item->id_prodi); ?>"><?php echo e($item->kode); ?> - <?php echo e($item->nama); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['prodi_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="form-text">Blok yang dipakai mata kuliah diatur dari form Blok.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode Mata Kuliah</label>
                            <input type="text" class="form-control" wire:model.live.debounce.500ms="kode">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKS</label>
                            <input type="number" step="0.5" class="form-control" wire:model="sks">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['sks'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Kuliah</label>
                        <input type="text" class="form-control" wire:model.live.debounce.500ms="nama">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['nama'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" wire:model="deskripsi" rows="3"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['deskripsi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" wire:model="status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="position: fixed; bottom: 50px; left: 0; width: 100%; display: flex; justify-content: center; z-index: 1050;">
        <button type="submit" class="btn btn-primary shadow d-flex align-items-center gap-2 fab-save" wire:loading.attr="disabled">
            <span wire:loading.remove><i class="ri-save-line"></i> SIMPAN</span>
            <span wire:loading>Loading...</span>
        </button>
    </div>
</form>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\mata-kuliah\add_edit.blade.php ENDPATH**/ ?>