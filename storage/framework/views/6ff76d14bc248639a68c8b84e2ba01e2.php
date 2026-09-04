<?php
use App\Exports\NilaiPertemuanTemplateExport;
use App\Imports\NilaiPertemuanImport;
use App\Models\KomponenPenilaianBlok;
use App\Models\NilaiPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\PesertaBlok;
use App\Models\RekapNilaiPertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
?>

<div class="nilai-pertemuan">
    <style>
        .nilai-pertemuan-matrix table {
            table-layout: fixed;
        }

        .nilai-pertemuan-matrix .nilai-no {
            width: 2.5rem;
        }

        .nilai-pertemuan-matrix .nilai-mahasiswa {
            width: 11.25rem;
        }

        .nilai-pertemuan-matrix .nilai-total {
            width: 7rem;
        }

        .nilai-pertemuan-matrix .nilai-komponen,
        .nilai-pertemuan-matrix .nilai-komponen-cell {
            overflow-wrap: anywhere;
        }

        .nilai-pertemuan-matrix thead .nilai-komponen {
            white-space: normal;
            line-height: 1.35;
        }

        .nilai-pertemuan-matrix .form-control {
            min-width: 0;
        }

        .nilai-pertemuan-label {
            display: none;
        }

        @media (max-width: 1199.98px) {
            .nilai-pertemuan-matrix {
                overflow: visible;
            }

            .nilai-pertemuan-matrix table,
            .nilai-pertemuan-matrix tbody {
                display: block;
            }

            .nilai-pertemuan-matrix thead {
                display: none;
            }

            .nilai-pertemuan-matrix tbody {
                display: grid;
                gap: .75rem;
            }

            .nilai-pertemuan-matrix tbody tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .75rem;
                padding: 1rem;
                border: 1px solid var(--line, #dde7e2);
                border-radius: .5rem;
                background: var(--surface, #fff);
            }

            .nilai-pertemuan-matrix tbody td {
                display: block;
                width: auto !important;
                padding: 0;
                border: 0;
            }

            .nilai-pertemuan-matrix tbody .nilai-no {
                display: none;
            }

            .nilai-pertemuan-matrix tbody .nilai-mahasiswa {
                grid-column: 1 / -1;
                padding-bottom: .75rem;
                border-bottom: 1px solid var(--line, #dde7e2);
            }

            .nilai-pertemuan-matrix tbody .nilai-total {
                grid-column: 1 / -1;
                padding-top: .75rem;
                border-top: 1px solid var(--line, #dde7e2);
            }

            .nilai-pertemuan-label {
                display: block;
                margin-bottom: .35rem;
                color: var(--muted, #6c757d);
                font-size: .75rem;
                font-weight: 600;
            }
        }

        @media (max-width: 575.98px) {
            .nilai-pertemuan-matrix tbody tr {
                grid-template-columns: minmax(0, 1fr);
                padding: .875rem;
            }

            .nilai-pertemuan .nilai-pertemuan-action,
            .nilai-pertemuan .nilai-pertemuan-submit,
            .nilai-pertemuan .nilai-pertemuan-submit .btn {
                width: 100%;
            }
        }
    </style>

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
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $perluPenilaian): ?>
        <div class="alert alert-warning py-2 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-information-line"></i>
            Jenis kegiatan ini ditandai <span class="fw-semibold">tidak perlu penilaian</span> pada susunan blok.
            Nilai yang tersimpan tetap ditampilkan, tapi sebaiknya nyalakan penanda penilaian bila memang dinilai.
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($komponen->isEmpty()): ?>
        <div class="alert alert-warning py-2 mb-0 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-graduation-cap-line"></i>
            Rubrik penilaian kegiatan ini belum disusun. Pengelola perlu mengisi komponen penilaian pada
            tab <span class="fw-semibold">Penilaian</span> di form Blok terlebih dahulu.
        </div>
    <?php elseif($anggota->isEmpty()): ?>
        <div class="alert alert-warning py-2 mb-0 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-group-line"></i>
            Kelompok pertemuan ini belum punya anggota aktif. Isi anggota kelompok terlebih dahulu.
        </div>
    <?php else: ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="d-flex flex-wrap gap-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rekap['sel_terisi'] === 0): ?>
                    <span class="badge bg-warning-subtle text-warning">Belum dinilai</span>
                <?php elseif($rekap['sel_terisi'] < $rekap['sel_total']): ?>
                    <span class="badge bg-warning-subtle text-warning">
                        Terisi <?php echo e($rekap['sel_terisi']); ?> dari <?php echo e($rekap['sel_total']); ?> isian
                    </span>
                <?php else: ?>
                    <span class="badge bg-success-subtle text-success">Lengkap</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span class="badge bg-light text-dark border"><?php echo e($anggota->count()); ?> mahasiswa</span>
                <span class="badge bg-light text-dark border"><?php echo e($komponen->count()); ?> komponen</span>
                <span class="badge bg-info-subtle text-info">Nilai maksimum <?php echo e($rekap['nilai_maks_total']); ?></span>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bolehIsi): ?>
                <button type="button" class="btn btn-outline-primary btn-sm text-nowrap nilai-pertemuan-action"
                    x-on:click="$dispatch('buka-import-nilai', { modalId: 'modal-import-nilai-<?php echo e($pertemuan_blok_id); ?>' })">
                    <i class="ri-upload-2-line"></i> Import
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <form wire:submit="simpan">
            <div class="table-responsive nilai-pertemuan-matrix">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="nilai-no">#</th>
                            <th class="nilai-mahasiswa text-center">Mahasiswa</th>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $komponen; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <th class="nilai-komponen text-center">
                                    <div><?php echo e($item->komponen_penilaian?->nama ?: $item->komponen_penilaian?->kode); ?></div>
                                    <div class="text-muted fw-normal small"><?php echo e($item->nilai_min); ?> - <?php echo e($item->nilai_maks); ?></div>
                                </th>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <th class="nilai-total text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $anggota; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $peserta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php ($pesertaId = $peserta->id_peserta_blok); ?>
                            <?php ($total = $this->totalPeserta($pesertaId)); ?>
                            <?php ($nilaiAkhir = $this->nilaiAkhirPeserta($pesertaId)); ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'nilai-'.e($pesertaId).''; ?>wire:key="nilai-<?php echo e($pesertaId); ?>">
                                <td class="text-muted nilai-no"><?php echo e($index + 1); ?></td>
                                <td class="nilai-mahasiswa">
                                    <div class="small fw-semibold"><?php echo e($peserta->mahasiswa?->nama); ?></div>
                                    <div class="text-muted small"><?php echo e($peserta->mahasiswa?->nim); ?></div>
                                </td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $komponen; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php ($namaKomponen = $item->komponen_penilaian?->nama ?: $item->komponen_penilaian?->kode); ?>
                                    <td class="nilai-komponen-cell">
                                        <label class="nilai-pertemuan-label"
                                            for="nilai-<?php echo e($pertemuan_blok_id); ?>-<?php echo e($pesertaId); ?>-<?php echo e($item->id); ?>">
                                            <?php echo e($namaKomponen); ?> (<?php echo e($item->nilai_min); ?>–<?php echo e($item->nilai_maks); ?>)
                                        </label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bolehIsi): ?>
                                            <input id="nilai-<?php echo e($pertemuan_blok_id); ?>-<?php echo e($pesertaId); ?>-<?php echo e($item->id); ?>"
                                                type="number" step="0.01"
                                                min="<?php echo e($item->nilai_min); ?>" max="<?php echo e($item->nilai_maks); ?>"
                                                aria-label="Nilai <?php echo e($namaKomponen); ?> untuk <?php echo e($peserta->mahasiswa?->nama); ?>"
                                                class="form-control form-control-sm"
                                                placeholder="-"
                                                wire:model.blur="nilai.<?php echo e($pesertaId); ?>.<?php echo e($item->id); ?>">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['nilai.'.$pesertaId.'.'.$item->id];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                <div class="small text-danger mt-1"><?php echo e($message); ?></div>
                                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php else: ?>
                                            <?php ($isian = trim((string) ($nilai[$pesertaId][$item->id] ?? ''))); ?>
                                            <span class="<?php echo e($isian === '' ? 'text-muted' : 'fw-semibold'); ?>">
                                                <?php echo e($isian === '' ? '-' : $isian); ?>

                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <td class="nilai-total">
                                    <span class="nilai-pertemuan-label">Total</span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($total === null): ?>
                                        <span class="text-muted small">-</span>
                                    <?php else: ?>
                                        <div>
                                            <span class="badge bg-primary-subtle text-primary">
                                                <?php echo e($total); ?> / <?php echo e($rekap['nilai_maks_total']); ?>

                                            </span>
                                        </div>
                                        <div class="small fw-semibold mt-1">
                                            <?php echo e(number_format($nilaiAkhir, 2, ',', '.')); ?> / 100
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-muted small mt-2">
                Kosongkan isian untuk membatalkan penilaian komponen tersebut. Nilai boleh diperbaiki kapan saja,
                termasuk setelah pertemuan divalidasi.
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bolehIsi): ?>
                    <br>
                    Lewat <span class="fw-semibold">Template Import</span>, berkas yang diunduh sudah memuat seluruh mahasiswa
                    beserta nilai yang tersimpan, jadi bisa dipakai untuk mengisi maupun mengoreksi. Saat diimport, sel
                    yang dikosongkan <span class="fw-semibold">menghapus</span> nilai komponen itu, sedangkan baris NIM
                    yang dihapus dari berkas tidak tersentuh. Bila ada satu baris yang ditolak, seluruh berkas dibatalkan.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bolehIsi): ?>
                <div class="mt-3 nilai-pertemuan-submit">
                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="simpan">
                        <i class="ri-save-line"></i> SIMPAN NILAI
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bolehIsi): ?>
            <template x-teleport="<?php echo e('body'); ?>">
                
                <div wire:ignore.self class="modal fade" id="modal-import-nilai-<?php echo e($pertemuan_blok_id); ?>" tabindex="-1"
                    aria-labelledby="modal-import-nilai-<?php echo e($pertemuan_blok_id); ?>-label" aria-hidden="true"
                    style="z-index: 1060;"
                    x-data="{ bodyStyle: null }"
                    x-on:buka-import-nilai.window="if ($event.detail.modalId === $el.id) { bodyStyle = document.body.getAttribute('style'); bootstrap.Modal.getOrCreateInstance($el, { backdrop: false }).show(); }"
                    x-init="$el.addEventListener('hidden.bs.modal', () => { const parent = document.getElementById('pelaksanaanModal'); if (parent?.classList.contains('show')) { bodyStyle === null ? document.body.removeAttribute('style') : document.body.setAttribute('style', bodyStyle); document.body.classList.add('modal-open'); parent.focus(); bodyStyle = null; } })"
                    x-on:import-nilai-berhasil.window="if ($event.detail.modalId === $el.id) bootstrap.Modal.getInstance($el)?.hide()">
                    <div class="modal-dialog modal-dialog-centered">
                        <form wire:submit="importNilai" class="modal-content" x-data="{ uploadError: '' }">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modal-import-nilai-<?php echo e($pertemuan_blok_id); ?>-label">Template Import</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body">
                                <button type="button" wire:click="unduhTemplate"
                                    wire:loading.attr="disabled" wire:target="unduhTemplate,importNilai"
                                    class="btn btn-outline-secondary btn-sm mb-3 d-block">
                                    <i class="ri-file-excel-2-line"></i> Template Import
                                </button>
                                <label for="import-file-nilai-<?php echo e($pertemuan_blok_id); ?>" class="form-label">File Import Nilai Pertemuan</label>
                                <input id="import-file-nilai-<?php echo e($pertemuan_blok_id); ?>" type="file" class="form-control"
                                    wire:model="importFile" wire:loading.attr="disabled" wire:target="importNilai"
                                    x-on:livewire-upload-start="uploadError = ''"
                                    x-on:livewire-upload-error="uploadError = 'File gagal diunggah. Coba lagi atau hubungi pengelola sistem.'"
                                    accept=".xlsx,.xls,.csv">
                                <div x-show="uploadError" x-text="uploadError" class="small text-danger mt-1"
                                    role="alert" style="display: none;"></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['importFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['import_nilai'];
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
                                <button type="submit" class="btn btn-primary"
                                    wire:loading.attr="disabled" wire:target="unduhTemplate,importNilai">
                                    <span wire:loading.remove wire:target="importNilai"><i class="ri-upload-2-line"></i> Import</span>
                                    <span wire:loading wire:target="importNilai">Memproses...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH D:\laragon\www\sistem-blok\storage\framework\views/livewire/views/45e6ff1c.blade.php ENDPATH**/ ?>