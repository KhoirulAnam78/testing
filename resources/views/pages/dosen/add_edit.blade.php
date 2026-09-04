<?php

use App\Models\Dosen;
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

    public $nidn;

    public $nip;

    public $nama;

    public $email;

    public $no_hp;

    public $gelar_depan;

    public $gelar_belakang;

    public $bidang_keahlian;

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

            $dosen = Dosen::findOrFail($this->edit_id);
            $this->user_id = $dosen->user_id;
            $this->prodi_id = $dosen->prodi_id;
            $this->nidn = $dosen->nidn;
            $this->nip = $dosen->nip;
            $this->nama = $dosen->nama;
            $this->email = $dosen->email;
            $this->no_hp = $dosen->no_hp;
            $this->gelar_depan = $dosen->gelar_depan;
            $this->gelar_belakang = $dosen->gelar_belakang;
            $this->bidang_keahlian = $dosen->bidang_keahlian;
            $this->status = $dosen->status;
        }
    }

    public function save()
    {
        $payload = $this->validate([
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('dosen', 'user_id')->ignore($this->edit_id, 'id_dosen')],
            'prodi_id' => ['nullable', 'exists:prodi,id_prodi'],
            'nidn' => ['nullable', 'string', 'max:255', Rule::unique('dosen', 'nidn')->ignore($this->edit_id, 'id_dosen')],
            'nip' => ['nullable', 'string', 'max:255', Rule::unique('dosen', 'nip')->ignore($this->edit_id, 'id_dosen')],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user_id)],
            'no_hp' => ['nullable', 'string', 'max:255'],
            'gelar_depan' => ['nullable', 'string', 'max:255'],
            'gelar_belakang' => ['nullable', 'string', 'max:255'],
            'bidang_keahlian' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'user_id.unique' => 'User sudah terhubung dengan dosen lain.',
            'prodi_id.exists' => 'Program studi tidak valid.',
            'nidn.unique' => 'NIDN sudah digunakan.',
            'nip.unique' => 'NIP sudah digunakan.',
            'nama.required' => 'Nama dosen wajib diisi.',
            'email.required' => 'Email wajib diisi untuk akun login.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh user lain.',
            'status.required' => 'Status wajib dipilih.',
        ]);

        $payload['user_id'] = $payload['user_id'] ?: null;
        $payload['prodi_id'] = $payload['prodi_id'] ?: null;
        $payload['email'] = strtolower($payload['email']);
        $username = strtolower(trim((string) ($payload['nip'] ?: $payload['nidn'])));

        if ($username === '') {
            $this->addError('nip', 'NIP atau NIDN wajib diisi untuk username.');

            return;
        }

        if (User::where('username', $username)->when($payload['user_id'], fn ($query) => $query->whereKeyNot($payload['user_id']))->exists()) {
            $this->addError('nip', 'NIP atau NIDN sudah digunakan sebagai username.');

            return;
        }
        DB::transaction(function () use (&$payload, $username) {
            if ($payload['user_id']) {
                $user = User::findOrFail($payload['user_id']);
                $user->update([
                    'name' => $payload['nama'],
                    'username' => $username,
                    'email' => $payload['email'],
                ]);
            } else {
                $user = User::create([
                    'name' => $payload['nama'],
                    'username' => $username,
                    'email' => $payload['email'],
                    'password' => Hash::make($username),
                ]);

                $payload['user_id'] = $user->id;
            }

            $user->assignRole('dosen');

            Dosen::updateOrCreate(['id_dosen' => $this->edit_id], $payload);
        });

        session()->flash('success', $this->edit_id ? 'Berhasil mengubah data' : 'Berhasil menambah data');

        return $this->redirect(route('dosen.index'), navigate: true);
    }
}; ?>

<form wire:submit="save">
    @csrf
    <div class="row">
        <div class="col-sm-12 col-lg-8">
            <div class="card">
                <div class="card-header"><h4>{{ $edit_id ? 'Edit Dosen' : 'Tambah Dosen' }}</h4></div>
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
                                <option value="">Lintas prodi</option>
                                @foreach ($prodi as $item)
                                    <option value="{{ $item->id_prodi }}">{{ $item->kode }} - {{ $item->nama }}</option>
                                @endforeach
                            </select>
                            @error('prodi_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIDN</label>
                            <input type="text" class="form-control" wire:model.live.debounce.500ms="nidn">
                            @error('nidn') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIP</label>
                            <input type="text" class="form-control" wire:model.live.debounce.500ms="nip">
                            @error('nip') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Dosen</label>
                        <input type="text" class="form-control" wire:model.live.debounce.500ms="nama">
                        @error('nama') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gelar Depan</label>
                            <input type="text" class="form-control" wire:model="gelar_depan" placeholder="Contoh: dr.">
                            @error('gelar_depan') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gelar Belakang</label>
                            <input type="text" class="form-control" wire:model="gelar_belakang" placeholder="Contoh: M.Kes.">
                            @error('gelar_belakang') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                        </div>
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
                        <label class="form-label">Bidang Keahlian</label>
                        <input type="text" class="form-control" wire:model="bidang_keahlian">
                        @error('bidang_keahlian') <div class="text-sm text-danger">{{ $message }}</div> @enderror
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
        </div>
    </div>

    <div style="position: fixed; bottom: 50px; left: 0; width: 100%; display: flex; justify-content: center; z-index: 1050;">
        <button type="submit" class="btn btn-primary shadow d-flex align-items-center gap-2 fab-save" wire:loading.attr="disabled">
            <span wire:loading.remove><i class="ri-save-line"></i> SIMPAN</span>
            <span wire:loading>Loading...</span>
        </button>
    </div>
</form>
