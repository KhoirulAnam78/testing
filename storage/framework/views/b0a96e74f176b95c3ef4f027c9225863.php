<?php

use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Support\PerhitunganDpnaBlok;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    #[Locked]
    public int $blokId;

    public bool $kehadiranAktif = false;
    public string $bobotKehadiran = '0';
    public array $kegiatan = [];
    public ?int $pesertaTerpilih = null;

    public function mount(string $id): void
    {
        try {
            $this->blokId = (int) Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404, 'Enkripsi tidak valid !');
        }

        $blok = Blok::with('aturan_kegiatan_blok')->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        $this->kehadiranAktif = $blok->kehadiran_masuk_dpna;
        $this->bobotKehadiran = (string) $blok->bobot_kehadiran_dpna;
        foreach ($blok->aturan_kegiatan_blok as $aturan) {
            $this->kegiatan[$aturan->id] = [
                'aktif' => $aturan->nilai_masuk_dpna,
                'bobot' => (string) $aturan->bobot_nilai_dpna,
            ];
        }
    }

    public function simpanBobot(): void
    {
        $blok = Blok::with('aturan_kegiatan_blok:id,blok_id')->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        $this->validate([
            'kehadiranAktif' => ['boolean'],
            'bobotKehadiran' => ['required', 'numeric', 'min:0', 'max:100'],
            'kegiatan' => ['array'],
            'kegiatan.*.aktif' => ['boolean'],
            'kegiatan.*.bobot' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $sumber = [[
            'aktif' => $this->kehadiranAktif,
            'bobot' => (float) $this->bobotKehadiran,
            'field' => 'bobotKehadiran',
        ]];
        foreach ($blok->aturan_kegiatan_blok as $aturan) {
            $config = $this->kegiatan[$aturan->id] ?? ['aktif' => false, 'bobot' => 0];
            $sumber[] = [
                'aktif' => (bool) $config['aktif'],
                'bobot' => (float) $config['bobot'],
                'field' => "kegiatan.{$aturan->id}.bobot",
            ];
        }

        foreach ($sumber as $item) {
            if ($item['aktif'] && $item['bobot'] <= 0) {
                $this->addError($item['field'], 'Sumber aktif wajib memiliki bobot lebih dari 0.');
            }
            if (! $item['aktif'] && $item['bobot'] != 0) {
                $this->addError($item['field'], 'Sumber nonaktif wajib berbobot 0.');
            }
        }

        $total = collect($sumber)->where('aktif', true)->sum('bobot');
        if (abs($total - 100) > .001) {
            $this->addError('totalBobot', 'Total bobot sumber aktif wajib tepat 100%. Saat ini '.number_format($total, 2, ',', '.').'%.');
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($blok): void {
            $blok->update([
                'kehadiran_masuk_dpna' => $this->kehadiranAktif,
                'bobot_kehadiran_dpna' => $this->kehadiranAktif ? $this->bobotKehadiran : 0,
            ]);
            foreach ($blok->aturan_kegiatan_blok as $aturan) {
                $config = $this->kegiatan[$aturan->id];
                AturanKegiatanBlok::whereKey($aturan->id)->update([
                    'nilai_masuk_dpna' => (bool) $config['aktif'],
                    'bobot_nilai_dpna' => $config['aktif'] ? $config['bobot'] : 0,
                ]);
            }
        });

        session()->flash('success', 'Konfigurasi bobot DPNA berhasil disimpan.');
    }

    public function updatedKehadiranAktif(bool $aktif): void
    {
        if (! $aktif) {
            $this->bobotKehadiran = '0';
        }
    }

    public function updatedKegiatan(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.aktif') && ! $value) {
            data_set($this->kegiatan, str($key)->beforeLast('.')->append('.bobot')->value(), '0');
        }
    }

    public function pilihPeserta(int $id): void
    {
        $this->pesertaTerpilih = $this->pesertaTerpilih === $id ? null : $id;
    }

    public function data(): array
    {
        $blok = Blok::with(['prodi:id_prodi,nama', 'semester:id_semester,nama,tahun'])->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        return ['blok' => $blok] + app(PerhitunganDpnaBlok::class)->rekap($blok);
    }
}; ?>

