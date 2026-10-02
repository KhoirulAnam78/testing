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

    public array $peserta_ids = [];

    public int $kandidat_per_page = 10;

    public int $peserta_per_page = 10;

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

    public function updatedKandidatPerPage(): void
    {
        $this->resetPage('kandidatPage');
    }

    public function updatedPesertaPerPage(): void
    {
        $this->resetPage('pesertaPage');
    }

    private function perPage(int $perPage): int
    {
        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
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
            ->paginate($this->perPage($this->kandidat_per_page), pageName: 'kandidatPage')
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
        PesertaBlok::where('blok_id', $this->blok_id)->findOrFail($id);

        $this->hapusPeserta([(int) $id]);
        $this->peserta_ids = array_values(array_diff(array_map('strval', $this->peserta_ids), [(string) $id]));

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Peserta dikeluarkan dari blok.',
        ]);
    }

    public function togglePagePeserta(): void
    {
        $ids = $this->pesertaQuery()
            ->paginate($this->perPage($this->peserta_per_page), pageName: 'pesertaPage')
            ->pluck('id_peserta_blok')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        $selected = array_map('strval', $this->peserta_ids);

        $this->peserta_ids = empty(array_diff($ids, $selected))
            ? array_values(array_diff($selected, $ids))
            : array_values(array_unique([...$selected, ...$ids]));
    }

    public function deletePesertaTerpilih(): void
    {
        $this->validate([
            'peserta_ids' => ['required', 'array', 'min:1'],
            'peserta_ids.*' => ['integer'],
        ], [
            'peserta_ids.required' => 'Pilih minimal satu peserta.',
        ]);

        $ids = collect($this->peserta_ids)->map(fn ($id) => (int) $id)->unique()->values();
        $validIds = PesertaBlok::where('blok_id', $this->blok_id)
            ->whereIn('id_peserta_blok', $ids)
            ->pluck('id_peserta_blok');

        if ($validIds->count() !== $ids->count()) {
            $this->addError('peserta_ids', 'Pilihan peserta tidak valid untuk blok ini.');

            return;
        }

        $this->hapusPeserta($validIds->all());
        $jumlah = $validIds->count();
        $this->peserta_ids = [];
        $this->resetPage('pesertaPage');

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $jumlah.' peserta dikeluarkan dari blok.',
        ]);
    }

    private function hapusPeserta(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            AnggotaKelompokBlok::whereIn('peserta_blok_id', $ids)->delete();

            PesertaBlok::where('blok_id', $this->blok_id)
                ->whereIn('id_peserta_blok', $ids)
                ->delete();
        });
    }

    public function render()
    {
        $blok = $this->blok();

        return $this->view([
            'peserta' => $this->pesertaQuery()->paginate($this->perPage($this->peserta_per_page), pageName: 'pesertaPage'),
            'kandidat' => $this->kandidatQuery($blok)->paginate($this->perPage($this->kandidat_per_page), pageName: 'kandidatPage'),
            'rombelOptions' => Kelas::where('blok_id', $this->blok_id)
                ->orderBy('kode')
                ->get(['id_kelas', 'kode', 'nama']),
        ]);
    }
};
?>

