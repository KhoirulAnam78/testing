<?php

use App\Models\LampiranMateriBlok;
use App\Models\MateriRinciBlok;
use App\Models\PertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Daftar dan form tautan modul / video untuk satu materi.
 *
 * Dua mode, ditentukan oleh `pertemuan_blok_id`:
 * - null  : mengelola lampiran default materi, berlaku untuk semua kelompok (pengelola).
 * - terisi: mengelola lampiran satu pertemuan. Lampiran default tetap ditampilkan
 *           tetapi hanya bisa dibaca, supaya dosen satu kelompok tidak menimpa
 *           materi milik kelompok lain.
 *
 * Halaman mahasiswa tidak memakai komponen ini: daftarnya dirender langsung di Blade
 * karena read-only, sehingga tidak perlu satu komponen Livewire per pertemuan.
 */
new class extends Component
{
    public int $materi_rinci_blok_id;
    public ?int $pertemuan_blok_id = null;

    public string $form_jenis = 'modul';
    public string $form_judul = '';
    public string $form_url = '';
    public string $form_deskripsi = '';
    public ?int $edit_id = null;

    public function mount(int $materi_rinci_blok_id, ?int $pertemuan_blok_id = null): void
    {
        $this->materi_rinci_blok_id = $materi_rinci_blok_id;
        $this->pertemuan_blok_id = $pertemuan_blok_id;

        abort_unless($this->bolehLihat(), 403);

        // Pertemuan yang tidak sesuai materinya menandakan parameter yang tidak konsisten.
        $this->pertemuan();
    }

    public function bolehLihat(): bool
    {
        $user = auth()->user();

        return $this->pertemuan_blok_id
            ? AksesPertemuanBlok::bolehLihatPertemuan($user, $this->pertemuan_blok_id)
            : AksesPertemuanBlok::bolehKelolaLampiranDefault($user, $this->materi_rinci_blok_id);
    }

    public function bolehKelola(): bool
    {
        $user = auth()->user();

        return $this->pertemuan_blok_id
            ? AksesPertemuanBlok::bolehKelolaPertemuan($user, $this->pertemuan_blok_id)
            : AksesPertemuanBlok::bolehKelolaLampiranDefault($user, $this->materi_rinci_blok_id);
    }

    /**
     * Lampiran default materi ini, ditambah lampiran milik pertemuan bila mode pertemuan.
     * Yang nonaktif ikut tampil di sini supaya pengelola dan dosen bisa mengaktifkannya
     * kembali; halaman mahasiswa yang menyaring hanya yang aktif.
     */
    public function daftar()
    {
        $query = $this->pertemuan_blok_id
            ? LampiranMateriBlok::query()->untukPertemuan($this->materi_rinci_blok_id, $this->pertemuan_blok_id)
            : LampiranMateriBlok::query()->defaultMateri($this->materi_rinci_blok_id);

        return $query->get();
    }

    public function simpan(): void
    {
        abort_unless($this->bolehKelola(), 403);

        $data = $this->validate([
            'form_jenis' => ['required', 'in:modul,video'],
            'form_judul' => ['required', 'string', 'max:255'],
            'form_url' => ['required', 'url', 'max:1000', 'starts_with:http://,https://'],
            'form_deskripsi' => ['nullable', 'string', 'max:1000'],
        ], [
            'form_jenis.required' => 'Jenis lampiran wajib dipilih.',
            'form_jenis.in' => 'Jenis lampiran harus modul atau video.',
            'form_judul.required' => 'Judul wajib diisi.',
            'form_judul.max' => 'Judul maksimal 255 karakter.',
            'form_url.required' => 'Tautan wajib diisi.',
            'form_url.url' => 'Tautan tidak valid.',
            'form_url.max' => 'Tautan maksimal 1000 karakter.',
            'form_url.starts_with' => 'Tautan harus dimulai dengan http:// atau https://.',
            'form_deskripsi.max' => 'Keterangan maksimal 1000 karakter.',
        ]);

        $lampiran = $this->edit_id
            ? $this->milikSendiri()->findOrFail($this->edit_id)
            : new LampiranMateriBlok();

        $lampiran->fill([
            'blok_id' => $this->blokId(),
            'materi_rinci_blok_id' => $this->materi_rinci_blok_id,
            'pertemuan_blok_id' => $this->pertemuan_blok_id,
            'jenis' => $data['form_jenis'],
            'judul' => trim($data['form_judul']),
            'url' => trim($data['form_url']),
            'deskripsi' => trim($data['form_deskripsi']) !== '' ? trim($data['form_deskripsi']) : null,
        ]);

        if (! $lampiran->exists) {
            $lampiran->urutan = $this->urutanBerikutnya($data['form_jenis']);
            $lampiran->status = 'aktif';
            $lampiran->dibuat_oleh_user_id = auth()->id();
        }

        $lampiran->save();

        $pesan = $this->edit_id ? 'Tautan berhasil diperbarui.' : 'Tautan berhasil ditambahkan.';

        $this->resetForm();
        $this->dispatch('lampiran-materi-tersimpan');
        $this->dispatch('notify', message: ['status' => 'success', 'message' => $pesan]);
    }

    public function edit(string $id): void
    {
        abort_unless($this->bolehKelola(), 403);

        $lampiran = $this->milikSendiri()->findOrFail((int) $id);

        $this->edit_id = $lampiran->id_lampiran_materi_blok;
        $this->form_jenis = $lampiran->jenis;
        $this->form_judul = (string) $lampiran->judul;
        $this->form_url = (string) $lampiran->url;
        $this->form_deskripsi = (string) $lampiran->deskripsi;
        $this->resetErrorBag();
    }

    public function hapus(string $id): void
    {
        abort_unless($this->bolehKelola(), 403);

        $this->milikSendiri()->findOrFail((int) $id)->delete();

        $this->resetForm();
        $this->dispatch('lampiran-materi-tersimpan');
        $this->dispatch('notify', message: ['status' => 'success', 'message' => 'Tautan berhasil dihapus.']);
    }

    /**
     * Menonaktifkan lebih aman daripada menghapus: tautan hilang dari halaman mahasiswa
     * tetapi riwayatnya tetap ada untuk pengelola dan dosen.
     */
    public function toggleStatus(string $id): void
    {
        abort_unless($this->bolehKelola(), 403);

        $lampiran = $this->milikSendiri()->findOrFail((int) $id);
        $lampiran->status = $lampiran->status === 'aktif' ? 'nonaktif' : 'aktif';
        $lampiran->save();

        $this->dispatch('lampiran-materi-tersimpan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $lampiran->status === 'aktif' ? 'Tautan diaktifkan.' : 'Tautan dinonaktifkan.',
        ]);
    }

    public function resetForm(): void
    {
        $this->reset(['form_jenis', 'form_judul', 'form_url', 'form_deskripsi', 'edit_id']);
        $this->resetErrorBag();
    }

    #[On('lampiran-materi-tersimpan')]
    public function refreshDaftar(): void
    {
        // Cukup memicu render ulang agar komponen lain pada materi yang sama ikut segar.
    }

    /**
     * Hanya baris yang benar-benar milik cakupan komponen ini. Lampiran default tidak
     * bisa disentuh dari mode pertemuan, dan sebaliknya.
     */
    private function milikSendiri()
    {
        return LampiranMateriBlok::query()
            ->where('materi_rinci_blok_id', $this->materi_rinci_blok_id)
            ->when(
                $this->pertemuan_blok_id,
                fn ($query) => $query->where('pertemuan_blok_id', $this->pertemuan_blok_id),
                fn ($query) => $query->whereNull('pertemuan_blok_id')
            );
    }

    private function pertemuan(): ?PertemuanBlok
    {
        if (! $this->pertemuan_blok_id) {
            return null;
        }

        return PertemuanBlok::query()
            ->where('materi_rinci_blok_id', $this->materi_rinci_blok_id)
            ->findOrFail($this->pertemuan_blok_id);
    }

    private function blokId(): int
    {
        $pertemuan = $this->pertemuan();

        if ($pertemuan) {
            return (int) $pertemuan->blok_id;
        }

        $materi = MateriRinciBlok::with('materi_blok.aturan_kegiatan_blok:id,blok_id')
            ->findOrFail($this->materi_rinci_blok_id);

        $blokId = $materi->materi_blok?->aturan_kegiatan_blok?->blok_id;

        abort_unless($blokId, 404, 'Materi ini tidak terhubung ke blok manapun.');

        return (int) $blokId;
    }

    private function urutanBerikutnya(string $jenis): int
    {
        return (int) $this->milikSendiri()->where('jenis', $jenis)->max('urutan') + 1;
    }

    public function render()
    {
        return $this->view([
            'daftar' => $this->daftar(),
            'bolehKelola' => $this->bolehKelola(),
        ]);
    }
};
?>

