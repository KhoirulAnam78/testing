<?php
use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\KelompokBlok;
use App\Models\PertemuanBlok;
use App\Models\PresensiPertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
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
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card mb-0">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Pertemuan</div>
                    <div class="fs-5 fw-semibold"><?php echo e($ringkasan['total']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card mb-0">
                <div class="card-body py-2">
                    <div class="text-muted small">Belum Diisi</div>
                    <div class="fs-5 fw-semibold text-warning"><?php echo e($ringkasan['belum_dijurnal']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card mb-0">
                <div class="card-body py-2">
                    <div class="text-muted small">Tervalidasi</div>
                    <div class="fs-5 fw-semibold text-success"><?php echo e($ringkasan['tervalidasi']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card mb-0">
                <div class="card-body py-2">
                    <div class="text-muted small">Kehadiran</div>
                    <div class="fs-5 fw-semibold">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ringkasan['persen_hadir'] === null): ?>
                            <span class="text-muted fs-6">belum ada data</span>
                        <?php else: ?>
                            <?php echo e($ringkasan['persen_hadir']); ?>%
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="text-muted small"><?php echo e($ringkasan['presensi_tercatat']); ?> presensi tercatat</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-1">Monitoring Pertemuan</h5>
            <div class="text-muted small">
                Pengelola dapat mengisi maupun mengoreksi presensi, nilai, dan catatan monitoring,
                serta membuka kembali validasi yang keliru.
                Nilai tidak terkunci oleh validasi, jadi masih bisa diperbaiki kapan saja.
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Jenis Kegiatan</label>
                    <select class="form-select" wire:model.live="aturan_kegiatan_blok_id">
                        <option value="">Semua kegiatan</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $aturanOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $aturan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($aturan->id); ?>"><?php echo e($aturan->jenis_kegiatan?->nama); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelompok</label>
                    <select class="form-select" wire:model.live="kelompok_blok_id">
                        <option value="">Semua kelompok</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $kelompokOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kelompok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($kelompok->id_kelompok_blok); ?>"><?php echo e($kelompok->kode); ?> - <?php echo e($kelompok->nama); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status Pengisian</label>
                    <select class="form-select" wire:model.live="status_pengisian">
                        <option value="">Semua status</option>
                        <option value="belum">Belum diisi</option>
                        <option value="terisi">Sudah diisi</option>
                        <option value="tervalidasi">Tervalidasi</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cari</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ri-search-line"></i></span>
                        <input type="text" class="form-control" placeholder="Materi, kelompok, ruangan"
                            wire:model.live.debounce.400ms="search">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jadwal</th>
                            <th>Kegiatan</th>
                            <th>Kelompok</th>
                            <th>Materi</th>
                            <th>Dosen</th>
                            <th>Monitoring</th>
                            <th>Presensi</th>
                            <th>Nilai</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $pertemuanList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'monitoring-'.e($item->id_pertemuan_blok).''; ?>wire:key="monitoring-<?php echo e($item->id_pertemuan_blok); ?>">
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->tanggal): ?>
                                        <div><?php echo e($item->tanggal->format('d/m/Y')); ?></div>
                                    <?php else: ?>
                                        <div class="text-warning small">belum dijadwalkan</div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->jam_mulai): ?>
                                        <div class="text-muted small">
                                            <?php echo e(substr((string) $item->jam_mulai, 0, 5)); ?><?php echo e($item->jam_selesai ? '-'.substr((string) $item->jam_selesai, 0, 5) : ''); ?>

                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->ruangan): ?>
                                        <div class="text-muted small"><?php echo e($item->ruangan); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info">
                                        <?php echo e($item->aturan_kegiatan_blok?->jenis_kegiatan?->nama); ?>

                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?php echo e($item->kelompok_blok?->kode); ?></div>
                                    <div class="text-muted small"><?php echo e($item->kelompok_blok?->anggota_kelompok_blok_count); ?> anggota</div>
                                </td>
                                <td>
                                    <div class="small">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->materi_rinci_blok?->pertemuan_ke): ?>
                                            <span class="badge bg-light text-dark border">P<?php echo e($item->materi_rinci_blok->pertemuan_ke); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php echo e($item->materi_rinci_blok?->judul ?: $item->topik); ?>

                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted small">
                                        <?php echo e($item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->join(', ') ?: 'belum ada dosen'); ?>

                                    </div>
                                </td>
                                <td>
                                    <?php ($jurnal = $item->monitoring_pertemuan_blok); ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $jurnal): ?>
                                        <span class="badge bg-light text-dark border">belum diisi</span>
                                    <?php elseif($jurnal->divalidasi_pada): ?>
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="ri-shield-check-line"></i> Tervalidasi
                                        </span>
                                        <div class="small text-muted mt-1"><?php echo e($jurnal->divalidasi_pada->format('d/m/Y')); ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-info">sudah diisi</span>
                                        <div class="small text-muted mt-1">belum divalidasi</div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $item->aturan_kegiatan_blok?->perlu_presensi): ?>
                                        <span class="text-muted small">tidak perlu</span>
                                    <?php elseif(! $item->presensi_tercatat_count): ?>
                                        <span class="badge bg-warning-subtle text-warning">belum diisi</span>
                                    <?php else: ?>
                                        <div class="small fw-semibold">
                                            <?php echo e($item->presensi_hadir_count); ?>/<?php echo e($item->kelompok_blok?->anggota_kelompok_blok_count ?? $item->presensi_tercatat_count); ?>

                                        </div>
                                        <div class="text-muted small">hadir</div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $item->aturan_kegiatan_blok?->perlu_penilaian): ?>
                                        <span class="text-muted small">tidak dinilai</span>
                                    <?php else: ?>
                                        <?php ($komponenCount = (int) ($item->aturan_kegiatan_blok?->komponen_penilaian_blok_count ?? 0)); ?>
                                        <?php ($selTarget = $komponenCount * (int) ($item->kelompok_blok?->anggota_kelompok_blok_count ?? 0)); ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($komponenCount === 0): ?>
                                            <span class="badge bg-danger-subtle text-danger">rubrik kosong</span>
                                        <?php elseif(! $item->nilai_tercatat_count): ?>
                                            <span class="badge bg-warning-subtle text-warning">belum diisi</span>
                                        <?php else: ?>
                                            <div class="small fw-semibold">
                                                <?php echo e($item->nilai_tercatat_count); ?><?php echo e($selTarget > 0 ? '/'.$selTarget : ''); ?>

                                            </div>
                                            <div class="text-muted small">isian nilai</div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        wire:click="kelolaPelaksanaan('<?php echo e($item->id_pertemuan_blok); ?>', 'pelaksanaan')">
                                        <i class="ri-booklet-line"></i> Isi Monitoring
                                    </button>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->aturan_kegiatan_blok?->perlu_penilaian): ?>
                                        <button type="button" class="btn btn-outline-info btn-sm mt-1"
                                            wire:click="kelolaPelaksanaan('<?php echo e($item->id_pertemuan_blok); ?>', 'nilai')">
                                            <i class="ri-graduation-cap-line"></i> Nilai
                                        </button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    Tidak ada pertemuan yang cocok dengan filter ini.
                                </td>
                            </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3"><?php echo e($pertemuanList->links()); ?></div>
        </div>
    </div>

    <div class="modal fade" id="pelaksanaanModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Monitoring Pertemuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" wire:click="tutupPelaksanaan"></button>
                </div>
                <div class="modal-body">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $pelaksanaan_pertemuan_id): ?>
                        <div class="text-muted">Pilih tombol Monitoring atau Nilai pada salah satu pertemuan.</div>
                    <?php else: ?>
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="text-muted small">Pertemuan</div>
                            <div class="fw-semibold"><?php echo e($pelaksanaan_judul); ?></div>
                        </div>

                        <ul class="nav nav-pills mb-3">
                            <li class="nav-item">
                                <button type="button"
                                    class="nav-link <?php echo e($pelaksanaan_mode === 'pelaksanaan' ? 'active' : ''); ?>"
                                    wire:click="setPelaksanaanMode('pelaksanaan')">
                                    <i class="ri-booklet-line"></i> Monitoring
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button"
                                    class="nav-link <?php echo e($pelaksanaan_mode === 'nilai' ? 'active' : ''); ?>"
                                    wire:click="setPelaksanaanMode('nilai')">
                                    <i class="ri-graduation-cap-line"></i> Nilai
                                </button>
                            </li>
                        </ul>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pelaksanaan_mode === 'nilai'): ?>
                            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('blok-operasional.nilai-pertemuan', ['pertemuan_blok_id' => $pelaksanaan_pertemuan_id]);