<div>
    <?php ($data = $this->data()); ?>
    <?php ($blok = $data['blok']); ?>
    <?php ($barisTerpilih = $data['baris']->first(fn ($row) => $row['peserta']->id_peserta_blok === $pesertaTerpilih)); ?>

    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">DPNA Blok</h4>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item"><a wire:navigate href="<?php echo e(route('dpna-blok.index')); ?>">DPNA Blok</a></li><li class="breadcrumb-item active"><?php echo e($blok->kode); ?></li></ol>
    </div>
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('alert', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3677541532-0', $__key);

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

    <div class="card">
        <div class="card-body d-flex flex-wrap justify-content-between gap-3">
            <div><div class="text-muted small">Blok</div><div class="fw-semibold"><?php echo e($blok->kode); ?> - <?php echo e($blok->nama); ?></div></div>
            <div><div class="text-muted small">Prodi</div><div><?php echo e($blok->prodi->nama); ?></div></div>
            <div><div class="text-muted small">Semester</div><div><?php echo e(ucfirst($blok->semester->nama)); ?> <?php echo e($blok->semester->tahun); ?></div></div>
            <div><div class="text-muted small">Peserta</div><div><?php echo e($data['peserta']->count()); ?> mahasiswa</div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-1">Konfigurasi Sumber DPNA</h5><div class="text-muted small">Sumber aktif wajib berbobot lebih dari 0 dan totalnya tepat 100%.</div></div>
        <div class="card-body">
            <form wire:submit="simpanBobot">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['totalBobot'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="alert alert-danger"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-3">
                        <thead><tr><th>Sumber</th><th class="text-center">Masuk DPNA</th><th style="width: 180px">Bobot (%)</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><span class="fw-semibold">Kehadiran</span><div class="text-muted small">Persentase hadir dari seluruh pertemuan wajib presensi.</div></td>
                                <td class="text-center"><input class="form-check-input" type="checkbox" wire:model.live="kehadiranAktif" aria-label="Aktifkan kehadiran"></td>
                                <td><input class="form-control" type="number" min="0" max="100" step="0.01" wire:model="bobotKehadiran" <?php if(!$kehadiranAktif): echo 'disabled'; endif; ?>><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['bobotKehadiran'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger small"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $data['kegiatan']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $aturan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'bobot-'.e($aturan->id).''; ?>wire:key="bobot-<?php echo e($aturan->id); ?>">
                                    <td><span class="fw-semibold"><?php echo e($aturan->jenis_kegiatan?->nama); ?></span><div class="text-muted small">Rata-rata nilai <?php echo e($aturan->materi_rinci_blok_count); ?> pertemuan per mahasiswa.</div></td>
                                    <td class="text-center"><input class="form-check-input" type="checkbox" wire:model.live="kegiatan.<?php echo e($aturan->id); ?>.aktif" aria-label="Aktifkan <?php echo e($aturan->jenis_kegiatan->nama); ?>"></td>
                                    <td><input class="form-control" type="number" min="0" max="100" step="0.01" wire:model="kegiatan.<?php echo e($aturan->id); ?>.bobot" <?php if(!($kegiatan[$aturan->id]['aktif'] ?? false)): echo 'disabled'; endif; ?>><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["kegiatan.{$aturan->id}.bobot"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger small"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i>Simpan Bobot</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-1">Matriks DPNA</h5><div class="text-muted small">Klik mahasiswa untuk melihat rincian. Nilai akhir hanya tampil jika semua sumber aktif lengkap.</div></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead><tr><th class="text-center">No</th><th>NIM</th><th>Mahasiswa</th><th class="text-center">Kehadiran</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $data['kegiatan']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $aturan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><th class="text-center"><?php echo e($aturan->jenis_kegiatan->nama); ?></th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><th class="text-center">Nilai Akhir</th></tr></thead>
                    <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $data['baris']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr role="button" wire:click="pilihPeserta(<?php echo e($row['peserta']->id_peserta_blok); ?>)" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'peserta-'.e($row['peserta']->id_peserta_blok).''; ?>wire:key="peserta-<?php echo e($row['peserta']->id_peserta_blok); ?>">
                            <td class="text-center"><?php echo e($index + 1); ?></td><td><?php echo e($row['peserta']->mahasiswa->nim); ?></td><td class="fw-semibold"><?php echo e($row['peserta']->mahasiswa->nama); ?></td>
                            <td class="text-center"><?php echo e($row['kehadiran'] === null ? 'Belum Lengkap' : number_format($row['kehadiran'], 2, ',', '.')); ?></td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $data['kegiatan']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $aturan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><td class="text-center"><?php echo e($row['nilai_kegiatan'][$aturan->id] === null ? 'Belum Lengkap' : number_format($row['nilai_kegiatan'][$aturan->id], 2, ',', '.')); ?></td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <td class="text-center fw-bold"><?php echo e($row['nilai_akhir'] === null ? 'Belum Lengkap' : number_format($row['nilai_akhir'], 2, ',', '.')); ?></td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td class="text-center text-muted py-4" colspan="<?php echo e(5 + $data['kegiatan']->count()); ?>">Belum ada peserta aktif.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($barisTerpilih): ?>
        <div class="card border-primary" id="detail-mahasiswa">
            <div class="card-header d-flex justify-content-between"><div><h5 class="mb-0">Detail <?php echo e($barisTerpilih['peserta']->mahasiswa->nama); ?></h5><span class="text-muted"><?php echo e($barisTerpilih['peserta']->mahasiswa->nim); ?></span></div><button class="btn-close" type="button" wire:click="pilihPeserta(<?php echo e($pesertaTerpilih); ?>)" aria-label="Tutup"></button></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small">Kehadiran</div><h5><?php echo e($barisTerpilih['kehadiran'] === null ? 'Belum Lengkap' : number_format($barisTerpilih['kehadiran'], 2, ',', '.').'%'); ?></h5><div class="small"><?php echo e($barisTerpilih['kehadiran_detail']['terisi']); ?> dari <?php echo e($barisTerpilih['kehadiran_detail']['wajib']); ?> presensi terisi</div></div></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $data['kegiatan']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $aturan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small"><?php echo e($aturan->jenis_kegiatan->nama); ?></div><h5><?php echo e($barisTerpilih['nilai_kegiatan'][$aturan->id] === null ? 'Belum Lengkap' : number_format($barisTerpilih['nilai_kegiatan'][$aturan->id], 2, ',', '.')); ?></h5><div class="small">Bobot <?php echo e(number_format((float) $aturan->bobot_nilai_dpna, 2, ',', '.')); ?>%</div></div></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="col-md-4"><div class="border border-primary rounded p-3 h-100"><div class="text-muted small">Nilai Akhir Blok</div><h4 class="text-primary mb-0"><?php echo e($barisTerpilih['nilai_akhir'] === null ? 'Belum Lengkap' : number_format($barisTerpilih['nilai_akhir'], 2, ',', '.')); ?></h4></div></div>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\dpna-blok\detail.blade.php ENDPATH**/ ?>