<div class="row">
    <x-full-page-loading message="Memproses operasional blok..." />
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Tambah Peserta</h5>
                <span class="badge bg-primary-subtle text-primary">{{ count($kandidat_ids) }} dipilih</span>
            </div>
            <div class="card-body">
                @error('kandidat_ids') <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>{{ $message }}</div> @enderror
                @error('kandidat_kelas_id') <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>{{ $message }}</div> @enderror

                <div class="mb-3">
                    <label class="form-label">Cari Mahasiswa</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ri-search-line"></i></span>
                        <input type="text" class="form-control" placeholder="Nama atau NIM" wire:model.live.debounce.400ms="kandidat_search">
                        <select class="form-select flex-grow-0 w-auto" wire:model.live="kandidat_per_page" aria-label="Jumlah calon peserta per halaman">
                            @foreach ([10, 25, 50, 100] as $jumlah)
                                <option value="{{ $jumlah }}">{{ $jumlah }} tampil</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-text">Hanya mahasiswa aktif pada program studi blok yang belum menjadi peserta.</div>
                </div>

                @if ($rombelOptions->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label">Masukkan ke Rombel</label>
                        <select class="form-select" wire:model="kandidat_kelas_id">
                            <option value="">Tanpa rombel</option>
                            @foreach ($rombelOptions as $rombel)
                                <option value="{{ $rombel->id_kelas }}">{{ $rombel->kode }}{{ $rombel->nama ? ' - '.$rombel->nama : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @php($pageIds = $kandidat->pluck('id_mahasiswa')->map(fn ($id) => (string) $id)->all())
                @php($pageAllSelected = $pageIds !== [] && empty(array_diff($pageIds, array_map('strval', $kandidat_ids))))

                <div class="border rounded">
                    <div class="form-check border-bottom p-3 ps-5 mb-0">
                        <input class="form-check-input" type="checkbox" id="kandidat-page-all"
                            wire:click="togglePageKandidat"
                            @checked($pageAllSelected) @disabled($pageIds === [])>
                        <label class="form-check-label fw-semibold" for="kandidat-page-all">Pilih semua di halaman ini</label>
                    </div>
                    @forelse ($kandidat as $item)
                        <div class="form-check border-bottom p-3 ps-5 mb-0">
                            <input class="form-check-input" type="checkbox" value="{{ $item->id_mahasiswa }}" wire:model.live="kandidat_ids" id="kandidat-{{ $item->id_mahasiswa }}">
                            <label class="form-check-label w-100" for="kandidat-{{ $item->id_mahasiswa }}">
                                <span class="fw-semibold">{{ $item->nama }}</span>
                                <span class="text-muted d-block small">{{ $item->nim }} &middot; angkatan {{ $item->angkatan }}</span>
                            </label>
                        </div>
                    @empty
                        <div class="text-muted small p-3">
                            {{ $kandidat_search ? 'Mahasiswa tidak ditemukan.' : 'Semua mahasiswa aktif prodi ini sudah menjadi peserta blok.' }}
                        </div>
                    @endforelse
                </div>

                @if ($kandidat->hasPages())
                    <div class="mt-3">{{ $kandidat->links() }}</div>
                @endif

                <button type="button" class="btn btn-primary mt-3" wire:click="addPeserta" wire:loading.attr="disabled" wire:target="addPeserta" @disabled(count($kandidat_ids) === 0)>
                    <span wire:loading.remove wire:target="addPeserta"><i class="ri-user-add-line"></i> Tambah {{ count($kandidat_ids) ?: '' }} Peserta</span>
                    <span wire:loading wire:target="addPeserta">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Daftar Peserta Blok</h5>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-info-subtle text-info">{{ $peserta->total() }} peserta</span>
                    <span class="badge bg-primary-subtle text-primary">{{ count($peserta_ids) }} dipilih</span>
                    <button type="button" class="btn btn-danger btn-sm"
                        wire:click="deletePesertaTerpilih"
                        wire:confirm="Keluarkan semua peserta terpilih dari blok? Keanggotaan kelompok mereka juga akan dihapus."
                        @disabled(count($peserta_ids) === 0)>
                        <i class="ri-delete-bin-line"></i> Hapus Terpilih
                    </button>
                </div>
            </div>
            <div class="card-body">
                @error('peserta_ids') <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>{{ $message }}</div> @enderror
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="ri-search-line"></i></span>
                    <input type="text" class="form-control" placeholder="Cari nama atau NIM peserta" wire:model.live.debounce.400ms="peserta_search">
                    <select class="form-select flex-grow-0 w-auto" wire:model.live="peserta_per_page" aria-label="Jumlah peserta per halaman">
                        @foreach ([10, 25, 50, 100] as $jumlah)
                            <option value="{{ $jumlah }}">{{ $jumlah }} tampil</option>
                        @endforeach
                    </select>
                </div>

                @php($pesertaPageIds = $peserta->pluck('id_peserta_blok')->map(fn ($id) => (string) $id)->all())
                @php($pesertaPageAllSelected = $pesertaPageIds !== [] && empty(array_diff($pesertaPageIds, array_map('strval', $peserta_ids))))

                <div class="table-responsive">
                    <table class="table table-nowrap align-middle">
                        <thead>
                            <tr>
                                <th style="width: 1%">
                                    <input class="form-check-input" type="checkbox" aria-label="Pilih semua peserta di halaman ini"
                                        wire:click="togglePagePeserta"
                                        @checked($pesertaPageAllSelected) @disabled($pesertaPageIds === [])>
                                </th>
                                <th>Mahasiswa</th>
                                <th>Status Kontrak</th>
                                @if ($rombelOptions->isNotEmpty())
                                    <th>Rombel</th>
                                @endif
                                <th>Kelompok</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($peserta as $item)
                                <tr wire:key="peserta-{{ $item->id_peserta_blok }}">
                                    <td>
                                        <input class="form-check-input" type="checkbox" value="{{ $item->id_peserta_blok }}"
                                            wire:model.live="peserta_ids" aria-label="Pilih {{ $item->mahasiswa?->nama }}">
                                    </td>
                                    <td>
                                        <span class="fw-semibold">{{ $item->mahasiswa?->nama }}</span>
                                        <span class="text-muted d-block small">{{ $item->mahasiswa?->nim }}</span>
                                    </td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'bg-primary-subtle text-primary' => $item->status === 'aktif',
                                            'bg-warning-subtle text-warning' => $item->status === 'mengulang',
                                            'bg-success-subtle text-success' => $item->status === 'selesai',
                                            'bg-secondary-subtle text-secondary' => ! in_array($item->status, ['aktif', 'mengulang', 'selesai'], true),
                                        ])>{{ ucfirst($item->status) }}</span>
                                    </td>
                                    @if ($rombelOptions->isNotEmpty())
                                        <td>
                                            <select class="form-select form-select-sm" wire:change="setRombel('{{ $item->id_peserta_blok }}', $event.target.value)">
                                                <option value="" @selected(! $item->kelas_id)>Tanpa rombel</option>
                                                @foreach ($rombelOptions as $rombel)
                                                    <option value="{{ $rombel->id_kelas }}" @selected((int) $item->kelas_id === (int) $rombel->id_kelas)>{{ $rombel->kode }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endif
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $item->anggota_kelompok_blok_count }} kelompok</span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-danger btn-sm"
                                            wire:click="deletePeserta('{{ $item->id_peserta_blok }}')"
                                            wire:confirm="Keluarkan peserta ini dari blok? Keanggotaan kelompoknya juga akan dihapus.">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $rombelOptions->isNotEmpty() ? 6 : 5 }}" class="text-muted">
                                        {{ $peserta_search ? 'Peserta tidak ditemukan.' : 'Belum ada peserta pada blok ini.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($peserta->hasPages())
                    {{ $peserta->links() }}
                @endif
            </div>
        </div>
    </div>
</div>
