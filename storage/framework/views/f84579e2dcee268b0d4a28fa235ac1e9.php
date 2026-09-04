<?php

use App\Models\LogbookPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\PesertaBlok;
use App\Support\AksesPertemuanBlok;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $pertemuan_blok_id;

    public $file;

    public array $catatan = [];

    public function mount(int $pertemuan_blok_id): void
    {
        $this->pertemuan_blok_id = $pertemuan_blok_id;
        abort_unless(AksesPertemuanBlok::logbookAktif($pertemuan_blok_id), 404);
        abort_unless(
            AksesPertemuanBlok::bolehValidasiLogbook(auth()->user(), $pertemuan_blok_id)
            || AksesPertemuanBlok::bolehLihatPertemuan(auth()->user(), $pertemuan_blok_id),
            403
        );
    }

    public function unggah(): void
    {
        $mahasiswaId = (int) (auth()->user()?->mahasiswa?->id_mahasiswa ?? 0);
        abort_unless(AksesPertemuanBlok::bolehUnggahLogbook(auth()->user(), $this->pertemuan_blok_id, $mahasiswaId), 403);

        $this->validate([
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:10240'],
        ], [
            'file.required' => 'File PDF wajib dipilih.',
            'file.mimetypes' => 'File wajib berformat PDF.',
            'file.mimes' => 'File wajib berformat PDF.',
            'file.max' => 'Ukuran PDF maksimal 10 MB.',
        ]);

        $lama = LogbookPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->where('mahasiswa_id', $mahasiswaId)
            ->first();

        abort_if($lama?->status === 'valid', 422, 'Logbook tervalidasi sudah terkunci.');

        $namaFileAsli = $this->file->getClientOriginalName();
        $ukuranFile = $this->file->getSize();
        $pathBaru = $this->file->store("logbook/{$this->pertemuan_blok_id}", 'local');
        abort_unless($pathBaru, 500, 'File gagal disimpan.');

        try {
            LogbookPertemuanBlok::updateOrCreate(
                ['pertemuan_blok_id' => $this->pertemuan_blok_id, 'mahasiswa_id' => $mahasiswaId],
                [
                    'path_file' => $pathBaru,
                    'nama_file_asli' => $namaFileAsli,
                    'ukuran_file' => $ukuranFile,
                    'status' => 'menunggu',
                    'catatan_validasi' => null,
                    'diunggah_pada' => now(),
                    'divalidasi_pada' => null,
                    'divalidasi_oleh_user_id' => null,
                ]
            );
        } catch (Throwable $e) {
            Storage::disk('local')->delete($pathBaru);
            throw $e;
        }

        if ($lama && $lama->path_file !== $pathBaru) {
            Storage::disk('local')->delete($lama->path_file);
        }

        $this->reset('file');
        $this->dispatch('logbook-tersimpan');
    }

    public function validasi(int $id): void
    {
        $logbook = $this->logbook($id);
        abort_unless(AksesPertemuanBlok::bolehValidasiLogbook(auth()->user(), $this->pertemuan_blok_id), 403);

        $logbook->update([
            'status' => 'valid',
            'catatan_validasi' => null,
            'divalidasi_pada' => now(),
            'divalidasi_oleh_user_id' => auth()->id(),
        ]);
    }

    public function tolak(int $id): void
    {
        $logbook = $this->logbook($id);
        abort_unless(AksesPertemuanBlok::bolehValidasiLogbook(auth()->user(), $this->pertemuan_blok_id), 403);

        $this->validate(["catatan.$id" => ['required', 'string', 'max:2000']], [
            "catatan.$id.required" => 'Catatan penolakan wajib diisi.',
        ]);

        $logbook->update([
            'status' => 'ditolak',
            'catatan_validasi' => trim($this->catatan[$id]),
            'divalidasi_pada' => now(),
            'divalidasi_oleh_user_id' => auth()->id(),
        ]);
    }

    private function logbook(int $id): LogbookPertemuanBlok
    {
        return LogbookPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->findOrFail($id);
    }

    public function render()
    {
        $pertemuan = PertemuanBlok::findOrFail($this->pertemuan_blok_id);
        $validator = AksesPertemuanBlok::bolehValidasiLogbook(auth()->user(), $this->pertemuan_blok_id);
        $mahasiswaId = (int) (auth()->user()?->mahasiswa?->id_mahasiswa ?? 0);
        $bolehUnggah = AksesPertemuanBlok::bolehUnggahLogbook(auth()->user(), $this->pertemuan_blok_id, $mahasiswaId);

        $peserta = $validator
            ? PesertaBlok::query()
                ->select('peserta_blok.*')
                ->join('anggota_kelompok_blok', 'anggota_kelompok_blok.peserta_blok_id', '=', 'peserta_blok.id_peserta_blok')
                ->where('anggota_kelompok_blok.kelompok_blok_id', $pertemuan->kelompok_blok_id)
                ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
                ->with('mahasiswa:id_mahasiswa,nim,nama')
                ->get()
            : collect();

        $logbooks = LogbookPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->when(! $validator, fn ($query) => $query->where('mahasiswa_id', $mahasiswaId))
            ->get()
            ->keyBy('mahasiswa_id');

        return $this->view(compact('validator', 'mahasiswaId', 'bolehUnggah', 'peserta', 'logbooks'));
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
    <?php ($warna = ['menunggu' => 'warning', 'valid' => 'success', 'ditolak' => 'danger']); ?>
    <?php ($label = ['menunggu' => 'Menunggu Validasi', 'valid' => 'Valid', 'ditolak' => 'Ditolak']); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $validator): ?>
        <?php ($logbook = $logbooks->get($mahasiswaId)); ?>
        <form wire:submit="unggah">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logbook): ?>
                <span class="badge bg-<?php echo e($warna[$logbook->status]); ?>-subtle text-<?php echo e($warna[$logbook->status]); ?>"><?php echo e($label[$logbook->status]); ?></span>
                <a class="btn btn-link btn-sm" href="<?php echo e(route('logbook.download', $logbook)); ?>"><i class="ri-download-line"></i> Unduh</a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logbook->status === 'ditolak'): ?>
                    <div class="alert alert-danger py-2 mt-2 alert-dismissible fade show" role="alert">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                        <?php echo e($logbook->catatan_validasi); ?></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((! $logbook || $logbook->status !== 'valid') && $bolehUnggah): ?>
                <input type="file" class="form-control mt-2" wire:model="file" accept="application/pdf">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <button class="btn btn-primary btn-sm mt-2" type="submit">Unggah PDF</button>
            <?php elseif((! $logbook || $logbook->status !== 'valid') && ! $bolehUnggah): ?>
                <div class="alert alert-info py-2 mt-2 mb-0">
                    Logbook dapat diunggah setelah monitoring pertemuan divalidasi.
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    <?php else: ?>
        <?php ($rekap = ['belum' => 0, 'menunggu' => 0, 'valid' => 0, 'ditolak' => 0]); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $peserta; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php ($rekap[$logbooks->get($item->mahasiswa_id)?->status ?? 'belum']++); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <div class="mb-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rekap; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $jumlah): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <span class="badge bg-light text-dark border"><?php echo e(ucfirst($status)); ?>: <?php echo e($jumlah); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Mahasiswa</th><th>Status</th><th>File</th><th>Validasi</th></tr></thead>
                <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $peserta; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php ($logbook = $logbooks->get($item->mahasiswa_id)); ?>
                    <tr>
                        <td><?php echo e($item->mahasiswa?->nama); ?><div class="small text-muted"><?php echo e($item->mahasiswa?->nim); ?></div></td>
                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logbook): ?>
                                <span class="badge bg-<?php echo e($warna[$logbook->status]); ?>-subtle text-<?php echo e($warna[$logbook->status]); ?>"><?php echo e($label[$logbook->status]); ?></span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border">Belum Unggah</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logbook): ?><a href="<?php echo e(route('logbook.download', $logbook)); ?>">Unduh PDF</a><?php else: ?> - <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logbook && $logbook->status !== 'valid'): ?>
                                <button class="btn btn-success btn-sm" wire:click="validasi(<?php echo e($logbook->id); ?>)">Validasi</button>
                                <input class="form-control form-control-sm mt-1" placeholder="Catatan wajib untuk tolak" wire:model="catatan.<?php echo e($logbook->id); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['catatan.'.$logbook->id];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <button class="btn btn-danger btn-sm mt-1" wire:click="tolak(<?php echo e($logbook->id); ?>)">Tolak</button>
                            <?php elseif($logbook?->catatan_validasi): ?>
                                <span class="small text-danger"><?php echo e($logbook->catatan_validasi); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\logbook-pertemuan.blade.php ENDPATH**/ ?>