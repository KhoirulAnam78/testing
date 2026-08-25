<?php

use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $edit_id;

    public $user_id;

    public $prodi_id;

    public $nim;

    public $nama;

    public $email;

    public $no_hp;

    public $angkatan;

    public $status = 'aktif';

    public $prodi = [];

    public $users = [];

    public function mount($id): void
    {
        $this->prodi = Prodi::orderBy('nama')->get(['id_prodi', 'nama', 'kode']);
        $this->users = User::orderBy('name')->get(['id', 'name', 'username']);

        if ($id && $id !== 'add') {
            try {
                $this->edit_id = Crypt::decrypt($id);
            } catch (DecryptException $e) {
                abort(404, 'Enkripsi tidak valid !');
            }

            $mahasiswa = Mahasiswa::findOrFail($this->edit_id);
            $this->user_id = $mahasiswa->user_id;
            $this->prodi_id = $mahasiswa->prodi_id;
            $this->nim = $mahasiswa->nim;
            $this->nama = $mahasiswa->nama;
            $this->email = $mahasiswa->email;
            $this->no_hp = $mahasiswa->no_hp;
            $this->angkatan = $mahasiswa->angkatan;
            $this->status = $mahasiswa->status;
        }
    }

    public function save()
    {
        $payload = $this->validate([
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('mahasiswa', 'user_id')->ignore($this->edit_id, 'id_mahasiswa')],
            'prodi_id' => ['required', 'exists:prodi,id_prodi'],
            'nim' => ['required', 'string', 'max:255', Rule::unique('mahasiswa', 'nim')->ignore($this->edit_id, 'id_mahasiswa')],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user_id)],
            'no_hp' => ['nullable', 'string', 'max:255'],
            'angkatan' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif', 'lulus', 'cuti'])],
        ], [
            'user_id.unique' => 'User sudah terhubung dengan mahasiswa lain.',
            'prodi_id.required' => 'Program studi wajib dipilih.',
            'nim.required' => 'NIM wajib diisi.',
            'nim.unique' => 'NIM sudah digunakan.',
            'nama.required' => 'Nama mahasiswa wajib diisi.',
            'email.required' => 'Email wajib diisi untuk akun login.',
            'email.unique' => 'Email sudah digunakan oleh user lain.',
            'angkatan.required' => 'Angkatan wajib diisi.',
            'angkatan.digits' => 'Angkatan harus terdiri dari 4 digit.',
            'status.required' => 'Status wajib dipilih.',
        ]);

        $payload['user_id'] = $payload['user_id'] ?: null;
        $payload['email'] = strtolower($payload['email']);
        $payload['nim'] = strtolower(trim($payload['nim']));

        if (User::where('username', $payload['nim'])->when($payload['user_id'], fn ($query) => $query->whereKeyNot($payload['user_id']))->exists()) {
            $this->addError('nim', 'NIM sudah digunakan sebagai username.');

            return;
        }

        DB::transaction(function () use (&$payload) {
            if ($payload['user_id']) {
                $user = User::findOrFail($payload['user_id']);
                $user->update([
                    'name' => $payload['nama'],
                    'username' => $payload['nim'],
                    'email' => $payload['email'],
                ]);
            } else {
                $user = User::create([
                    'name' => $payload['nama'],
                    'username' => $payload['nim'],
                    'email' => $payload['email'],
                    'password' => Hash::make($payload['nim']),
                ]);

                $payload['user_id'] = $user->id;
            }

            $user->assignRole('mahasiswa');

            Mahasiswa::updateOrCreate(['id_mahasiswa' => $this->edit_id], $payload);
        });

        session()->flash('success', $this->edit_id ? 'Berhasil mengubah data' : 'Berhasil menambah data');

        return $this->redirect(route('mahasiswa.index'), navigate: true);
    }
}; ?>

<form wire:submit="save">
    @csrf
    <div class="row">
        <div class="col-sm-12 col-lg-8">
            <div class="card">
                <div class="card-header"><h4>{{ $edit_id ? 'Edit Mahasiswa' : 'Tambah Mahasiswa' }}</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">User Login</label>
                            <select class="form-select" wire:model="user_id">
                                <option value="">Buat user baru otomatis</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->username }}</option>
                                @endforeach
                            </select>
                            @error('user_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Program Studi</label>
                            <select class="form-select" wire:model="prodi_id">
                                <option value="">Pilih prodi</option>
                                @foreach ($prodi as $item)
                                    <option value="{{ $item->id_prodi }}">{{ $item->kode }} - {{ $item->nama }}</option>
                                @endforeach
                            </select>
                            @error('prodi_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIM</label>
                            <input type="text" class="form-control" wire:model.live.debounce.500ms="nim">
                            @error('nim') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Angkatan</label>
                            <input type="number" class="form-control" wire:model="angkatan" placeholder="Contoh: 2026">
                            @error('angkatan') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Mahasiswa</label>
                        <input type="text" class="form-control" wire:model.live.debounce.500ms="nama">
                        @error('nama') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" wire:model="email">
                            @error('email') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">No HP</label>
                            <input type="text" class="form-control" wire:model="no_hp">
                            @error('no_hp') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" wire:model="status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                            <option value="lulus">Lulus</option>
                            <option value="cuti">Cuti</option>
                        </select>
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
