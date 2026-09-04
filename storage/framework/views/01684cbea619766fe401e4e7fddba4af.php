<?php

use App\Models\AnggotaKelompokBlok;
use App\Models\Blok;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\PesertaBlok;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $blok_id;
    public string $peserta_search = '';
    public string $kandidat_search = '';
    public array $kandidat_ids = [];
    public ?string $kandidat_kelas_id = null;

    public function mount($blok_id): void
    {
        $this->blok_id = (int) $blok_id;

        $blok = Blok::select('id')->findOrFail($this->blok_id);

        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);
    }

    public function updatedPesertaSearch(): void
    {
        $this->resetPage('pesertaPage');
    }

    public function updatedKandidatSearch(): void
    {
        $this->resetPage('kandidatPage');
    }

    /**
     * Hanya kolom yang benar-benar dipakai, blok dibaca ulang setiap request
     * agar tidak ada model besar yang ikut diserialisasi Livewire.
     */
    private function blok(): Blok
    {
        return Blok::select(['id', 'prodi_id', 'tanggal_mulai'])->findOrFail($this->blok_id);
    }

    /**
     * Pencarian dan pengurutan dilakukan di SQL, bukan pada koleksi PHP,
     * karena satu blok bisa berisi ratusan peserta.
     */
    private function pesertaQuery()
    {
        return PesertaBlok::query()
            ->select('peserta_blok.*')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
            ->where('peserta_blok.blok_id', $this->blok_id)
            ->when($this->peserta_search !== '', function ($query) {
                $search = '%'.$this->peserta_search.'%';

                $query->where(function ($inner) use ($search) {
                    $inner->where('mahasiswa.nama', 'like', $search)
                        ->orWhere('mahasiswa.nim', 'like', $search);
                });
            })
            ->with(['mahasiswa:id_mahasiswa,nim,nama,angkatan', 'kelas:id_kelas,kode,nama'])
            ->withCount('anggota_kelompok_blok')
            ->orderBy('mahasiswa.nama');
    }

    private function kandidatQuery(Blok $blok)
    {
        return Mahasiswa::query()
            ->select(['id_mahasiswa', 'nim', 'nama', 'angkatan'])
            ->where('status', 'aktif')
            ->where('prodi_id', $blok->prodi_id)
            ->whereDoesntHave('peserta_blok', fn ($query) => $query->where('blok_id', $this->blok_id))
            ->when($this->kandidat_search !== '', function ($query) {
                $search = '%'.$this->kandidat_search.'%';

                $query->where(function ($inner) use ($search) {
                    $inner->where('nama', 'like', $search)->orWhere('nim', 'like', $search);
                });
            })
            ->orderBy('nama');
    }

    /**
     * Pilih atau lepas seluruh kandidat pada halaman aktif. Id halaman dihitung
     * ulang di server supaya tidak perlu mengirim array lewat atribut wire:click.
     */
    public function togglePageKandidat(): void
    {
        $ids = $this->kandidatQuery($this->blok())
            ->paginate(10, pageName: 'kandidatPage')
            ->pluck('id_mahasiswa')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        $selected = array_map('strval', $this->kandidat_ids);

        $this->kandidat_ids = empty(array_diff($ids, $selected))
            ? array_values(array_diff($selected, $ids))
            : array_values(array_unique([...$selected, ...$ids]));
    }

    /**
     * Kapasitas rombel ditegakkan di sini juga, bukan hanya saat rombel diedit.
     * Kapasitas yang tidak ditegakkan lebih menyesatkan daripada tidak ada kapasitas.
     */
    private function pelanggaranKapasitasRombel(?string $kelasId, int $tambahan): ?string
    {
        if (! $kelasId || $tambahan < 1) {
            return null;
        }

        $rombel = Kelas::where('blok_id', $this->blok_id)
            ->withCount('peserta_blok')
            ->find($kelasId);

        if (! $rombel || ! $rombel->kapasitas) {
            return null;
        }

        $total = $rombel->peserta_blok_count + $tambahan;

        if ($total <= (int) $rombel->kapasitas) {
            return null;
        }

        return 'Rombel '.$rombel->kode.' hanya menampung '.$rombel->kapasitas.' peserta, sedangkan isinya akan menjadi '.$total.'.';
    }

    public function addPeserta(): void
    {
        $this->validate([
            'kandidat_ids' => ['required', 'array', 'min:1'],
            'kandidat_ids.*' => ['integer', 'exists:mahasiswa,id_mahasiswa'],
            'kandidat_kelas_id' => ['nullable', Rule::exists('kelas', 'id_kelas')->where('blok_id', $this->blok_id)],
        ], [
            'kandidat_ids.required' => 'Pilih minimal satu mahasiswa.',
            'kandidat_kelas_id.exists' => 'Rombel tidak valid untuk blok ini.',
        ]);

        $blok = $this->blok();
        $ids = collect($this->kandidat_ids)->map(fn ($id) => (int) $id)->unique()->values();

        $valid = Mahasiswa::whereIn('id_mahasiswa', $ids)
            ->where('status', 'aktif')
            ->where('prodi_id', $blok->prodi_id)
            ->pluck('id_mahasiswa');

        if ($valid->count() !== $ids->count()) {
            $this->addError('kandidat_ids', 'Peserta harus mahasiswa aktif dari program studi yang sama dengan blok.');

            return;
        }

        $pelanggaran = $this->pelanggaranKapasitasRombel($this->kandidat_kelas_id, $valid->count());

        if ($pelanggaran !== null) {
            $this->addError('kandidat_kelas_id', $pelanggaran);

            return;
        }

        DB::transaction(function () use ($valid, $blok) {
            foreach ($valid as $mahasiswaId) {
                // Baris yang pernah dihapus lembut tetap menempati unique index,
                // jadi dipulihkan alih-alih dibuat ulang.
                $peserta = PesertaBlok::withTrashed()->firstOrNew([
                    'blok_id' => $this->blok_id,
                    'mahasiswa_id' => $mahasiswaId,
                ]);

                $peserta->fill([
                    'kelas_id' => $this->kandidat_kelas_id ?: null,
                    'status' => 'aktif',
                    'tanggal_masuk' => $peserta->tanggal_masuk ?: ($blok->tanggal_mulai?->toDateString() ?: now()->toDateString()),
                ]);

                if ($peserta->trashed()) {
                    $peserta->restore();
                }

                $peserta->save();
            }
        });

        $jumlah = $valid->count();
        $this->kandidat_ids = [];
        $this->resetPage('kandidatPage');

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $jumlah.' peserta berhasil ditambahkan.',
        ]);
    }

    public function setRombel(string $id, ?string $kelasId): void
    {
        $peserta = PesertaBlok::where('blok_id', $this->blok_id)->findOrFail($id);

        if ($kelasId && ! Kelas::where('blok_id', $this->blok_id)->where('id_kelas', $kelasId)->exists()) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Rombel tidak valid untuk blok ini.',
            ]);

            return;
        }

        $pindah = (int) $peserta->kelas_id !== (int) $kelasId;
        $pelanggaran = $pindah ? $this->pelanggaranKapasitasRombel($kelasId ?: null, 1) : null;

        if ($pelanggaran !== null) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => $pelanggaran,
            ]);

            return;
        }

        DB::transaction(function () use ($peserta, $kelasId) {
            $peserta->update(['kelas_id' => $kelasId ?: null]);

            // Kelompok yang dibatasi ke rombel lain jadi tidak valid untuk peserta ini.
            AnggotaKelompokBlok::where('peserta_blok_id', $peserta->id_peserta_blok)
                ->whereHas('kelompok_blok', function ($query) use ($kelasId) {
                    $query->whereNotNull('kelas_id')
                        ->when($kelasId, fn ($inner) => $inner->where('kelas_id', '!=', $kelasId));
                })
                ->delete();
        });

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Rombel peserta diperbarui.',
        ]);
    }

    public function deletePeserta(string $id): void
    {
        $peserta = PesertaBlok::where('blok_id', $this->blok_id)->findOrFail($id);

        DB::transaction(function () use ($peserta) {
            AnggotaKelompokBlok::where('peserta_blok_id', $peserta->id_peserta_blok)->delete();
            $peserta->delete();
        });

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Peserta dikeluarkan dari blok.',
        ]);
    }

    public function render()
    {
        $blok = $this->blok();

        return $this->view([
            'peserta' => $this->pesertaQuery()->paginate(10, pageName: 'pesertaPage'),
            'kandidat' => $this->kandidatQuery($blok)->paginate(10, pageName: 'kandidatPage'),
            'rombelOptions' => Kelas::where('blok_id', $this->blok_id)
                ->orderBy('kode')
                ->get(['id_kelas', 'kode', 'nama']),
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
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Tambah Peserta</h5>
                <span class="badge bg-primary-subtle text-primary"><?php echo e(count($kandidat_ids)); ?> dipilih</span>
            </div>
            <div class="card-body">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kandidat_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kandidat_kelas_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Cari Mahasiswa</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ri-search-line"></i></span>
                        <input type="text" class="form-control" placeholder="Nama atau NIM" wire:model.live.debounce.400ms="kandidat_search">
                    </div>
                    <div class="form-text">Hanya mahasiswa aktif pada program studi blok yang belum menjadi peserta.</div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rombelOptions->isNotEmpty()): ?>
                    <div class="mb-3">
                        <label class="form-label">Masukkan ke Rombel</label>
                        <select class="form-select" wire:model="kandidat_kelas_id">
                            <option value="">Tanpa rombel</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rombelOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rombel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($rombel->id_kelas); ?>"><?php echo e($rombel->kode); ?><?php echo e($rombel->nama ? ' - '.$rombel->nama : ''); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php ($pageIds = $kandidat->pluck('id_mahasiswa')->map(fn ($id) => (string) $id)->all()); ?>
                <?php ($pageAllSelected = $pageIds !== [] && empty(array_diff($pageIds, array_map('strval', $kandidat_ids)))); ?>

                <div class="border rounded">
                    <div class="form-check border-bottom p-3 ps-5 mb-0">
                        <input class="form-check-input" type="checkbox" id="kandidat-page-all"
                            wire:click="togglePageKandidat"
                            <?php if($pageAllSelected): echo 'checked'; endif; ?> <?php if($pageIds === []): echo 'disabled'; endif; ?>>
                        <label class="form-check-label fw-semibold" for="kandidat-page-all">Pilih semua di halaman ini</label>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $kandidat; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="form-check border-bottom p-3 ps-5 mb-0">
                            <input class="form-check-input" type="checkbox" value="<?php echo e($item->id_mahasiswa); ?>" wire:model.live="kandidat_ids" id="kandidat-<?php echo e($item->id_mahasiswa); ?>">
                            <label class="form-check-label w-100" for="kandidat-<?php echo e($item->id_mahasiswa); ?>">
                                <span class="fw-semibold"><?php echo e($item->nama); ?></span>
                                <span class="text-muted d-block small"><?php echo e($item->nim); ?> &middot; angkatan <?php echo e($item->angkatan); ?></span>
                            </label>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="text-muted small p-3">
                            <?php echo e($kandidat_search ? 'Mahasiswa tidak ditemukan.' : 'Semua mahasiswa aktif prodi ini sudah menjadi peserta blok.'); ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($kandidat->hasPages()): ?>
                    <div class="mt-3"><?php echo e($kandidat->links()); ?></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <button type="button" class="btn btn-primary mt-3" wire:click="addPeserta" wire:loading.attr="disabled" wire:target="addPeserta" <?php if(count($kandidat_ids) === 0): echo 'disabled'; endif; ?>>
                    <span wire:loading.remove wire:target="addPeserta"><i class="ri-user-add-line"></i> Tambah <?php echo e(count($kandidat_ids) ?: ''); ?> Peserta</span>
                    <span wire:loading wire:target="addPeserta">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Daftar Peserta Blok</h5>
                <span class="badge bg-info-subtle text-info"><?php echo e($peserta->total()); ?> peserta</span>
            </div>
            <div class="card-body">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="ri-search-line"></i></span>
                    <input type="text" class="form-control" placeholder="Cari nama atau NIM peserta" wire:model.live.debounce.400ms="peserta_search">
                </div>

                <div class="table-responsive">
                    <table class="table table-nowrap align-middle">
                        <thead>
                            <tr>
                                <th>Mahasiswa</th>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rombelOptions->isNotEmpty()): ?>
                                    <th>Rombel</th>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <th>Kelompok</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $peserta; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'peserta-'.e($item->id_peserta_blok).''; ?>wire:key="peserta-<?php echo e($item->id_peserta_blok); ?>">
                                    <td>
                                        <span class="fw-semibold"><?php echo e($item->mahasiswa?->nama); ?></span>
                                        <span class="text-muted d-block small"><?php echo e($item->mahasiswa?->nim); ?></span>
                                    </td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rombelOptions->isNotEmpty()): ?>
                                        <td>
                                            <select class="form-select form-select-sm" wire:change="setRombel('<?php echo e($item->id_peserta_blok); ?>', $event.target.value)">
                                                <option value="" <?php if(! $item->kelas_id): echo 'selected'; endif; ?>>Tanpa rombel</option>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rombelOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rombel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <option value="<?php echo e($rombel->id_kelas); ?>" <?php if((int) $item->kelas_id === (int) $rombel->id_kelas): echo 'selected'; endif; ?>><?php echo e($rombel->kode); ?></option>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </select>
                                        </td>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo e($item->anggota_kelompok_blok_count); ?> kelompok</span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-danger btn-sm"
                                            wire:click="deletePeserta('<?php echo e($item->id_peserta_blok); ?>')"
                                            wire:confirm="Keluarkan peserta ini dari blok? Keanggotaan kelompoknya juga akan dihapus.">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr>
                                    <td colspan="<?php echo e($rombelOptions->isNotEmpty() ? 4 : 3); ?>" class="text-muted">
                                        <?php echo e($peserta_search ? 'Peserta tidak ditemukan.' : 'Belum ada peserta pada blok ini.'); ?>

                                    </td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($peserta->hasPages()): ?>
                    <?php echo e($peserta->links()); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\blok-operasional\peserta.blade.php ENDPATH**/ ?>