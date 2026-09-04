<?php

use App\Models\AnggotaKelompokBlok;
use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\Kelas;
use App\Models\KelompokBlok;
use App\Models\PesertaBlok;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public int $blok_id;
    public $aturan_kegiatan_blok_id;

    public $edit_id;
    public string $kode = '';
    public string $nama = '';
    public $kapasitas;
    public ?string $kelas_id = null;
    public string $status = 'aktif';
    public array $anggota_ids = [];
    public string $anggota_search = '';

    // Sengaja tanpa tipe int: input number yang dikosongkan mengirim string kosong,
    // biarkan validasi yang menolaknya alih-alih memicu TypeError saat assignment.
    public $gen_jumlah = 2;
    public string $gen_prefix = '';
    public ?string $gen_kelas_id = null;

    public function mount($blok_id): void
    {
        $this->blok_id = (int) $blok_id;

        $blok = Blok::select('id')->findOrFail($this->blok_id);

        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        $this->aturan_kegiatan_blok_id = $this->aturanList()->first()?->id;
        $this->syncGenPrefix();
    }

    public function pilihKegiatan(string $id): void
    {
        $aturan = $this->aturanList()->firstWhere('id', (int) $id);

        if (! $aturan) {
            return;
        }

        $this->aturan_kegiatan_blok_id = $aturan->id;
        $this->resetForm();
        $this->syncGenPrefix();
    }

    public function updatedAnggotaSearch(): void
    {
        $this->resetPage('anggotaPage');
    }

    /**
     * Saat kelompok dibatasi ke satu rombel, anggota di luar rombel itu tidak lagi sah.
     */
    public function updatedKelasId(): void
    {
        $this->resetPage('anggotaPage');

        if (! $this->kelas_id || $this->anggota_ids === []) {
            return;
        }

        $this->anggota_ids = PesertaBlok::where('blok_id', $this->blok_id)
            ->whereIn('id_peserta_blok', $this->anggota_ids)
            ->where('kelas_id', $this->kelas_id)
            ->pluck('id_peserta_blok')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function aturanList()
    {
        return AturanKegiatanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->with('jenis_kegiatan:id,kode,nama')
            ->withCount(['kelompok_blok', 'materi_rinci_blok'])
            ->orderBy('urutan')
            ->get(['id', 'blok_id', 'jenis_kegiatan_id', 'durasi_menit', 'jumlah_mahasiswa_per_kelompok', 'urutan']);
    }

    public function resetForm(): void
    {
        $this->reset(['edit_id', 'kode', 'nama', 'kapasitas', 'kelas_id', 'anggota_ids', 'anggota_search']);
        $this->status = 'aktif';
        $this->resetErrorBag();
        $this->resetPage('anggotaPage');
    }

    private function syncGenPrefix(): void
    {
        $aturan = $this->aturanList()->firstWhere('id', (int) $this->aturan_kegiatan_blok_id);
        $this->gen_prefix = $aturan?->jenis_kegiatan?->kode ?: 'K';
        $this->gen_jumlah = 2;
        $this->gen_kelas_id = null;
    }

    private function aturanTerpilih(): AturanKegiatanBlok
    {
        return AturanKegiatanBlok::where('blok_id', $this->blok_id)->findOrFail($this->aturan_kegiatan_blok_id);
    }

    /**
     * Peserta yang sudah masuk kelompok lain pada kegiatan yang sama.
     * Satu query, hasilnya sebatas jumlah peserta blok.
     *
     * @return array<int, int>
     */
    public function pesertaTerpakaiIds(): array
    {
        if (! $this->aturan_kegiatan_blok_id) {
            return [];
        }

        return AnggotaKelompokBlok::query()
            ->whereHas('kelompok_blok', function ($query) {
                $query->where('blok_id', $this->blok_id)
                    ->where('aturan_kegiatan_blok_id', $this->aturan_kegiatan_blok_id)
                    ->when($this->edit_id, fn ($inner) => $inner->where('id_kelompok_blok', '!=', $this->edit_id));
            })
            ->pluck('peserta_blok_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function anggotaQuery()
    {
        return PesertaBlok::query()
            ->select('peserta_blok.*')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
            ->where('peserta_blok.blok_id', $this->blok_id)
            ->where('peserta_blok.status', 'aktif')
            ->when($this->kelas_id, fn ($query) => $query->where('peserta_blok.kelas_id', $this->kelas_id))
            ->when($this->anggota_search !== '', function ($query) {
                $search = '%'.$this->anggota_search.'%';

                $query->where(function ($inner) use ($search) {
                    $inner->where('mahasiswa.nama', 'like', $search)->orWhere('mahasiswa.nim', 'like', $search);
                });
            })
            ->with(['mahasiswa:id_mahasiswa,nim,nama', 'kelas:id_kelas,kode'])
            ->orderBy('mahasiswa.nama');
    }

    public function togglePageAnggota(): void
    {
        $terpakai = $this->pesertaTerpakaiIds();

        $ids = $this->anggotaQuery()
            ->paginate(10, pageName: 'anggotaPage')
            ->reject(fn ($peserta) => in_array((int) $peserta->id_peserta_blok, $terpakai, true))
            ->pluck('id_peserta_blok')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        $selected = array_map('strval', $this->anggota_ids);

        $this->anggota_ids = empty(array_diff($ids, $selected))
            ? array_values(array_diff($selected, $ids))
            : array_values(array_unique([...$selected, ...$ids]));
    }

    public function edit(string $id): void
    {
        $kelompok = KelompokBlok::where('blok_id', $this->blok_id)->findOrFail($id);

        $this->edit_id = $kelompok->id_kelompok_blok;
        $this->aturan_kegiatan_blok_id = $kelompok->aturan_kegiatan_blok_id;
        $this->kode = (string) $kelompok->kode;
        $this->nama = (string) $kelompok->nama;
        $this->kapasitas = $kelompok->kapasitas;
        $this->kelas_id = $kelompok->kelas_id ? (string) $kelompok->kelas_id : null;
        $this->status = $kelompok->status;
        $this->anggota_ids = $kelompok->anggota_kelompok_blok()
            ->pluck('peserta_blok_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->resetErrorBag();
        $this->resetPage('anggotaPage');
    }

    public function save(): void
    {
        $payload = $this->validate([
            'aturan_kegiatan_blok_id' => ['required', Rule::exists('aturan_kegiatan_blok', 'id')->where('blok_id', $this->blok_id)],
            'kode' => [
                'required',
                'string',
                'max:255',
                Rule::unique('kelompok_blok', 'kode')
                    ->where('blok_id', $this->blok_id)
                    ->where('aturan_kegiatan_blok_id', $this->aturan_kegiatan_blok_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->edit_id, 'id_kelompok_blok'),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'kapasitas' => ['nullable', 'integer', 'min:1', 'max:999'],
            'kelas_id' => ['nullable', Rule::exists('kelas', 'id_kelas')->where('blok_id', $this->blok_id)],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'anggota_ids' => ['array'],
            'anggota_ids.*' => ['integer'],
        ], [
            'aturan_kegiatan_blok_id.required' => 'Jenis kegiatan wajib dipilih.',
            'aturan_kegiatan_blok_id.exists' => 'Jenis kegiatan harus berasal dari blok ini.',
            'kode.required' => 'Kode kelompok wajib diisi.',
            'kode.unique' => 'Kode kelompok sudah dipakai pada jenis kegiatan ini.',
            'nama.required' => 'Nama kelompok wajib diisi.',
            'kelas_id.exists' => 'Rombel tidak valid untuk blok ini.',
        ]);

        $anggotaIds = collect($payload['anggota_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        if ($payload['kapasitas'] && $anggotaIds->count() > (int) $payload['kapasitas']) {
            $this->addError('anggota_ids', 'Jumlah anggota tidak boleh melebihi kapasitas kelompok.');

            return;
        }

        if ($anggotaIds->isNotEmpty()) {
            $milikBlok = PesertaBlok::where('blok_id', $this->blok_id)
                ->whereIn('id_peserta_blok', $anggotaIds)
                ->count();

            if ($milikBlok !== $anggotaIds->count()) {
                $this->addError('anggota_ids', 'Anggota harus berasal dari peserta blok ini.');

                return;
            }

            if ($payload['kelas_id']) {
                $luarRombel = PesertaBlok::where('blok_id', $this->blok_id)
                    ->whereIn('id_peserta_blok', $anggotaIds)
                    ->where(fn ($query) => $query->whereNull('kelas_id')->orWhere('kelas_id', '!=', $payload['kelas_id']))
                    ->exists();

                if ($luarRombel) {
                    $this->addError('anggota_ids', 'Kelompok ini dibatasi ke satu rombel, jadi seluruh anggota harus berasal dari rombel tersebut.');

                    return;
                }
            }

            $sudahBerkelompok = AnggotaKelompokBlok::query()
                ->whereIn('peserta_blok_id', $anggotaIds)
                ->whereHas('kelompok_blok', function ($query) {
                    $query->where('blok_id', $this->blok_id)
                        ->where('aturan_kegiatan_blok_id', $this->aturan_kegiatan_blok_id)
                        ->when($this->edit_id, fn ($inner) => $inner->where('id_kelompok_blok', '!=', $this->edit_id));
                })
                ->exists();

            if ($sudahBerkelompok) {
                $this->addError('anggota_ids', 'Mahasiswa hanya boleh masuk satu kelompok pada jenis kegiatan yang sama.');

                return;
            }
        }

        DB::transaction(function () use ($payload, $anggotaIds) {
            // Unique index (blok, kegiatan, kode) tetap ditempati baris terhapus lembut,
            // jadi baris lama dengan kode sama dipulihkan alih-alih dibuat ulang.
            $kelompok = $this->edit_id
                ? KelompokBlok::where('blok_id', $this->blok_id)->findOrFail($this->edit_id)
                : KelompokBlok::withTrashed()->firstOrNew([
                    'blok_id' => $this->blok_id,
                    'aturan_kegiatan_blok_id' => $payload['aturan_kegiatan_blok_id'],
                    'kode' => $payload['kode'],
                ]);

            $kelompok->fill([
                'blok_id' => $this->blok_id,
                'aturan_kegiatan_blok_id' => $payload['aturan_kegiatan_blok_id'],
                'kelas_id' => $payload['kelas_id'] ?: null,
                'kode' => $payload['kode'],
                'nama' => $payload['nama'],
                'kapasitas' => $payload['kapasitas'] ?: null,
                'status' => $payload['status'],
            ]);

            if ($kelompok->trashed()) {
                $kelompok->restore();
            }

            $kelompok->save();

            $kelompok->anggota_kelompok_blok()
                ->when($anggotaIds->isNotEmpty(), fn ($query) => $query->whereNotIn('peserta_blok_id', $anggotaIds->all()))
                ->delete();

            foreach ($anggotaIds as $pesertaId) {
                $kelompok->anggota_kelompok_blok()->updateOrCreate(
                    ['peserta_blok_id' => $pesertaId]
                );
            }
        });

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $this->edit_id ? 'Kelompok berhasil diubah.' : 'Kelompok berhasil ditambahkan.',
        ]);

        $this->resetForm();
    }

    public function delete(string $id): void
    {
        $kelompok = KelompokBlok::where('blok_id', $this->blok_id)->findOrFail($id);

        DB::transaction(fn () => $this->hapusKelompok($kelompok));

        if ((int) $this->edit_id === (int) $id) {
            $this->resetForm();
        }

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Kelompok berhasil dihapus.',
        ]);
    }

    /**
     * Bagi peserta aktif secara merata ke sejumlah kelompok baru.
     * Kelompok lama pada kegiatan (dan lingkup rombel) yang sama diganti.
     */
    public function generateKelompok(): void
    {
        $payload = $this->validate([
            'aturan_kegiatan_blok_id' => ['required', Rule::exists('aturan_kegiatan_blok', 'id')->where('blok_id', $this->blok_id)],
            'gen_jumlah' => ['required', 'integer', 'min:1', 'max:50'],
            'gen_prefix' => ['required', 'string', 'max:20'],
            'gen_kelas_id' => ['nullable', Rule::exists('kelas', 'id_kelas')->where('blok_id', $this->blok_id)],
        ], [
            'gen_jumlah.required' => 'Jumlah kelompok wajib diisi.',
            'gen_jumlah.max' => 'Jumlah kelompok maksimal 50.',
            'gen_prefix.required' => 'Awalan kode kelompok wajib diisi.',
            'gen_kelas_id.exists' => 'Rombel tidak valid untuk blok ini.',
        ]);

        $pesertaIds = PesertaBlok::query()
            ->select('peserta_blok.id_peserta_blok')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
            ->where('peserta_blok.blok_id', $this->blok_id)
            ->where('peserta_blok.status', 'aktif')
            ->when($payload['gen_kelas_id'], fn ($query) => $query->where('peserta_blok.kelas_id', $payload['gen_kelas_id']))
            ->orderBy('mahasiswa.nama')
            ->pluck('peserta_blok.id_peserta_blok');

        if ($pesertaIds->isEmpty()) {
            $this->addError('gen_jumlah', 'Belum ada peserta aktif yang bisa dibagi. Tambahkan peserta terlebih dahulu.');

            return;
        }

        $jumlah = (int) $payload['gen_jumlah'];
        $prefix = trim($payload['gen_prefix']);

        DB::transaction(function () use ($payload, $pesertaIds, $jumlah, $prefix) {
            KelompokBlok::query()
                ->where('blok_id', $this->blok_id)
                ->where('aturan_kegiatan_blok_id', $payload['aturan_kegiatan_blok_id'])
                ->when(
                    $payload['gen_kelas_id'],
                    fn ($query) => $query->where('kelas_id', $payload['gen_kelas_id']),
                    fn ($query) => $query->whereNull('kelas_id'),
                )
                ->get()
                ->each(fn (KelompokBlok $kelompok) => $this->hapusKelompok($kelompok));

            for ($index = 0; $index < $jumlah; $index++) {
                $kode = $prefix.($index + 1);

                $kelompok = KelompokBlok::withTrashed()->firstOrNew([
                    'blok_id' => $this->blok_id,
                    'aturan_kegiatan_blok_id' => $payload['aturan_kegiatan_blok_id'],
                    'kode' => $kode,
                ]);

                $kelompok->fill([
                    'blok_id' => $this->blok_id,
                    'aturan_kegiatan_blok_id' => $payload['aturan_kegiatan_blok_id'],
                    'kelas_id' => $payload['gen_kelas_id'] ?: null,
                    'kode' => $kode,
                    'nama' => 'Kelompok '.$kode,
                    'kapasitas' => null,
                    'status' => 'aktif',
                ]);

                if ($kelompok->trashed()) {
                    $kelompok->restore();
                }

                $kelompok->save();

                $anggota = $pesertaIds->filter(fn ($id, $position) => $position % $jumlah === $index);

                foreach ($anggota as $pesertaId) {
                    $kelompok->anggota_kelompok_blok()->updateOrCreate(
                        ['peserta_blok_id' => $pesertaId]
                    );
                }
            }
        });

        $this->resetForm();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $jumlah.' kelompok dibuat dan '.$pesertaIds->count().' peserta dibagi merata.',
        ]);
    }

    /**
     * Kelompok dihapus lembut, keanggotaan dihapus permanen, dan pertemuannya
     * ikut dihapus lembut supaya tidak ada pertemuan menggantung tanpa kelompok.
     */
    private function hapusKelompok(KelompokBlok $kelompok): void
    {
        $kelompok->anggota_kelompok_blok()->delete();
        $kelompok->pertemuan_blok()->delete();
        $kelompok->delete();
    }

    public function render()
    {
        $aturanList = $this->aturanList();

        return $this->view([
            'aturanList' => $aturanList,
            'aturanAktif' => $aturanList->firstWhere('id', (int) $this->aturan_kegiatan_blok_id),
            'anggotaPage' => $this->anggotaQuery()->paginate(10, pageName: 'anggotaPage'),
            'pesertaTerpakai' => $this->pesertaTerpakaiIds(),
            'kelompokList' => $this->aturan_kegiatan_blok_id
                ? KelompokBlok::query()
                    ->where('blok_id', $this->blok_id)
                    ->where('aturan_kegiatan_blok_id', $this->aturan_kegiatan_blok_id)
                    ->with([
                        'kelas:id_kelas,kode',
                        'anggota_kelompok_blok.peserta_blok.mahasiswa:id_mahasiswa,nim,nama',
                    ])
                    ->withCount('anggota_kelompok_blok')
                    ->orderBy('kode')
                    ->get()
                : collect(),
            'rombelOptions' => Kelas::where('blok_id', $this->blok_id)->orderBy('kode')->get(['id_kelas', 'kode', 'nama']),
            'pesertaAktifCount' => PesertaBlok::where('blok_id', $this->blok_id)->where('status', 'aktif')->count(),
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
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($aturanList->isEmpty()): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-error-warning-line"></i>
            Blok ini belum punya jenis kegiatan. Susun kegiatan terlebih dahulu di menu Blok.
        </div>
    <?php else: ?>
        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label">Jenis Kegiatan</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $aturanList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $aturan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <button type="button"
                            class="btn btn-sm <?php echo e((int) $aturan_kegiatan_blok_id === (int) $aturan->id ? 'btn-primary' : 'btn-outline-primary'); ?>"
                            wire:click="pilihKegiatan('<?php echo e($aturan->id); ?>')">
                            <?php echo e($aturan->jenis_kegiatan?->nama); ?>

                            <span class="badge bg-light text-dark border ms-1"><?php echo e($aturan->kelompok_blok_count); ?></span>
                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($aturanAktif): ?>
                    <div class="text-muted small mt-2">
                        <?php echo e($aturanAktif->materi_rinci_blok_count); ?> pertemuan per kelompok &times; <?php echo e($aturanAktif->durasi_menit); ?> menit
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($aturanAktif->jumlah_mahasiswa_per_kelompok): ?>
                            &middot; standar <?php echo e($aturanAktif->jumlah_mahasiswa_per_kelompok); ?> mahasiswa per kelompok
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        &middot; <?php echo e($pesertaAktifCount); ?> peserta aktif di blok
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Bagi Otomatis</h5></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Jumlah Kelompok</label>
                        <input type="number" min="1" max="50" class="form-control" wire:model="gen_jumlah">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['gen_jumlah'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Awalan Kode</label>
                        <input type="text" class="form-control" wire:model="gen_prefix" placeholder="P">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['gen_prefix'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rombelOptions->isNotEmpty()): ?>
                        <div class="col-md-3">
                            <label class="form-label">Lingkup Rombel</label>
                            <select class="form-select" wire:model="gen_kelas_id">
                                <option value="">Tanpa rombel</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rombelOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rombel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <option value="<?php echo e($rombel->id_kelas); ?>"><?php echo e($rombel->kode); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['gen_kelas_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-soft-primary w-100"
                            wire:click="generateKelompok"
                            wire:loading.attr="disabled"
                            wire:target="generateKelompok"
                            wire:confirm="Kelompok yang sudah ada pada jenis kegiatan dan lingkup rombel ini akan diganti, termasuk pertemuan yang sudah dibuat untuknya. Lanjutkan?">
                            <span wire:loading.remove wire:target="generateKelompok"><i class="ri-magic-line"></i> Bagi Merata</span>
                            <span wire:loading wire:target="generateKelompok">Membagi...</span>
                        </button>
                    </div>
                </div>
                <div class="text-muted small mt-2">
                    Contoh: Kuliah Pakar 2 kelompok, Praktikum 4 kelompok. Peserta aktif dibagi merata mengikuti urutan nama.
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-5">
                <form wire:submit="save" class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo e($edit_id ? 'Edit Kelompok' : 'Tambah Kelompok'); ?></h5>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit_id): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="resetForm">Batal</button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['aturan_kegiatan_blok_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Kode</label>
                                <input type="text" class="form-control" wire:model.live.debounce.500ms="kode">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" class="form-control" wire:model.live.debounce.500ms="nama">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['nama'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="col-md-3 mb-3">
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
                        </div>

                        <div class="row">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rombelOptions->isNotEmpty()): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Rombel</label>
                                    <select class="form-select" wire:model.live="kelas_id">
                                        <option value="">Semua peserta blok</option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rombelOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rombel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <option value="<?php echo e($rombel->id_kelas); ?>"><?php echo e($rombel->kode); ?></option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kelas_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" wire:model="status">
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <label class="form-label mb-0">Anggota</label>
                                <span class="badge bg-primary-subtle text-primary"><?php echo e(count($anggota_ids)); ?> dipilih</span>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['anggota_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input type="text" class="form-control" placeholder="Cari nama atau NIM" wire:model.live.debounce.400ms="anggota_search">
                            </div>

                            <div class="border rounded">
                                <div class="form-check border-bottom p-3 ps-5 mb-0">
                                    <input class="form-check-input" type="checkbox" id="anggota-page-all" wire:click="togglePageAnggota">
                                    <label class="form-check-label fw-semibold" for="anggota-page-all">Pilih semua di halaman ini</label>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $anggotaPage; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $peserta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php ($terpakai = in_array((int) $peserta->id_peserta_blok, $pesertaTerpakai, true)); ?>
                                    <div class="form-check border-bottom p-3 ps-5 mb-0"
                                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'anggota-'.e($edit_id ?: 'baru').'-'.e($peserta->id_peserta_blok).''; ?>wire:key="anggota-<?php echo e($edit_id ?: 'baru'); ?>-<?php echo e($peserta->id_peserta_blok); ?>">
                                        <input class="form-check-input" type="checkbox" value="<?php echo e($peserta->id_peserta_blok); ?>" wire:model="anggota_ids" id="anggota-<?php echo e($peserta->id_peserta_blok); ?>" <?php if($terpakai): echo 'disabled'; endif; ?>>
                                        <label class="form-check-label w-100" for="anggota-<?php echo e($peserta->id_peserta_blok); ?>">
                                            <span class="fw-semibold"><?php echo e($peserta->mahasiswa?->nama); ?></span>
                                            <span class="text-muted d-block small">
                                                <?php echo e($peserta->mahasiswa?->nim); ?>

                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($peserta->kelas): ?>
                                                    &middot; <?php echo e($peserta->kelas->kode); ?>

                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($terpakai): ?>
                                                    &middot; sudah masuk kelompok lain pada kegiatan ini
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </span>
                                        </label>
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    <div class="text-muted small p-3">
                                        <?php echo e($anggota_search ? 'Peserta tidak ditemukan.' : 'Belum ada peserta aktif pada lingkup ini.'); ?>

                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($anggotaPage->hasPages()): ?>
                                <div class="mt-2"><?php echo e($anggotaPage->links()); ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <i class="ri-save-line"></i> SIMPAN
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-xl-7">
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="mb-0">Kelompok <?php echo e($aturanAktif?->jenis_kegiatan?->nama); ?></h5>
                        <span class="badge bg-info-subtle text-info"><?php echo e($kelompokList->count()); ?> kelompok</span>
                    </div>
                    <div class="card-body">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $kelompokList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kelompok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="border rounded p-3 mb-3" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'kelompok-'.e($kelompok->id_kelompok_blok).''; ?>wire:key="kelompok-<?php echo e($kelompok->id_kelompok_blok); ?>">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold">
                                            <?php echo e($kelompok->kode); ?> - <?php echo e($kelompok->nama); ?>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($kelompok->status !== 'aktif'): ?>
                                                <span class="badge bg-secondary">Nonaktif</span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo e($kelompok->anggota_kelompok_blok_count); ?> anggota<?php echo e($kelompok->kapasitas ? ' / '.$kelompok->kapasitas : ''); ?>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($kelompok->kelas): ?>
                                                &middot; rombel <?php echo e($kelompok->kelas->kode); ?>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button type="button" class="btn btn-info btn-sm" wire:click="edit('<?php echo e($kelompok->id_kelompok_blok); ?>')">
                                            <i class="ri-file-edit-line"></i> Kelola
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm"
                                            wire:click="delete('<?php echo e($kelompok->id_kelompok_blok); ?>')"
                                            wire:confirm="Hapus kelompok ini? Pertemuan yang sudah dibuat untuk kelompok ini juga akan dihapus.">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($kelompok->anggota_kelompok_blok->isNotEmpty()): ?>
                                    <div class="mt-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $kelompok->anggota_kelompok_blok; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $anggota): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <span class="badge bg-light text-dark border me-1 mb-1">
                                                <?php echo e($anggota->peserta_blok?->mahasiswa?->nama); ?>

                                            </span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <div class="text-muted">
                                Belum ada kelompok pada jenis kegiatan ini. Gunakan <span class="fw-semibold">Bagi Merata</span> untuk membuat beberapa kelompok sekaligus.
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\components\blok-operasional\kelompok.blade.php ENDPATH**/ ?>