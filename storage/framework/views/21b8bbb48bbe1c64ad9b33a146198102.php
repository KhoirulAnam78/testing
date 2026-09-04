<?php

use App\Models\Kelas;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public int $blok_id;
    public $edit_id;
    public string $kode = '';
    public string $nama = '';
    public $kapasitas;
    public string $status = 'aktif';

    public function mount($blok_id): void
    {
        $this->blok_id = (int) $blok_id;
    }

    public function resetForm(): void
    {
        $this->reset(['edit_id', 'kode', 'nama', 'kapasitas']);
        $this->status = 'aktif';
        $this->resetErrorBag();
    }

    public function edit(string $id): void
    {
        $rombel = Kelas::where('blok_id', $this->blok_id)->findOrFail($id);

        $this->edit_id = $rombel->id_kelas;
        $this->kode = (string) $rombel->kode;
        $this->nama = (string) $rombel->nama;
        $this->kapasitas = $rombel->kapasitas;
        $this->status = $rombel->status;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $payload = $this->validate([
            'kode' => [
                'required',
                'string',
                'max:255',
                Rule::unique('kelas', 'kode')
                    ->where('blok_id', $this->blok_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->edit_id, 'id_kelas'),
            ],
            'nama' => ['nullable', 'string', 'max:255'],
            'kapasitas' => ['nullable', 'integer', 'min:1', 'max:999'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'kode.required' => 'Kode rombel wajib diisi.',
            'kode.unique' => 'Kode rombel sudah dipakai pada blok ini.',
            'kapasitas.integer' => 'Kapasitas harus berupa angka.',
        ]);

        if ($this->edit_id) {
            $rombel = Kelas::where('blok_id', $this->blok_id)->findOrFail($this->edit_id);
            $terpakai = $rombel->peserta_blok()->count();

            if ($payload['kapasitas'] && $terpakai > (int) $payload['kapasitas']) {
                $this->addError('kapasitas', 'Kapasitas tidak boleh lebih kecil dari '.$terpakai.' peserta yang sudah masuk rombel ini.');

                return;
            }
        }

        // Unique index (blok_id, kode) tetap ditempati baris yang dihapus lembut,
        // jadi baris lama dengan kode sama dipulihkan alih-alih dibuat ulang.
        $rombel = $this->edit_id
            ? Kelas::where('blok_id', $this->blok_id)->findOrFail($this->edit_id)
            : Kelas::withTrashed()->firstOrNew([
                'blok_id' => $this->blok_id,
                'kode' => $payload['kode'],
            ]);

        $rombel->fill([
            'blok_id' => $this->blok_id,
            'kode' => $payload['kode'],
            'nama' => $payload['nama'] ?: null,
            'kapasitas' => $payload['kapasitas'] ?: null,
            'status' => $payload['status'],
        ]);

        if ($rombel->trashed()) {
            $rombel->restore();
        }

        $rombel->save();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $this->edit_id ? 'Rombel berhasil diubah.' : 'Rombel berhasil ditambahkan.',
        ]);

        $this->resetForm();
    }

    public function delete(string $id): void
    {
        $rombel = Kelas::where('blok_id', $this->blok_id)
            ->withCount(['peserta_blok', 'kelompok_blok'])
            ->findOrFail($id);

        if ($rombel->peserta_blok_count > 0 || $rombel->kelompok_blok_count > 0) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Rombel tidak dapat dihapus karena masih dipakai '.$rombel->peserta_blok_count.' peserta dan '.$rombel->kelompok_blok_count.' kelompok.',
            ]);

            return;
        }

        $rombel->delete();

        if ((int) $this->edit_id === (int) $id) {
            $this->resetForm();
        }

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Rombel berhasil dihapus.',
        ]);
    }

    public function render()
    {
        return $this->view([
            'rombelList' => Kelas::where('blok_id', $this->blok_id)
                ->withCount(['peserta_blok', 'kelompok_blok'])
                ->orderBy('kode')
                ->get(),
        ]);
    }
};
?>

<div class="row">
    <?php if (isset($component)) { $__componentOriginal4eb374fb264ddefd5a619b521190fb97 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4eb374fb264ddefd5a619b521190fb97 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.full-page-loading','data' => ['message' => 'Memproses operasional blok...']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('full-page-loading'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['message' => 'Memproses operasional blok...']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4eb374fb264ddefd5a619b521190fb97)): ?>
<?php $attributes = $__attributesOriginal4eb374fb264ddefd5a619b521190fb97; ?>
<?php unset($__attributesOriginal4eb374fb264ddefd5a619b521190fb97); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4eb374fb264ddefd5a619b521190fb97)): ?>
<?php $component = $__componentOriginal4eb374fb264ddefd5a619b521190fb97; ?>
<?php unset($__componentOriginal4eb374fb264ddefd5a619b521190fb97); ?>
<?php endif; ?>
    <div class="col-xl-4">
        <form wire:submit="save" class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo e($edit_id ? 'Edit Rombel' : 'Tambah Rombel'); ?></h5>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit_id): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="resetForm">Batal</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    <i class="ri-information-line"></i>
                    Rombel bersifat <span class="fw-semibold">opsional</span>. Blok tetap berjalan penuh tanpa rombel.
                    Buat rombel hanya bila satu blok perlu dipecah menjadi beberapa rombongan paralel.
                </div>

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" class="form-control" placeholder="R001" wire:model.live.debounce.500ms="kode">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-7 mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" placeholder="Reguler 001" wire:model.live.debounce.500ms="nama">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['nama'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kapasitas</label>
                        <input type="number" class="form-control" wire:model="kapasitas">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kapasitas'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" wire:model="status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <i class="ri-save-line"></i> SIMPAN
                </button>
            </div>
        </form>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Daftar Rombel</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Peserta</th>
                                <th>Kelompok</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rombelList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rombel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'rombel-'.e($rombel->id_kelas).''; ?>wire:key="rombel-<?php echo e($rombel->id_kelas); ?>">
                                    <td class="fw-semibold"><?php echo e($rombel->kode); ?></td>
                                    <td><?php echo e($rombel->nama ?: '-'); ?></td>
                                    <td><?php echo e($rombel->peserta_blok_count); ?><?php echo e($rombel->kapasitas ? ' / '.$rombel->kapasitas : ''); ?></td>
                                    <td><?php echo e($rombel->kelompok_blok_count); ?></td>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rombel->status === 'aktif'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-info btn-sm" wire:click="edit('<?php echo e($rombel->id_kelas); ?>')">
                                            <i class="ri-file-edit-line"></i> Kelola
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="delete('<?php echo e($rombel->id_kelas); ?>')" wire:confirm="Hapus rombel ini?">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr>
                                    <td colspan="6" class="text-muted">
                                        Belum ada rombel. Blok ini berjalan sebagai satu rombongan.
                                    </td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\blok-operasional\rombel.blade.php ENDPATH**/ ?>