<?php

use App\Models\Kurikulum;
use App\Models\KurikulumMataKuliah;
use App\Models\MataKuliah;
use App\Models\PrasyaratMataKuliah;
use App\Models\Prodi;
use App\Models\SkalaNilai;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $edit_id;

    public $prodi_id;

    public $skala_nilai_id;

    public $kode;

    public $nama;

    public $tahun_berlaku;

    public $sks_lulus = 144;

    public $semester_normal = 8;

    public $deskripsi;

    public $status = 'draft';

    public array $pemetaan = [];

    public $prodi = [];

    public $skalaNilai = [];

    public array $mataKuliah = [];

    public array $detailNilai = [];

    public function mount($id): void
    {
        abort_unless(auth()->user()?->can('kurikulum:'), 403);
        $this->prodi = Prodi::orderBy('nama')->get(['id_prodi', 'kode', 'nama']);
        $this->skalaNilai = SkalaNilai::orderByDesc('aktif')->orderBy('nama')->orderByDesc('versi')->get();

        if ($id === 'add') {
            $this->skala_nilai_id = SkalaNilai::where('aktif', true)->value('id_skala_nilai');
            $this->muatDetailNilai();

            return;
        }

        try {
            $this->edit_id = Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404, 'Enkripsi tidak valid!');
        }

        $kurikulum = Kurikulum::with([
            'mata_kuliah' => fn ($query) => $query->with(['aturan', 'prasyarat'])->orderBy('semester_urutan'),
        ])->findOrFail($this->edit_id);

        foreach (['prodi_id', 'skala_nilai_id', 'kode', 'nama', 'tahun_berlaku', 'sks_lulus', 'semester_normal', 'deskripsi', 'status'] as $field) {
            $this->{$field} = $kurikulum->{$field};
        }

        $this->muatMataKuliah();
        $this->muatDetailNilai();

        $indexById = $kurikulum->mata_kuliah->values()->mapWithKeys(fn ($item, $index) => [$item->id_kurikulum_mata_kuliah => $index]);
        $this->pemetaan = $kurikulum->mata_kuliah->values()->map(fn ($item) => [
            'id' => $item->id_kurikulum_mata_kuliah,
            'mata_kuliah_id' => $item->mata_kuliah_id,
            'semester_urutan' => $item->semester_urutan,
            'apakah_wajib' => $item->apakah_wajib,
            'catatan' => $item->catatan,
            'periode_pengambilan_ulang' => $item->aturan?->periode_pengambilan_ulang ?? 'mengikuti_semester_kurikulum',
            'aturan_aktif' => $item->aturan?->aktif ?? true,
            'prasyarat' => $item->prasyarat->map(fn ($prasyarat) => [
                'id' => $prasyarat->id,
                'pemetaan_index' => $indexById[$prasyarat->prasyarat_kurikulum_mata_kuliah_id] ?? null,
                'skala_nilai_detail_id' => $prasyarat->skala_nilai_detail_id,
                'aktif' => $prasyarat->aktif,
                'catatan' => $prasyarat->catatan,
            ])->all(),
        ])->all();
    }

    public function updatedProdiId(): void
    {
        foreach ($this->pemetaan as $index => $baris) {
            if (! MataKuliah::where('prodi_id', $this->prodi_id)->whereKey($baris['mata_kuliah_id'] ?? null)->exists()) {
                unset($this->pemetaan[$index]);
            }
        }

        $this->pemetaan = array_values($this->pemetaan);
        $this->muatMataKuliah();
    }

    public function updatedSkalaNilaiId(): void
    {
        $this->muatDetailNilai();

        foreach ($this->pemetaan as &$baris) {
            $baris['prasyarat'] = [];
        }
    }

    #[On('select-value')]
    public function selectValue($selected): void
    {
        $model = $selected['model'] ?? null;
        $value = $selected['value'] ?? null;

        if (is_string($model) && ctype_digit((string) $value) && preg_match('/^pemetaan\.(\d+)\.mata_kuliah_id$/', $model, $matches)) {
            $index = (int) $matches[1];
            $mataKuliahId = (int) $value;

            if (isset($this->pemetaan[$index]) && collect($this->mataKuliah)->contains('id', $mataKuliahId)) {
                $this->pemetaan[$index]['mata_kuliah_id'] = $mataKuliahId;
            }

            return;
        }

        if (is_string($model) && ctype_digit((string) $value) && preg_match('/^pemetaan\.(\d+)\.prasyarat\.(\d+)\.pemetaan_index$/', $model, $matches)) {
            $index = (int) $matches[1];
            $prasyaratIndex = (int) $matches[2];
            $candidateIndex = (int) $value;
            $candidate = $this->pemetaan[$candidateIndex] ?? null;
            $baris = $this->pemetaan[$index] ?? null;

            if (isset($this->pemetaan[$index]['prasyarat'][$prasyaratIndex])
                && $candidateIndex !== $index
                && $candidate
                && (int) ($candidate['semester_urutan'] ?? 0) < (int) ($baris['semester_urutan'] ?? 0)) {
                $this->pemetaan[$index]['prasyarat'][$prasyaratIndex]['pemetaan_index'] = $candidateIndex;
            }
        }
    }

    public function addPemetaan(): void
    {
        $this->pemetaan[] = [
            'id' => null, 'mata_kuliah_id' => null, 'semester_urutan' => 1, 'apakah_wajib' => true, 'catatan' => null,
            'periode_pengambilan_ulang' => 'mengikuti_semester_kurikulum', 'aturan_aktif' => true, 'prasyarat' => [],
        ];
    }

    public function removePemetaan(int $index): void
    {
        unset($this->pemetaan[$index]);
        $this->pemetaan = array_values($this->pemetaan);

        foreach ($this->pemetaan as &$baris) {
            $baris['prasyarat'] = array_values(array_filter($baris['prasyarat'] ?? [], fn ($item) => ($item['pemetaan_index'] ?? null) !== $index));
            foreach ($baris['prasyarat'] as &$prasyarat) {
                if (($prasyarat['pemetaan_index'] ?? -1) > $index) {
                    $prasyarat['pemetaan_index']--;
                }
            }
        }
    }

    public function addPrasyarat(int $index): void
    {
        $this->pemetaan[$index]['prasyarat'][] = ['id' => null, 'pemetaan_index' => null, 'skala_nilai_detail_id' => null, 'aktif' => true, 'catatan' => null];
    }

    public function removePrasyarat(int $index, int $prasyaratIndex): void
    {
        unset($this->pemetaan[$index]['prasyarat'][$prasyaratIndex]);
        $this->pemetaan[$index]['prasyarat'] = array_values($this->pemetaan[$index]['prasyarat']);
    }

    public function save()
    {
        abort_unless(auth()->user()?->can('kurikulum:'), 403);

        $payload = $this->validate([
            'prodi_id' => ['required', 'exists:prodi,id_prodi'],
            'skala_nilai_id' => ['required', 'exists:skala_nilai,id_skala_nilai'],
            'kode' => ['required', 'string', 'max:255', Rule::unique('kurikulum', 'kode')->where('prodi_id', $this->prodi_id)->ignore($this->edit_id, 'id_kurikulum')],
            'nama' => ['required', 'string', 'max:255'],
            'tahun_berlaku' => ['required', 'integer', 'min:1900', 'max:2200', Rule::unique('kurikulum', 'tahun_berlaku')->where('prodi_id', $this->prodi_id)->ignore($this->edit_id, 'id_kurikulum')],
            'sks_lulus' => ['required', 'numeric', 'min:0.5', 'max:999.9'],
            'semester_normal' => ['required', 'integer', 'min:1', 'max:30'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'aktif', 'nonaktif', 'arsip'])],
            'pemetaan' => ['array'],
            'pemetaan.*.id' => ['nullable', 'integer'],
            'pemetaan.*.mata_kuliah_id' => ['required', 'integer'],
            'pemetaan.*.semester_urutan' => ['required', 'integer', 'min:1', 'max:30'],
            'pemetaan.*.apakah_wajib' => ['boolean'],
            'pemetaan.*.catatan' => ['nullable', 'string'],
            'pemetaan.*.periode_pengambilan_ulang' => ['required', Rule::in(['mengikuti_semester_kurikulum', 'ganjil', 'genap', 'semua'])],
            'pemetaan.*.aturan_aktif' => ['boolean'],
            'pemetaan.*.prasyarat' => ['array'],
            'pemetaan.*.prasyarat.*.id' => ['nullable', 'integer'],
            'pemetaan.*.prasyarat.*.pemetaan_index' => ['required', 'integer', 'min:0'],
            'pemetaan.*.prasyarat.*.skala_nilai_detail_id' => ['required', 'integer'],
            'pemetaan.*.prasyarat.*.aktif' => ['boolean'],
            'pemetaan.*.prasyarat.*.catatan' => ['nullable', 'string'],
        ], [
            'prodi_id.required' => 'Program studi wajib dipilih.',
            'skala_nilai_id.required' => 'Skala nilai wajib dipilih.',
            'kode.unique' => 'Kode sudah dipakai prodi ini.',
            'tahun_berlaku.unique' => 'Tahun berlaku sudah dipakai prodi ini.',
            'pemetaan.*.mata_kuliah_id.required' => 'Mata kuliah wajib dipilih.',
            'pemetaan.*.prasyarat.*.pemetaan_index.required' => 'Mata kuliah prasyarat wajib dipilih.',
            'pemetaan.*.prasyarat.*.skala_nilai_detail_id.required' => 'Nilai minimum wajib dipilih.',
        ]);

        if (! $this->aturanValid($payload)) {
            return null;
        }

        if ($this->edit_id && (int) Kurikulum::findOrFail($this->edit_id)->skala_nilai_id !== (int) $payload['skala_nilai_id']) {
            $this->addError('skala_nilai_id', 'Versi skala nilai kurikulum yang sudah disimpan tidak dapat diganti.');

            return null;
        }

        DB::transaction(function () use ($payload) {
            $kurikulum = Kurikulum::updateOrCreate(
                ['id_kurikulum' => $this->edit_id],
                collect($payload)->except('pemetaan')->all()
            );
            $saved = [];

            foreach ($payload['pemetaan'] as $index => $baris) {
                $mapping = KurikulumMataKuliah::withTrashed()->firstOrNew([
                    'kurikulum_id' => $kurikulum->id_kurikulum,
                    'mata_kuliah_id' => (int) $baris['mata_kuliah_id'],
                ]);
                $mapping->fill(collect($baris)->only(['semester_urutan', 'apakah_wajib', 'catatan'])->all());
                if ($mapping->trashed()) {
                    $mapping->restore();
                }
                $mapping->save();
                $mapping->aturan()->updateOrCreate([], [
                    'periode_pengambilan_ulang' => $baris['periode_pengambilan_ulang'],
                    'aktif' => $baris['aturan_aktif'],
                ]);
                $saved[$index] = $mapping;
            }

            foreach ($saved as $index => $mapping) {
                $savedPrerequisites = [];
                foreach ($payload['pemetaan'][$index]['prasyarat'] ?? [] as $prasyarat) {
                    $model = $mapping->prasyarat()->updateOrCreate(
                        ['prasyarat_kurikulum_mata_kuliah_id' => $saved[$prasyarat['pemetaan_index']]->id_kurikulum_mata_kuliah],
                        collect($prasyarat)->only(['skala_nilai_detail_id', 'aktif', 'catatan'])->all()
                    );
                    $savedPrerequisites[] = $model->id;
                }
                $mapping->prasyarat()->when($savedPrerequisites !== [], fn ($query) => $query->whereNotIn('id', $savedPrerequisites))->delete();
            }

            $dihapus = $kurikulum->mata_kuliah()->whereNotIn('id_kurikulum_mata_kuliah', collect($saved)->pluck('id_kurikulum_mata_kuliah'))->get();
            foreach ($dihapus as $mapping) {
                $mapping->prasyarat()->delete();
                PrasyaratMataKuliah::where('prasyarat_kurikulum_mata_kuliah_id', $mapping->id_kurikulum_mata_kuliah)->delete();
                $mapping->delete();
            }
        });

        session()->flash('success', 'Kurikulum berhasil disimpan.');

        return $this->redirect(route('kurikulum.index'), navigate: true);
    }

    private function aturanValid(array $payload): bool
    {
        $mataKuliahIds = collect($payload['pemetaan'])->pluck('mata_kuliah_id')->map(fn ($id) => (int) $id);
        if ($mataKuliahIds->duplicates()->isNotEmpty()) {
            $this->addError('pemetaan', 'Mata kuliah tidak boleh muncul dua kali dalam satu kurikulum.');

            return false;
        }

        if (MataKuliah::whereIn('id', $mataKuliahIds)->where('prodi_id', $payload['prodi_id'])->count() !== $mataKuliahIds->count()) {
            $this->addError('pemetaan', 'Semua mata kuliah harus berasal dari program studi kurikulum.');

            return false;
        }

        $detailIds = collect($payload['pemetaan'])->flatMap(fn ($item) => collect($item['prasyarat'] ?? [])->pluck('skala_nilai_detail_id'))->unique();
        if ($detailIds->isNotEmpty() && SkalaNilai::findOrFail($payload['skala_nilai_id'])->detail()->whereIn('id_skala_nilai_detail', $detailIds)->count() !== $detailIds->count()) {
            $this->addError('pemetaan', 'Nilai minimum harus berasal dari skala nilai kurikulum.');

            return false;
        }

        foreach ($payload['pemetaan'] as $index => $baris) {
            $targets = collect($baris['prasyarat'] ?? [])->pluck('pemetaan_index');
            if ($targets->duplicates()->isNotEmpty()) {
                $this->addError("pemetaan.$index.prasyarat", 'Mata kuliah prasyarat tidak boleh duplikat.');

                return false;
            }

            foreach ($targets as $target) {
                if (! isset($payload['pemetaan'][$target]) || $target === $index || (int) $payload['pemetaan'][$target]['semester_urutan'] >= (int) $baris['semester_urutan']) {
                    $this->addError("pemetaan.$index.prasyarat", 'Prasyarat wajib berasal dari semester lebih awal pada kurikulum sama.');

                    return false;
                }
            }
        }

        if ($payload['status'] === 'aktif' && Kurikulum::where('prodi_id', $payload['prodi_id'])->where('status', 'aktif')
            ->when($this->edit_id, fn ($query) => $query->whereKeyNot($this->edit_id))->exists()) {
            $this->addError('status', 'Program studi sudah memiliki kurikulum aktif. Nonaktifkan kurikulum lama lebih dulu.');

            return false;
        }

        return true;
    }

    private function muatMataKuliah(): void
    {
        $this->mataKuliah = MataKuliah::where('prodi_id', $this->prodi_id)
            ->where('status', 'aktif')
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama'])
            ->toArray();
    }

    private function muatDetailNilai(): void
    {
        $this->detailNilai = SkalaNilai::find($this->skala_nilai_id)?->detail()->get()->toArray() ?? [];
    }
}; ?>