$__keyOuter = $__key ?? null;

$__key = 'nilai-monitoring-'.$pelaksanaan_pertemuan_id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1962618498-0', $__key);

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
                        <?php else: ?>
                            <div class="mb-4">
                                <h6 class="mb-3"><i class="ri-booklet-line"></i> Monitoring</h6>
                                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('blok-operasional.jurnal-pertemuan', ['pertemuan_blok_id' => $pelaksanaan_pertemuan_id,'tampilkan_tombol_simpan' => false,'tampilkan_tombol_validasi' => false]);

$__keyOuter = $__key ?? null;

$__key = 'jurnal-monitoring-'.$pelaksanaan_pertemuan_id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1962618498-1', $__key);

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

                            <div class="border-top pt-4">
                                <h6 class="mb-3"><i class="ri-user-follow-line"></i> Presensi</h6>
                                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('blok-operasional.presensi-pertemuan', ['pertemuan_blok_id' => $pelaksanaan_pertemuan_id,'tampilkan_tombol_simpan' => false]);

$__keyOuter = $__key ?? null;

$__key = 'presensi-monitoring-'.$pelaksanaan_pertemuan_id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1962618498-2', $__key);

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

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $pelaksanaan_pertemuan_id)): ?>
                                <div class="border-top pt-3 mt-4 d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-primary btn-sm"
                                        wire:click="simpanPelaksanaan"
                                        wire:loading.attr="disabled"
                                        wire:target="simpanPelaksanaan,validasiPelaksanaan">
                                        <i class="ri-save-line"></i> SIMPAN
                                    </button>
                                    <button type="button" class="btn btn-success btn-sm"
                                        wire:click="validasiPelaksanaan"
                                        wire:confirm="Validasi pertemuan ini? Presensi dan jurnal akan terkunci."
                                        wire:loading.attr="disabled"
                                        wire:target="simpanPelaksanaan,validasiPelaksanaan">
                                        <i class="ri-shield-check-line"></i> SIMPAN & VALIDASI
                                    </button>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" wire:click="tutupPelaksanaan">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/dee82853.blade.php ENDPATH**/ ?>