<?php

use App\Models\Prodi;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public $edit_id;
    public $kode;
    public $nama;
    public $jenjang;
    public $status = 'aktif';

    public function mount($id): void
    {
        if ($id && $id !== 'add') {
            try {
                $decrypted = Crypt::decrypt($id);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                abort(404, 'Enkripsi tidak valid !');
            }

            $this->edit_id = $decrypted;
            $prodi = Prodi::findOrFail($this->edit_id);

            $this->kode = $prodi->kode;
            $this->nama = $prodi->nama;
            $this->jenjang = $prodi->jenjang;
            $this->status = $prodi->status;
        }
    }

    public function save()
    {
        $validations = [
            'kode' => 'required|string|max:255|unique:prodi,kode'.($this->edit_id ? ','.$this->edit_id.',id_prodi' : ''),
            'nama' => 'required|string|max:255',
            'jenjang' => ['nullable', 'string', Rule::in(array_keys(config('akademik.jenjang_pendidikan', [])))],
            'status' => 'required|in:aktif,nonaktif',
        ];

        $messages = [
            'kode.required' => 'Kode prodi wajib diisi.',
            'kode.unique' => 'Kode prodi sudah digunakan.',
            'nama.required' => 'Nama program studi wajib diisi.',
            'jenjang.in' => 'Jenjang pendidikan tidak valid.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
        ];

        $payload = $this->validate($validations, $messages);

        // jenjang yang tidak dipilih disimpan sebagai NULL, bukan string kosong
        if (blank($payload['jenjang'] ?? null)) {
            $payload['jenjang'] = null;
        }

        if ($this->edit_id) {
            Prodi::findOrFail($this->edit_id)->update($payload);
            session()->flash('success', 'Berhasil mengubah data');
        } else {
            Prodi::create($payload);
            session()->flash('success', 'Berhasil menambah data');
        }

        return $this->redirect(route('prodi.index'), navigate: true);
    }
}; ?>

<form wire:submit="save">
    @csrf
    <div class="row">
        <div class="col-sm-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4>{{ $edit_id ? 'Edit Program Studi' : 'Tambah Program Studi' }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Prodi</label>
                        <input type="text" class="form-control" wire:model.live.debounce.500ms="kode" placeholder="Contoh: PSPD">
                        @error('kode')
                            <div class="text-sm text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Program Studi</label>
                        <input type="text" class="form-control" wire:model.live.debounce.500ms="nama" placeholder="Contoh: Pendidikan Dokter">
                        @error('nama')
                            <div class="text-sm text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenjang</label>
                        <select class="form-select" wire:model="jenjang">
                            <option value="">-- Pilih Jenjang --</option>
                            @foreach (config('akademik.jenjang_pendidikan', []) as $kode => $nama)
                                <option value="{{ $kode }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                        @error('jenjang')
                            <div class="text-sm text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" wire:model="status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                        @error('status')
                            <div class="text-sm text-danger">{{ $message }}</div>
                        @enderror
                    </div>
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
