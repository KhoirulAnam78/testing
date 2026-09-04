<?php

use App\Models\Kelas;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public int $blok_id;
    public $edit_id;
    public string $kode = '';
    public string $nama = '';
    public $kapasitas;
    public string $status = 'aktif';

    public function mount($blok_id): void
    {
        $this->blok_id = (int) $blok_id;
    }

    public function resetForm(): void
    {
        $this->reset(['edit_id', 'kode', 'nama', 'kapasitas']);
        $this->status = 'aktif';
        $this->resetErrorBag();
    }

    public function edit(string $id): void
    {
        $rombel = Kelas::where('blok_id', $this->blok_id)->findOrFail($id);

        $this->edit_id = $rombel->id_kelas;
        $this->kode = (string) $rombel->kode;
        $this->nama = (string) $rombel->nama;
        $this->kapasitas = $rombel->kapasitas;
        $this->status = $rombel->status;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $payload = $this->validate([
            'kode' => [
                'required',
                'string',
                'max:255',
                Rule::unique('kelas', 'kode')
                    ->where('blok_id', $this->blok_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->edit_id, 'id_kelas'),
            ],
            'nama' => ['nullable', 'string', 'max:255'],
            'kapasitas' => ['nullable', 'integer', 'min:1', 'max:999'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'kode.required' => 'Kode rombel wajib diisi.',
            'kode.unique' => 'Kode rombel sudah dipakai pada blok ini.',
            'kapasitas.integer' => 'Kapasitas harus berupa angka.',
        ]);

        if ($this->edit_id) {
            $rombel = Kelas::where('blok_id', $this->blok_id)->findOrFail($this->edit_id);
            $terpakai = $rombel->peserta_blok()->count();

            if ($payload['kapasitas'] && $terpakai > (int) $payload['kapasitas']) {
                $this->addError('kapasitas', 'Kapasitas tidak boleh lebih kecil dari '.$terpakai.' peserta yang sudah masuk rombel ini.');

                return;
            }
        }

        // Unique index (blok_id, kode) tetap ditempati baris yang dihapus lembut,
        // jadi baris lama dengan kode sama dipulihkan alih-alih dibuat ulang.
        $rombel = $this->edit_id
            ? Kelas::where('blok_id', $this->blok_id)->findOrFail($this->edit_id)
            : Kelas::withTrashed()->firstOrNew([
                'blok_id' => $this->blok_id,
                'kode' => $payload['kode'],
            ]);

        $rombel->fill([
            'blok_id' => $this->blok_id,
            'kode' => $payload['kode'],
            'nama' => $payload['nama'] ?: null,
            'kapasitas' => $payload['kapasitas'] ?: null,
            'status' => $payload['status'],
        ]);

        if ($rombel->trashed()) {
            $rombel->restore();
        }

        $rombel->save();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $this->edit_id ? 'Rombel berhasil diubah.' : 'Rombel berhasil ditambahkan.',
        ]);

        $this->resetForm();
    }

    public function delete(string $id): void
    {
        $rombel = Kelas::where('blok_id', $this->blok_id)
            ->withCount(['peserta_blok', 'kelompok_blok'])
            ->findOrFail($id);

        if ($rombel->peserta_blok_count > 0 || $rombel->kelompok_blok_count > 0) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Rombel tidak dapat dihapus karena masih dipakai '.$rombel->peserta_blok_count.' peserta dan '.$rombel->kelompok_blok_count.' kelompok.',
            ]);

            return;
        }

        $rombel->delete();

        if ((int) $this->edit_id === (int) $id) {
            $this->resetForm();
        }

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Rombel berhasil dihapus.',
        ]);
    }

    public function render()
    {
        return $this->view([
            'rombelList' => Kelas::where('blok_id', $this->blok_id)
                ->withCount(['peserta_blok', 'kelompok_blok'])
                ->orderBy('kode')
                ->get(),
        ]);
    }
};
?>

<div class="row">
    <x-full-page-loading message="Memproses operasional blok..." />
    <div class="col-xl-4">
        <form wire:submit="save" class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $edit_id ? 'Edit Rombel' : 'Tambah Rombel' }}</h5>
                @if ($edit_id)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="resetForm">Batal</button>
                @endif
            </div>
            <div class="card-body">
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    <i class="ri-information-line"></i>
                    Rombel bersifat <span class="fw-semibold">opsional</span>. Blok tetap berjalan penuh tanpa rombel.
                    Buat rombel hanya bila satu blok perlu dipecah menjadi beberapa rombongan paralel.
                </div>

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" class="form-control" placeholder="R001" wire:model.live.debounce.500ms="kode">
                        @error('kode') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-7 mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" placeholder="Reguler 001" wire:model.live.debounce.500ms="nama">
                        @error('nama') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kapasitas</label>
                        <input type="number" class="form-control" wire:model="kapasitas">
                        @error('kapasitas') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" wire:model="status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                        @error('status') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <i class="ri-save-line"></i> SIMPAN
                </button>
            </div>
        </form>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Daftar Rombel</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-nowrap align-middle">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Peserta</th>
                                <th>Kelompok</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rombelList as $rombel)
                                <tr wire:key="rombel-{{ $rombel->id_kelas }}">
                                    <td class="fw-semibold">{{ $rombel->kode }}</td>
                                    <td>{{ $rombel->nama ?: '-' }}</td>
                                    <td>{{ $rombel->peserta_blok_count }}{{ $rombel->kapasitas ? ' / '.$rombel->kapasitas : '' }}</td>
                                    <td>{{ $rombel->kelompok_blok_count }}</td>
                                    <td>
                                        @if ($rombel->status === 'aktif')
                                            <span class="badge bg-success">Aktif</span>
                                        @else
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-info btn-sm" wire:click="edit('{{ $rombel->id_kelas }}')">
                                            <i class="ri-file-edit-line"></i> Kelola
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="delete('{{ $rombel->id_kelas }}')" wire:confirm="Hapus rombel ini?">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-muted">
                                        Belum ada rombel. Blok ini berjalan sebagai satu rombongan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
