<?php

use App\Models\Semester;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public $edit_id;
    public $nama = 'ganjil';
    public $tahun;
    public $kode;
    public $tanggal_mulai;
    public $tanggal_selesai;
    public $is_aktif = false;

    public function mount($id): void
    {
        if ($id && $id !== 'add') {
            try {
                $this->edit_id = Crypt::decrypt($id);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                abort(404, 'Enkripsi tidak valid !');
            }

            $semester = Semester::findOrFail($this->edit_id);
            $this->nama = $semester->nama;
            $this->tahun = $semester->tahun;
            $this->kode = $semester->kode;
            $this->tanggal_mulai = $semester->tanggal_mulai?->format('Y-m-d');
            $this->tanggal_selesai = $semester->tanggal_selesai?->format('Y-m-d');
            $this->is_aktif = (bool) $semester->is_aktif;
        }
    }

    public function updatedNama(): void
    {
        $this->syncKode();
    }

    public function updatedTahun(): void
    {
        $this->syncKode();
    }

    private function syncKode(): void
    {
        if (! $this->tahun || ! $this->nama) {
            return;
        }

        $suffix = ['ganjil' => '1', 'genap' => '2', 'pendek' => '3'][$this->nama] ?? null;
        $this->kode = $suffix ? $this->tahun.$suffix : $this->kode;
    }

    private function normalizeDateInput(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    public function save()
    {
        $this->tanggal_mulai = $this->normalizeDateInput($this->tanggal_mulai);
        $this->tanggal_selesai = $this->normalizeDateInput($this->tanggal_selesai);

        $payload = $this->validate([
            'nama' => ['required', Rule::in(['ganjil', 'genap', 'pendek'])],
            'tahun' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'kode' => ['required', 'string', 'max:255', Rule::unique('semester', 'kode')->ignore($this->edit_id, 'id_semester')],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'is_aktif' => ['boolean'],
        ], [
            'nama.required' => 'Nama semester wajib dipilih.',
            'nama.in' => 'Nama semester tidak valid.',
            'tahun.required' => 'Tahun wajib diisi.',
            'tahun.digits' => 'Tahun harus terdiri dari 4 digit.',
            'kode.required' => 'Kode semester wajib diisi.',
            'kode.unique' => 'Kode semester sudah digunakan.',
            'tanggal_mulai.date_format' => 'Format tanggal mulai tidak valid.',
            'tanggal_selesai.date_format' => 'Format tanggal selesai tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        DB::transaction(function () use ($payload) {
            if ($payload['is_aktif']) {
                Semester::query()
                    ->when($this->edit_id, fn ($query) => $query->where('id_semester', '!=', $this->edit_id))
                    ->update(['is_aktif' => false]);
            }

            Semester::updateOrCreate(
                ['id_semester' => $this->edit_id],
                $payload
            );
        });

        session()->flash('success', $this->edit_id ? 'Berhasil mengubah data' : 'Berhasil menambah data');

        return $this->redirect(route('semester.index'), navigate: true);
    }
}; ?>

<form wire:submit="save">
    <?php echo csrf_field(); ?>
    <div class="row">
        <div class="col-sm-12 col-lg-8">
            <div class="card">
                <div class="card-header"><h4><?php echo e($edit_id ? 'Edit Semester' : 'Tambah Semester'); ?></h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Semester</label>
                            <select class="form-select" wire:model.live="nama">
                                <option value="ganjil">Ganjil</option>
                                <option value="genap">Genap</option>
                                <option value="pendek">Pendek</option>
                            </select>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['nama'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tahun</label>
                            <input type="number" class="form-control" wire:model.live.debounce.500ms="tahun" placeholder="Contoh: 2026">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tahun'];
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
                        <label class="form-label">Kode Semester</label>
                        <input type="text" class="form-control" wire:model.live.debounce.500ms="kode" placeholder="Contoh: 20261">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" wire:model="tanggal_mulai">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tanggal_mulai'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Selesai</label>
                            <input type="date" class="form-control" wire:model="tanggal_selesai">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tanggal_selesai'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_aktif" wire:model="is_aktif">
                        <label class="form-check-label" for="is_aktif">Semester Aktif</label>
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
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\semester\add_edit.blade.php ENDPATH**/ ?>