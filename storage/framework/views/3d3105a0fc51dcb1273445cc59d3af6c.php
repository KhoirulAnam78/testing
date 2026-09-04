<?php

use App\Models\MonitoringPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\PresensiPertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Jurnal pelaksanaan satu pertemuan, sekaligus gerbang validasinya.
 *
 * Jadwal realisasi diprefill dari jadwal rencana pertemuan supaya dosen hanya perlu
 * mengubah bila pelaksanaannya bergeser. Status pelaksanaan disimpan sebagai
 * `terlaksana` untuk kompatibilitas data lama, tanpa input status dari pengguna.
 *
 * Setelah divalidasi, presensi dan jurnal terkunci untuk semua peran. Pengelola harus
 * membuka validasi lebih dulu supaya koreksi meninggalkan jejak.
 */
new class extends Component
{
    public int $pertemuan_blok_id;
    public bool $tampilkan_tombol_simpan = true;
    public bool $tampilkan_tombol_validasi = true;

    public ?string $tanggal_realisasi = null;
    public ?string $jam_mulai_realisasi = null;
    public ?string $jam_selesai_realisasi = null;
    public string $topik_realisasi = '';
    public string $catatan_pelaksanaan = '';
    public string $kendala = '';

    public function mount(int $pertemuan_blok_id): void
    {
        $this->pertemuan_blok_id = $pertemuan_blok_id;

        abort_unless(
            AksesPertemuanBlok::bolehKelolaPertemuan(auth()->user(), $this->pertemuan_blok_id),
            403
        );

        $this->muatJurnal();
    }

    private function muatJurnal(): void
    {
        $pertemuan = $this->pertemuan();
        $jurnal = $pertemuan->monitoring_pertemuan_blok;

        // Belum pernah diisi: pakai jadwal rencana pertemuan sebagai titik awal.
        $this->tanggal_realisasi = $jurnal
            ? $jurnal->tanggal_realisasi?->toDateString()
            : $pertemuan->tanggal?->toDateString();
        $this->jam_mulai_realisasi = $this->formatJam($jurnal ? $jurnal->jam_mulai_realisasi : $pertemuan->jam_mulai);
        $this->jam_selesai_realisasi = $this->formatJam($jurnal ? $jurnal->jam_selesai_realisasi : $pertemuan->jam_selesai);
        $this->topik_realisasi = (string) ($jurnal ? $jurnal->topik_realisasi : $pertemuan->topik);
        $this->catatan_pelaksanaan = (string) ($jurnal?->catatan_pelaksanaan ?? '');
        $this->kendala = (string) ($jurnal?->kendala ?? '');
    }

    private function pertemuan(): PertemuanBlok
    {
        return PertemuanBlok::query()
            ->with([
                'monitoring_pertemuan_blok',
                'monitoring_pertemuan_blok.divalidasi_oleh:id,name',
                'aturan_kegiatan_blok:id,perlu_presensi',
                'materi_rinci_blok:id_materi_rinci_blok,judul',
            ])
            ->findOrFail($this->pertemuan_blok_id);
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }

    public function terkunci(): bool
    {
        return AksesPertemuanBlok::terkunci($this->pertemuan_blok_id);
    }

    public function bolehIsi(): bool
    {
        return AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $this->pertemuan_blok_id);
    }

    public function bolehBukaValidasi(): bool
    {
        return AksesPertemuanBlok::bolehBukaValidasi(auth()->user(), $this->pertemuan_blok_id);
    }

    public function simpan(): void
    {
        $this->tulisJurnal(validasi: false);
    }

    #[On('simpan-pelaksanaan')]
    public function simpanDariPelaksanaan(int $pertemuan_blok_id): void
    {
        if ($pertemuan_blok_id === $this->pertemuan_blok_id) {
            $this->simpan();
        }
    }

    #[On('validasi-pelaksanaan')]
    public function validasiDariPelaksanaan(int $pertemuan_blok_id): void
    {
        if ($pertemuan_blok_id === $this->pertemuan_blok_id) {
            $this->validasi();
        }
    }

    public function validasi(): void
    {
        $this->tulisJurnal(validasi: true);
    }

    private function tulisJurnal(bool $validasi): void
    {
        abort_unless($this->bolehIsi(), 403);

        $data = $this->validate([
            'tanggal_realisasi' => ['required', 'date_format:Y-m-d'],
            'jam_mulai_realisasi' => ['nullable', 'date_format:H:i'],
            'jam_selesai_realisasi' => ['nullable', 'date_format:H:i'],
            'topik_realisasi' => ['nullable', 'string', 'max:255'],
            'catatan_pelaksanaan' => ['nullable', 'string', 'max:2000'],
            'kendala' => ['nullable', 'string', 'max:2000'],
        ], [
            'tanggal_realisasi.required' => 'Tanggal pelaksanaan wajib diisi.',
            'tanggal_realisasi.date_format' => 'Tanggal pelaksanaan harus berformat YYYY-MM-DD.',
            'jam_mulai_realisasi.date_format' => 'Jam mulai harus berformat HH:MM.',
            'jam_selesai_realisasi.date_format' => 'Jam selesai harus berformat HH:MM.',
            'topik_realisasi.max' => 'Topik maksimal 255 karakter.',
            'catatan_pelaksanaan.max' => 'Catatan maksimal 2000 karakter.',
            'kendala.max' => 'Kendala maksimal 2000 karakter.',
        ]);

        if (! $this->lolosAturanDomain($data, $validasi)) {
            return;
        }

        $pertemuan = $this->pertemuan();

        DB::transaction(function () use ($data, $validasi, $pertemuan) {
            $muatan = [
                'status_pelaksanaan' => 'terlaksana',
                'tanggal_realisasi' => $data['tanggal_realisasi'] ?: null,
                'jam_mulai_realisasi' => $data['jam_mulai_realisasi'] ?: null,
                'jam_selesai_realisasi' => $data['jam_selesai_realisasi'] ?: null,
                'topik_realisasi' => trim((string) $data['topik_realisasi']) ?: null,
                'catatan_pelaksanaan' => trim((string) $data['catatan_pelaksanaan']) ?: null,
                'kendala' => trim((string) $data['kendala']) ?: null,
                'diisi_oleh_user_id' => auth()->id(),
            ];

            if ($validasi) {
                $muatan['divalidasi_pada'] = now();
                $muatan['divalidasi_oleh_user_id'] = auth()->id();
            }

            MonitoringPertemuanBlok::updateOrCreate(
                ['pertemuan_blok_id' => $this->pertemuan_blok_id],
                $muatan
            );

            $pertemuan->status = MonitoringPertemuanBlok::STATUS_PERTEMUAN['terlaksana'];
            $pertemuan->save();
        });

        $this->muatJurnal();
        $this->dispatch('jurnal-pertemuan-tersimpan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $validasi
                ? 'Monitoring pelaksanaan divalidasi. Presensi dan catatan monitoring pertemuan ini terkunci.'
                : 'Monitoring pelaksanaan berhasil disimpan.',
        ]);
    }

    /**
     * Aturan yang tidak bisa diungkapkan sebagai rule validasi biasa. Pola addError
     * lalu return mengikuti `savePertemuan()` pada tab Pertemuan.
     *
     * @param  array<string, mixed>  $data
     */
    private function lolosAturanDomain(array $data, bool $validasi): bool
    {
        if ($data['jam_selesai_realisasi'] && ! $data['jam_mulai_realisasi']) {
            $this->addError('jam_mulai_realisasi', 'Jam mulai wajib diisi bila jam selesai diisi.');

            return false;
        }

        if (
            $data['jam_mulai_realisasi'] && $data['jam_selesai_realisasi']
            && $data['jam_selesai_realisasi'] <= $data['jam_mulai_realisasi']
        ) {
            $this->addError('jam_selesai_realisasi', 'Jam selesai harus lebih besar dari jam mulai.');

            return false;
        }

        // Validasi mengunci presensi, jadi pastikan presensi sudah tercatat.
        if ($validasi && $this->perluPresensi()) {
            $adaPresensi = PresensiPertemuanBlok::query()
                ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
                ->exists();

            if (! $adaPresensi) {
                $this->addError('tanggal_realisasi', 'Isi presensi terlebih dahulu sebelum memvalidasi pertemuan.');

                return false;
            }
        }

        return true;
    }

    public function bukaValidasi(): void
    {
        abort_unless($this->bolehBukaValidasi(), 403);

        MonitoringPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->update([
                'divalidasi_pada' => null,
                'divalidasi_oleh_user_id' => null,
            ]);

        $this->muatJurnal();
        $this->dispatch('jurnal-pertemuan-tersimpan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Validasi dibuka. Presensi dan jurnal bisa dikoreksi kembali.',
        ]);
    }

    public function perluPresensi(): bool
    {
        return (bool) ($this->pertemuan()->aturan_kegiatan_blok?->perlu_presensi ?? true);
    }

    public function render()
    {
        $pertemuan = $this->pertemuan();

        return $this->view([
            'pertemuan' => $pertemuan,
            'jurnal' => $pertemuan->monitoring_pertemuan_blok,
            'terkunci' => $this->terkunci(),
            'bolehIsi' => $this->bolehIsi(),
            'bolehBuka' => $this->bolehBukaValidasi(),
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
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($terkunci): ?>
        <div class="alert alert-secondary py-2 d-flex flex-wrap justify-content-between align-items-center gap-2 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <div>
                <i class="ri-lock-line"></i>
                Sudah divalidasi
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jurnal?->divalidasi_oleh): ?>
                    oleh <span class="fw-semibold"><?php echo e($jurnal->divalidasi_oleh->name); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jurnal?->divalidasi_pada): ?>
                    pada <?php echo e($jurnal->divalidasi_pada->format('d/m/Y H:i')); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                . Presensi dan catatan monitoring terkunci.
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bolehBuka): ?>
                <button type="button" class="btn btn-outline-danger btn-sm"
                    wire:click="bukaValidasi"
                    wire:confirm="Buka kembali validasi pertemuan ini agar bisa dikoreksi?">
                    <i class="ri-lock-unlock-line"></i> Buka Validasi
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="border rounded p-3 mb-3 bg-light">
        <div class="text-muted small">Jadwal Rencana</div>
        <div class="fw-semibold"><?php echo e($pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik); ?></div>
        <div class="text-muted small mt-1">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pertemuan->tanggal): ?>
                <?php echo e($pertemuan->tanggal->format('d/m/Y')); ?>

            <?php else: ?>
                tanggal belum ditetapkan
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pertemuan->jam_mulai): ?>
                &middot; <?php echo e(substr((string) $pertemuan->jam_mulai, 0, 5)); ?><?php echo e($pertemuan->jam_selesai ? '-'.substr((string) $pertemuan->jam_selesai, 0, 5) : ''); ?>

            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pertemuan->ruangan): ?>
                &middot; <?php echo e($pertemuan->ruangan); ?>

            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <form wire:submit="simpan">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small mb-1">Tanggal Pelaksanaan</label>
                <input type="date" class="form-control form-control-sm" wire:model="tanggal_realisasi" <?php if(! $bolehIsi): echo 'disabled'; endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tanggal_realisasi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Mulai</label>
                <input type="time" class="form-control form-control-sm" wire:model="jam_mulai_realisasi" <?php if(! $bolehIsi): echo 'disabled'; endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['jam_mulai_realisasi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Selesai</label>
                <input type="time" class="form-control form-control-sm" wire:model="jam_selesai_realisasi" <?php if(! $bolehIsi): echo 'disabled'; endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['jam_selesai_realisasi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label small mb-1">Topik yang Disampaikan</label>
                <input type="text" class="form-control form-control-sm" wire:model="topik_realisasi" <?php if(! $bolehIsi): echo 'disabled'; endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['topik_realisasi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="col-md-6">
                <label class="form-label small mb-1">Catatan Pelaksanaan <span class="text-muted">(opsional)</span></label>
                <textarea class="form-control form-control-sm" rows="3" wire:model="catatan_pelaksanaan" <?php if(! $bolehIsi): echo 'disabled'; endif; ?>></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['catatan_pelaksanaan'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">Kendala <span class="text-muted">(opsional)</span></label>
                <textarea class="form-control form-control-sm" rows="3" wire:model="kendala" <?php if(! $bolehIsi): echo 'disabled'; endif; ?>></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kendala'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="small text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bolehIsi): ?>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tampilkan_tombol_simpan): ?>
                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="simpan,validasi">
                        <i class="ri-save-line"></i> SIMPAN
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tampilkan_tombol_validasi): ?>
                    <button type="button" class="btn btn-success btn-sm"
                        wire:click="validasi"
                        wire:confirm="Validasi pertemuan ini? Presensi dan jurnal akan terkunci."
                        wire:loading.attr="disabled" wire:target="simpan,validasi">
                        <i class="ri-shield-check-line"></i> SIMPAN & VALIDASI
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="text-muted small mt-2">
                Validasi mengunci presensi dan catatan monitoring pertemuan ini. Hanya pengelola yang bisa membukanya kembali.
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </form>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\blok-operasional\jurnal-pertemuan.blade.php ENDPATH**/ ?>