<div>
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
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($daftar->isEmpty()): ?>
        <div class="text-muted small mb-2">
            <i class="ri-information-line"></i>
            Belum ada tautan modul atau video.
        </div>
    <?php else: ?>
        <div class="list-group list-group-flush mb-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $daftar; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php ($diwarisi = $pertemuan_blok_id && $item->pertemuan_blok_id === null); ?>
                <div class="list-group-item px-0 py-2" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'lampiran-'.e($item->id_lampiran_materi_blok).''; ?>wire:key="lampiran-<?php echo e($item->id_lampiran_materi_blok); ?>">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div class="flex-grow-1">
                            <div class="small">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->jenis === 'video'): ?>
                                    <span class="badge bg-danger-subtle text-danger"><i class="ri-video-line"></i> Video</span>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary"><i class="ri-links-line"></i> Modul</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($diwarisi): ?>
                                    <span class="badge bg-light text-dark border">dari materi</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->status !== 'aktif'): ?>
                                    <span class="badge bg-secondary-subtle text-secondary">nonaktif</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <span class="fw-semibold"><?php echo e($item->judul); ?></span>
                            </div>

                            <div class="small mt-1">
                                <a href="<?php echo e($item->url); ?>" target="_blank" rel="noopener nofollow">
                                    <?php echo e(\Illuminate\Support\Str::limit($item->url, 70)); ?>

                                    <i class="ri-external-link-line"></i>
                                </a>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->deskripsi): ?>
                                <div class="text-muted small mt-1"><?php echo e($item->deskripsi); ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bolehKelola && ! $diwarisi): ?>
                            <div class="text-end flex-shrink-0">
                                <button type="button" class="btn btn-light btn-sm"
                                    wire:click="edit('<?php echo e($item->id_lampiran_materi_blok); ?>')" title="Ubah">
                                    <i class="ri-file-edit-line"></i>
                                </button>
                                <button type="button" class="btn btn-light btn-sm"
                                    wire:click="toggleStatus('<?php echo e($item->id_lampiran_materi_blok); ?>')"
                                    title="<?php echo e($item->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'); ?>">
                                    <i class="<?php echo e($item->status === 'aktif' ? 'ri-eye-off-line' : 'ri-eye-line'); ?>"></i>
                                </button>
                                <button type="button" class="btn btn-light btn-sm text-danger"
                                    wire:click="hapus('<?php echo e($item->id_lampiran_materi_blok); ?>')"
                                    wire:confirm="Hapus tautan ini?" title="Hapus">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bolehKelola): ?>
        <form wire:submit="simpan" class="border rounded p-2 bg-light">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Jenis</label>
                    <select class="form-select form-select-sm" wire:model="form_jenis">
                        <option value="modul">Modul</option>
                        <option value="video">Video</option>
                    </select>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form_jenis'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="col-md-9">
                    <label class="form-label small mb-1">Judul</label>
                    <input type="text" class="form-control form-control-sm" wire:model="form_judul"
                        placeholder="Modul Praktikum Anatomi 1">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form_judul'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Tautan</label>
                    <input type="url" class="form-control form-control-sm" wire:model="form_url"
                        placeholder="https://drive.google.com/...">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Keterangan <span class="text-muted">(opsional)</span></label>
                    <input type="text" class="form-control form-control-sm" wire:model="form_deskripsi">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form_deskripsi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="simpan">
                    <i class="ri-save-line"></i> <?php echo e($edit_id ? 'PERBARUI' : 'TAMBAH'); ?>

                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit_id): ?>
                    <button type="button" class="btn btn-light btn-sm" wire:click="resetForm">Batal</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\blok-operasional\lampiran-materi.blade.php ENDPATH**/ ?>