<form wire:submit="save">
    <div class="page-title-box"><h4 class="mb-sm-0">{{ $edit_id ? 'Kelola' : 'Tambah' }} Kurikulum</h4></div>
    <div class="card"><div class="card-header"><h5 class="mb-0">Identitas</h5></div><div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Program Studi</label><select class="form-select" wire:model.live="prodi_id"><option value="">Pilih prodi</option>@foreach ($prodi as $item)<option value="{{ $item->id_prodi }}">{{ $item->kode }} - {{ $item->nama }}</option>@endforeach</select>@error('prodi_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
            <div class="col-md-6 mb-3"><label class="form-label">Skala Nilai</label><select class="form-select" wire:model.live="skala_nilai_id" @disabled($edit_id)><option value="">Pilih skala</option>@foreach ($skalaNilai as $item)<option value="{{ $item->id_skala_nilai }}">{{ $item->nama }} v{{ $item->versi }}{{ $item->aktif ? ' (aktif)' : '' }}</option>@endforeach</select>@error('skala_nilai_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
            <div class="col-md-3 mb-3"><label class="form-label">Kode</label><input class="form-control" wire:model="kode">@error('kode')<small class="text-danger">{{ $message }}</small>@enderror</div>
            <div class="col-md-5 mb-3"><label class="form-label">Nama</label><input class="form-control" wire:model="nama">@error('nama')<small class="text-danger">{{ $message }}</small>@enderror</div>
            <div class="col-md-2 mb-3"><label class="form-label">Tahun Berlaku</label><input type="number" class="form-control" wire:model="tahun_berlaku">@error('tahun_berlaku')<small class="text-danger">{{ $message }}</small>@enderror</div>
            <div class="col-md-2 mb-3"><label class="form-label">Status</label><select class="form-select" wire:model="status"><option value="draft">Draft</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option><option value="arsip">Arsip</option></select>@error('status')<small class="text-danger">{{ $message }}</small>@enderror</div>
            <div class="col-md-3 mb-3"><label class="form-label">SKS Lulus</label><input type="number" step="0.5" class="form-control" wire:model="sks_lulus"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Semester Normal</label><input type="number" class="form-control" wire:model="semester_normal"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Deskripsi</label><textarea class="form-control" rows="2" wire:model="deskripsi"></textarea></div>
        </div>
    </div></div>

    <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><div><h5 class="mb-0">Pemetaan dan Aturan</h5><small class="text-muted">Aturan periode hanya berlaku untuk pengambilan ulang. Semester pendek hanya lolos untuk Semua.</small></div><button type="button" class="btn btn-soft-primary btn-sm" wire:click="addPemetaan" @disabled(! $prodi_id)>Tambah Mata Kuliah</button></div>
        <div class="card-body">@error('pemetaan')<div class="alert alert-danger">{{ $message }}</div>@enderror
            @forelse ($pemetaan as $index => $baris)
                <div class="border rounded p-3 mb-3" wire:key="mapping-{{ $index }}">
                    <div class="row align-items-end">
                        <div class="col-lg-4 mb-3">
                            <livewire:dropdown.select-search
                                :options="collect($mataKuliah)->map(fn ($mk) => ['value' => $mk['id'], 'label' => $mk['kode'].' - '.$mk['nama']])->all()"
                                :wire_model="'pemetaan.'.$index.'.mata_kuliah_id'"
                                label="Mata Kuliah"
                                :selected="$baris['mata_kuliah_id']"
                                :key="'kurikulum-mata-kuliah-'.$index.'-'.($baris['id'] ?? 'baru').'-'.($baris['mata_kuliah_id'] ?? 'kosong')"
                            />
                            @error("pemetaan.$index.mata_kuliah_id")<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="col-lg-2 mb-3"><label class="form-label">Semester</label><input type="number" min="1" max="30" class="form-control" wire:model.live="pemetaan.{{ $index }}.semester_urutan"></div>
                        <div class="col-lg-2 mb-3"><label class="form-label">Sifat</label><select class="form-select" wire:model="pemetaan.{{ $index }}.apakah_wajib"><option value="1">Wajib</option><option value="0">Pilihan</option></select></div>
                        <div class="col-lg-3 mb-3"><label class="form-label">Pengambilan Ulang</label><select class="form-select" wire:model="pemetaan.{{ $index }}.periode_pengambilan_ulang"><option value="mengikuti_semester_kurikulum">Ikuti semester kurikulum</option><option value="ganjil">Ganjil</option><option value="genap">Genap</option><option value="semua">Bebas kontrak ganjil/genap</option></select></div>
                        <div class="col-lg-1 mb-3"><button type="button" class="btn btn-danger" wire:click="removePemetaan({{ $index }})" title="Hapus"><i class="ri-delete-bin-line"></i></button></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2"><strong>Prasyarat (AND)</strong><button type="button" class="btn btn-soft-secondary btn-sm" wire:click="addPrasyarat({{ $index }})">Tambah Prasyarat</button></div>
                    @error("pemetaan.$index.prasyarat")<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    @foreach ($baris['prasyarat'] ?? [] as $pIndex => $prasyarat)
                        <div class="row align-items-end mb-2" wire:key="prasyarat-{{ $index }}-{{ $pIndex }}">
                            @php
                                $candidateOptions = collect($pemetaan)->map(function ($candidate, $candidateIndex) use ($index, $baris, $mataKuliah) {
                                    if ($candidateIndex === $index || (int) ($candidate['semester_urutan'] ?? 0) >= (int) ($baris['semester_urutan'] ?? 0)) {
                                        return null;
                                    }

                                    $mk = collect($mataKuliah)->firstWhere('id', (int) ($candidate['mata_kuliah_id'] ?? 0));

                                    return [
                                        'value' => $candidateIndex,
                                        'label' => 'Semester '.($candidate['semester_urutan'] ?? '-').' - '.($mk ? $mk['kode'].' - '.$mk['nama'] : 'Belum dipilih'),
                                    ];
                                })->filter()->values()->all();
                            @endphp
                            <div class="col-md-5">
                                <livewire:dropdown.select-search
                                    :options="$candidateOptions"
                                    :wire_model="'pemetaan.'.$index.'.prasyarat.'.$pIndex.'.pemetaan_index'"
                                    label="Mata Kuliah Sebelumnya"
                                    :selected="$prasyarat['pemetaan_index']"
                                    :key="'prasyarat-'.$index.'-'.$pIndex.'-'.md5(json_encode($candidateOptions)).'-'.($prasyarat['pemetaan_index'] ?? 'kosong')"
                                />
                                @error("pemetaan.$index.prasyarat.$pIndex.pemetaan_index")<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                            <div class="col-md-3"><label class="form-label">Nilai Minimum</label><select class="form-select" wire:model="pemetaan.{{ $index }}.prasyarat.{{ $pIndex }}.skala_nilai_detail_id"><option value="">Pilih grade</option>@foreach ($detailNilai as $grade)<option value="{{ $grade['id_skala_nilai_detail'] }}">{{ $grade['nilai_huruf'] }} (≥ {{ $grade['nilai_angka_min'] }})</option>@endforeach</select>@error("pemetaan.$index.prasyarat.$pIndex.skala_nilai_detail_id")<small class="text-danger">{{ $message }}</small>@enderror</div>
                            <div class="col-md-3"><label class="form-label">Catatan</label><input class="form-control" wire:model="pemetaan.{{ $index }}.prasyarat.{{ $pIndex }}.catatan"></div>
                            <div class="col-md-1"><button type="button" class="btn btn-outline-danger" wire:click="removePrasyarat({{ $index }}, {{ $pIndex }})"><i class="ri-close-line"></i></button></div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="text-muted text-center py-4">Pilih program studi, lalu tambah mata kuliah.</div>
            @endforelse
        </div>
    </div>
    <div class="d-flex gap-2 justify-content-end mb-5"><a href="{{ route('kurikulum.index') }}" wire:navigate class="btn btn-light">Kembali</a><button class="btn btn-primary" wire:loading.attr="disabled"><i class="ri-save-line"></i> Simpan</button></div>
</form>