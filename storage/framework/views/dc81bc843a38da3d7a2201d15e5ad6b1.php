<?php

use App\Models\JenisKegiatan;
use App\Models\KomponenPenilaian;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $edit_id;

    public $kode;

    public $nama;

    public $jumlah_pertemuan_default = 1;

    public $durasi_menit_default = 100;

    public bool $pakai_cbt = false;

    public $deskripsi;

    public $status = 'aktif';

    /**
     * Standar komponen penilaian jenis kegiatan ini. Setiap baris:
     * id, nama, nilai_min, nilai_maks, urutan, status.
     *
     * @var array<int, array<string, mixed>>
     */
    public $standar = [];

    public function mount($id): void
    {
        if ($id && $id !== 'add') {
            try {
                $this->edit_id = Crypt::decrypt($id);
            } catch (DecryptException $e) {
                abort(404, 'Enkripsi tidak valid !');
            }

            $jenis = JenisKegiatan::with([
                'komponen_penilaian' => fn ($query) => $query
                    ->withCount([
                        'komponen_penilaian_blok as pernah_digunakan' => fn ($mapping) => $mapping->withTrashed(),
                    ])
                    ->orderBy('urutan'),
            ])->findOrFail($this->edit_id);

            $this->kode = $jenis->kode;
            $this->nama = $jenis->nama;
            $this->jumlah_pertemuan_default = $jenis->jumlah_pertemuan_default;
            $this->durasi_menit_default = $jenis->durasi_menit_default;
            $this->pakai_cbt = $jenis->pakai_cbt;
            $this->deskripsi = $jenis->deskripsi;
            $this->status = $jenis->status;

            $this->standar = $jenis->komponen_penilaian
                ->map(fn (KomponenPenilaian $item) => [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'nilai_min' => $item->nilai_min_default,
                    'nilai_maks' => $item->nilai_maks_default,
                    'urutan' => $item->urutan,
                    'status' => $item->status,
                    'pernah_digunakan' => (bool) $item->pernah_digunakan,
                ])
                ->values()
                ->toArray();
        }
    }

    public function updatedPakaiCbt(bool $pakaiCbt): void
    {
        if (! $pakaiCbt) {
            return;
        }

        $index = collect($this->standar)->search(
            fn ($baris) => Str::lower(trim($baris['nama'] ?? '')) === 'nilai'
        );

        if ($index !== false) {
            $this->standar[$index]['nilai_min'] = 1;
            $this->standar[$index]['nilai_maks'] = 100;
            $this->standar[$index]['status'] = 'aktif';

            return;
        }

        $this->standar[] = [
            'id' => null,
            'nama' => 'Nilai',
            'nilai_min' => 1,
            'nilai_maks' => 100,
            'urutan' => count($this->standar) + 1,
            'status' => 'aktif',
            'pernah_digunakan' => false,
        ];
    }

    public function addStandar(): void
    {
        $this->standar[] = [
            'id' => null,
            'nama' => '',
            'nilai_min' => 0,
            'nilai_maks' => 100,
            'urutan' => count($this->standar) + 1,
            'status' => 'aktif',
            'pernah_digunakan' => false,
        ];
    }

    public function removeStandar($index): void
    {
        $baris = $this->standar[$index] ?? null;

        if (! $baris) {
            return;
        }

        if (! empty($baris['id'])) {
            $pernahDigunakan = KomponenPenilaian::where('jenis_kegiatan_id', $this->edit_id)
                ->find($baris['id'])
                ?->komponen_penilaian_blok()
                ->withTrashed()
                ->exists();

            if ($pernahDigunakan) {
                $this->addError("standar.$index.status", 'Komponen sudah digunakan oleh blok. Ubah status menjadi nonaktif.');

                return;
            }
        }

        unset($this->standar[$index]);
        $this->standar = array_values($this->standar);
    }

    public function save()
    {
        $this->updatedPakaiCbt($this->pakai_cbt);

        $payload = $this->validate([
            'kode' => ['required', 'string', 'max:255', Rule::unique('jenis_kegiatan', 'kode')->ignore($this->edit_id)],
            'nama' => ['required', 'string', 'max:255'],
            'jumlah_pertemuan_default' => ['required', 'integer', 'min:1', 'max:100'],
            'durasi_menit_default' => ['required', 'integer', 'min:1', 'max:1440'],
            'pakai_cbt' => ['boolean'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'standar' => ['array'],
            'standar.*.id' => ['nullable', 'integer'],
            'standar.*.nama' => ['required', 'string', 'max:255'],
            'standar.*.nilai_min' => ['required', 'numeric', 'min:0', 'max:9999'],
            'standar.*.nilai_maks' => ['required', 'numeric', 'min:0', 'max:9999'],
            'standar.*.urutan' => ['required', 'integer', 'min:1'],
            'standar.*.status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'kode.required' => 'Kode jenis kegiatan wajib diisi.',
            'kode.unique' => 'Kode jenis kegiatan sudah digunakan.',
            'nama.required' => 'Nama jenis kegiatan wajib diisi.',
            'jumlah_pertemuan_default.required' => 'Jumlah pertemuan default wajib diisi.',
            'durasi_menit_default.required' => 'Durasi default wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
            'standar.*.nama.required' => 'Nama komponen penilaian wajib diisi.',
            'standar.*.nilai_min.required' => 'Nilai minimum wajib diisi.',
            'standar.*.nilai_maks.required' => 'Nilai maksimum wajib diisi.',
            'standar.*.urutan.required' => 'Urutan wajib diisi.',
            'standar.*.status.required' => 'Status komponen wajib dipilih.',
        ]);

        $namaKomponen = collect($payload['standar'] ?? [])
            ->pluck('nama')
            ->map(fn ($nama) => Str::lower(trim($nama)));

        if ($namaKomponen->duplicates()->isNotEmpty()) {
            $this->addError('standar', 'Nama komponen penilaian tidak boleh duplikat pada satu jenis kegiatan.');

            return null;
        }

        foreach ($payload['standar'] ?? [] as $index => $baris) {
            if ((float) $baris['nilai_maks'] <= (float) $baris['nilai_min']) {
                $this->addError("standar.$index.nilai_maks", 'Nilai maksimum harus lebih besar dari nilai minimum.');

                return null;
            }
        }

        DB::transaction(function () use ($payload) {
            $jenisPayload = collect($payload)->except('standar')->toArray();

            $jenis = JenisKegiatan::updateOrCreate(['id' => $this->edit_id], $jenisPayload);

            $savedIds = [];

            foreach ($payload['standar'] ?? [] as $index => $baris) {
                $komponen = ! empty($baris['id'])
                    ? KomponenPenilaian::where('jenis_kegiatan_id', $jenis->id)->find($baris['id'])
                    : null;

                if (! $komponen) {
                    $kodeDasar = Str::upper(Str::slug($baris['nama'], '_')) ?: 'KOMPONEN';
                    $kode = $kodeDasar;
                    $nomor = 2;

                    while (KomponenPenilaian::withTrashed()->where('kode', $kode)->exists()) {
                        $kode = $kodeDasar.'_'.$jenis->id.'_'.$nomor++;
                    }

                    $komponen = new KomponenPenilaian(['kode' => $kode]);
                }

                $komponen->fill([
                    'jenis_kegiatan_id' => $jenis->id,
                    'nama' => trim($baris['nama']),
                    'nilai_min_default' => $baris['nilai_min'],
                    'nilai_maks_default' => $baris['nilai_maks'],
                    'urutan' => $baris['urutan'] ?: $index + 1,
                    'status' => $baris['status'],
                ])->save();

                $savedIds[] = $komponen->id;
            }

            $komponenDihapus = KomponenPenilaian::where('jenis_kegiatan_id', $jenis->id)
                ->when($savedIds !== [], fn ($query) => $query->whereNotIn('id', $savedIds))
                ->get();

            foreach ($komponenDihapus as $komponen) {
                if ($komponen->komponen_penilaian_blok()->withTrashed()->exists()) {
                    $komponen->update(['status' => 'nonaktif']);
                } else {
                    $komponen->delete();
                }
            }
        });

        session()->flash('success', $this->edit_id ? 'Berhasil mengubah data' : 'Berhasil menambah data');

        return $this->redirect(route('jenis-kegiatan.index'), navigate: true);
    }
}; ?>

