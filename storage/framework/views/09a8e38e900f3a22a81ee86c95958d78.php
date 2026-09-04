<?php

use App\Models\Blok;
use App\Models\PertemuanBlok;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Locked]
    public int $blok_id;

    public string $kegiatan_id = '';

    public string $pertemuan_id = '';

    public string $kelompok_id = '';

    public string $status = '';

    #[Locked]
    public ?int $logbook_pertemuan_id = null;

    public string $logbook_judul = '';

    public string $logbook_konteks = '';

    public function mount($blok_id): void
    {
        $this->blok_id = (int) $blok_id;

        $blok = Blok::select('id')->findOrFail($this->blok_id);

        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);
    }

    public function updated($property): void
    {
        if (in_array($property, ['kegiatan_id', 'pertemuan_id', 'kelompok_id', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function pertemuanQuery()
    {
        return PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->whereHas('aturan_kegiatan_blok', fn ($query) => $query->where('perlu_logbook', true))
            ->when($this->kegiatan_id !== '', fn ($query) => $query->whereHas(
                'aturan_kegiatan_blok',
                fn ($aturan) => $aturan->where('jenis_kegiatan_id', (int) $this->kegiatan_id)
            ))
            ->when($this->pertemuan_id !== '', fn ($query) => $query->whereKey((int) $this->pertemuan_id))
            ->when($this->kelompok_id !== '', fn ($query) => $query->where('kelompok_blok_id', (int) $this->kelompok_id))
            ->when($this->status !== '', function ($query) {
                if ($this->status === 'belum') {
                    $query->whereDoesntHave('logbook_pertemuan_blok');
                } else {
                    $query->whereHas('logbook_pertemuan_blok', fn ($logbook) => $logbook->where('status', $this->status));
                }
            })
            ->with([
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
                'kelompok_blok:id_kelompok_blok,kode,nama',
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
            ])
            ->orderBy('tanggal')
            ->orderBy('jam_mulai');
    }

    public function bukaLogbook(string $id): void
    {
        $pertemuan = PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->whereHas('aturan_kegiatan_blok', fn ($query) => $query->where('perlu_logbook', true))
            ->with([
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
                'kelompok_blok:id_kelompok_blok,kode,nama',
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
            ])
            ->findOrFail((int) $id);

        $this->logbook_pertemuan_id = $pertemuan->id_pertemuan_blok;
        $this->logbook_judul = $pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik ?: 'Pertemuan';
        $this->logbook_konteks = implode(' · ', array_filter([
            $pertemuan->aturan_kegiatan_blok?->jenis_kegiatan?->nama,
            $pertemuan->materi_rinci_blok?->pertemuan_ke
                ? 'Pertemuan '.$pertemuan->materi_rinci_blok->pertemuan_ke
                : null,
            $pertemuan->kelompok_blok?->kode,
            $pertemuan->tanggal?->format('d/m/Y'),
        ]));

        $this->dispatch('show-logbook-modal');
    }

    public function tutupLogbook(): void
    {
        $this->reset(['logbook_pertemuan_id', 'logbook_judul', 'logbook_konteks']);
    }

    public function options()
    {
        $query = PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->whereHas('aturan_kegiatan_blok', fn ($aturan) => $aturan->where('perlu_logbook', true));

        return [
            'kegiatan' => (clone $query)->with('aturan_kegiatan_blok.jenis_kegiatan:id,nama')->get()
                ->pluck('aturan_kegiatan_blok.jenis_kegiatan')->filter()->unique('id')->sortBy('nama'),
            'pertemuan' => (clone $query)->with('materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke')->get(),
            'kelompok' => (clone $query)->with('kelompok_blok:id_kelompok_blok,kode,nama')->get()
                ->pluck('kelompok_blok')->filter()->unique('id_kelompok_blok')->sortBy('kode'),
        ];
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
    <?php ($options = $this->options()); ?>
    <?php ($pertemuan = $this->pertemuanQuery()->paginate(10)); ?>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Logbook Pertemuan</h5></div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Kegiatan</label>
                    <select class="form-select" wire:model.live="kegiatan_id">
                        <option value="">Semua kegiatan</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $options['kegiatan']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($item->id); ?>"><?php echo e($item->nama); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pertemuan</label>
                    <select class="form-select" wire:model.live="pertemuan_id">
                        <option value="">Semua pertemuan</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $options['pertemuan']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($item->id_pertemuan_blok); ?>">
                                <?php echo e($item->materi_rinci_blok?->judul ?: $item->topik ?: 'Pertemuan '.$item->id_pertemuan_blok); ?>

                            </option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelompok</label>
                    <select class="form-select" wire:model.live="kelompok_id">
                        <option value="">Semua kelompok</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $options['kelompok']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($item->id_kelompok_blok); ?>"><?php echo e($item->kode); ?> - <?php echo e($item->nama); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" wire:model.live="status">
                        <option value="">Semua status</option>
                        <option value="belum">Belum unggah</option>
                        <option value="menunggu">Menunggu validasi</option>
                        <option value="valid">Valid</option>
                        <option value="ditolak">Ditolak</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jadwal</th>
                            <th>Kegiatan</th>
                            <th>Pertemuan</th>
                            <th>Kelompok</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $pertemuan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'logbook-operasional-'.e($item->id_pertemuan_blok).''; ?>wire:key="logbook-operasional-<?php echo e($item->id_pertemuan_blok); ?>">
                                <td>
                                    <div class="fw-semibold"><?php echo e($item->tanggal?->format('d/m/Y') ?: 'Belum terjadwal'); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->jam_mulai || $item->jam_selesai): ?>
                                        <div class="text-muted small">
                                            <?php echo e($item->jam_mulai ? substr($item->jam_mulai, 0, 5) : ''); ?><?php echo e($item->jam_selesai ? '–'.substr($item->jam_selesai, 0, 5) : ''); ?>

                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td><?php echo e($item->aturan_kegiatan_blok?->jenis_kegiatan?->nama ?: '-'); ?></td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->materi_rinci_blok?->pertemuan_ke): ?>
                                        <span class="badge bg-light text-dark border mb-1">Pertemuan <?php echo e($item->materi_rinci_blok->pertemuan_ke); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <div class="fw-semibold"><?php echo e($item->materi_rinci_blok?->judul ?: $item->topik ?: 'Pertemuan'); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo e($item->kelompok_blok?->kode ?: 'Tanpa kelompok'); ?></div>
                                    <div class="text-muted small"><?php echo e($item->kelompok_blok?->nama); ?></div>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                        wire:click="bukaLogbook('<?php echo e($item->id_pertemuan_blok); ?>')">
                                        <i class="ri-file-list-3-line"></i> Logbook
                                    </button>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Tidak ada pertemuan logbook sesuai filter.
                                </td>
                            </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3"><?php echo e($pertemuan->links()); ?></div>
        </div>
    </div>

    <div class="modal fade" id="logbookModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Logbook Pertemuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" wire:click="tutupLogbook"></button>
                </div>
                <div class="modal-body">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $logbook_pertemuan_id): ?>
                        <div class="text-muted">Pilih tombol Logbook pada salah satu pertemuan.</div>
                    <?php else: ?>
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="fw-semibold"><?php echo e($logbook_judul); ?></div>
                            <div class="text-muted small"><?php echo e($logbook_konteks); ?></div>
                        </div>

                        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('logbook-pertemuan', ['pertemuan_blok_id' => $logbook_pertemuan_id]);

$__keyOuter = $__key ?? null;

$__key = 'logbook-operasional-detail-'.$logbook_pertemuan_id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2384869407-0', $__key);

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
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" wire:click="tutupLogbook">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\blok-operasional\logbook.blade.php ENDPATH**/ ?>