<form wire:submit="save">
    <?php echo csrf_field(); ?>
    <div class="row">
        <div class="col-sm-12 col-lg-12">
            <div class="card">
                <div class="card-header"><h4><?php echo e($edit_id ? 'Edit Jenis Kegiatan' : 'Tambah Jenis Kegiatan'); ?></h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode</label>
                            <input type="text" class="form-control" wire:model.live.debounce.500ms="kode" placeholder="Contoh: TUTORIAL">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['kode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama</label>
                            <input type="text" class="form-control" wire:model.live.debounce.500ms="nama" placeholder="Contoh: Tutorial/PBL">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['nama'];
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jumlah Pertemuan Default</label>
                            <input type="number" class="form-control" wire:model="jumlah_pertemuan_default">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['jumlah_pertemuan_default'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Durasi Menit Default</label>
                            <input type="number" class="form-control" wire:model="durasi_menit_default">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['durasi_menit_default'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">CBT</label>
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" type="checkbox" wire:model.live="pakai_cbt" id="pakaiCbt">
                                <label class="form-check-label" for="pakaiCbt">Pakai CBT</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" wire:model="deskripsi" rows="3"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['deskripsi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" wire:model="status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-1">Standar Komponen Penilaian</h5>
                        <div class="text-muted small">
                            Aspek yang dinilai setiap pertemuan jenis kegiatan ini, misalnya Tutorial = Keaktifan dan Perilaku.
                            Hanya komponen aktif yang disalin saat blok disusun. Komponen nonaktif tetap tersimpan dan bisa diaktifkan kembali.
                        </div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $pakai_cbt): ?>
                        <button type="button" class="btn btn-soft-primary btn-sm" wire:click="addStandar">
                            <i class="ri-add-box-fill"></i> Tambah Komponen
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['standar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="alert alert-danger py-2 mb-3 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pakai_cbt): ?>
                        <div class="alert alert-info py-2 alert-dismissible fade show" role="alert">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                            <i class="ri-information-line"></i>
                            Komponen penilaian Nilai dengan rentang 1–100 otomatis ditambahkan untuk CBT.
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($standar)): ?>
                        <div class="alert alert-light border mb-0 alert-dismissible fade show" role="alert">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                            <i class="ri-information-line"></i>
                            Belum ada komponen penilaian. Jenis kegiatan ini bisa dipakai tanpa penilaian, atau tambahkan
                            komponen agar dosen dapat mengisi nilai per mahasiswa.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 220px;">Komponen</th>
                                        <th style="width: 130px;">Nilai Min</th>
                                        <th style="width: 130px;">Nilai Maks</th>
                                        <th style="width: 110px;">Urutan</th>
                                        <th style="width: 130px;">Status</th>
                                        <th style="width: 70px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $standar; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $baris): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'standar-'.e($index).''; ?>wire:key="standar-<?php echo e($index); ?>">
                                            <td>
                                                <input type="text" class="form-control form-control-sm"
                                                    wire:model="standar.<?php echo e($index); ?>.nama"
                                                    placeholder="Contoh: Keaktifan">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["standar.$index.nama"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="number" min="0" step="0.01" class="form-control form-control-sm"
                                                    wire:model.live.debounce.400ms="standar.<?php echo e($index); ?>.nilai_min">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["standar.$index.nilai_min"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="number" min="0.01" step="0.01" class="form-control form-control-sm"
                                                    wire:model.live.debounce.400ms="standar.<?php echo e($index); ?>.nilai_maks">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["standar.$index.nilai_maks"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm"
                                                    wire:model="standar.<?php echo e($index); ?>.urutan">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["standar.$index.urutan"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm"
                                                    wire:model="standar.<?php echo e($index); ?>.status">
                                                    <option value="aktif">Aktif</option>
                                                    <option value="nonaktif">Nonaktif</option>
                                                </select>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["standar.$index.status"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-sm text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($baris['pernah_digunakan'])): ?>
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                        wire:click="removeStandar(<?php echo e($index); ?>)"
                                                        title="Hapus komponen">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small" title="Komponen sudah digunakan oleh blok">
                                                        Sudah digunakan
                                                    </span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($standar)): ?>
                        <?php
                            $standarAktif = collect($standar)->where('status', 'aktif');
                            $totalNilaiMaks = $standarAktif
                                ->sum(fn ($baris) => (float) ($baris['nilai_maks'] ?? 0));
                        ?>

                        <div class="row g-2 mt-3">
                            <div class="col-sm-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Komponen Aktif</div>
                                    <div class="fs-5 fw-semibold"><?php echo e($standarAktif->count()); ?> dari <?php echo e(count($standar)); ?></div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Total Nilai Maksimum per Pertemuan</div>
                                    <div class="fs-5 fw-semibold"><?php echo e(number_format($totalNilaiMaks, 2, ',', '.')); ?></div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Rumus Nilai Akhir</div>
                                    <div class="fw-semibold">Total skor ÷ <?php echo e(number_format($totalNilaiMaks, 2, ',', '.')); ?> × 100</div>
                                </div>
                            </div>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalNilaiMaks <= 0): ?>
                            <div class="alert alert-warning py-2 mt-3 mb-0 alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                <i class="ri-alert-line"></i>
                                Total nilai maksimum harus lebih dari 0 agar nilai akhir dapat dihitung.
                            </div>
                        <?php else: ?>
                            <div class="text-muted small mt-2">
                                Skor komponen per pertemuan dijumlahkan, lalu dinormalisasi ke skala 100.
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\pages\jenis-kegiatan\add_edit.blade.php ENDPATH**/ ?>