<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public $foto;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
    }

    public function updateProfile(): void
    {
        $user = auth()->user();
        $this->email = strtolower(trim($this->email));
        $this->username = trim($this->username);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'foto.image' => 'File foto harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, JPEG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        unset($validated['foto']);
        $validated['email'] = strtolower($validated['email']);

        if ($user->email !== $validated['email']) {
            $user->email_verified_at = null;
        }

        $fotoLama = $user->foto_profil;
        $fotoBaru = $this->foto?->store('foto-profil', 'public');

        if ($fotoBaru) {
            $validated['foto_profil'] = $fotoBaru;
        }

        try {
            $user->update($validated);
        } catch (Throwable $exception) {
            if ($fotoBaru) {
                Storage::disk('public')->delete($fotoBaru);
            }

            throw $exception;
        }

        if ($fotoBaru && $fotoLama) {
            Storage::disk('public')->delete($fotoLama);
        }

        $this->reset('foto');
        $this->dispatch('profile-updated');
        session()->flash('profile-success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
        ]);

        auth()->user()->update(['password' => $validated['password']]);
        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('password-success', 'Password berhasil diperbarui.');
    }
}; ?>

<div>
    <div class="row align-items-center mb-4">
        <div class="col">
            <h4 class="mb-1">Profil & Akun</h4>
            <p class="text-muted mb-0">Kelola foto, identitas akun, dan password Anda.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <form wire:submit="updateProfile">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Informasi Akun</h5>
                        <p class="text-muted mb-0">Informasi ini digunakan saat Anda mengakses sistem.</p>
                    </div>
                    <div class="card-body">
                        @if (session('profile-success'))
                            <div class="alert alert-success" role="alert">{{ session('profile-success') }}</div>
                        @endif

                        <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 mb-4">
                            <div class="flex-shrink-0">
                                @if ($foto && str_starts_with($foto->getMimeType(), 'image/'))
                                    <img src="{{ $foto->temporaryUrl() }}" class="rounded-circle object-fit-cover border"
                                        width="96" height="96" alt="Pratinjau foto profil">
                                @elseif (auth()->user()->foto_profil)
                                    <img src="{{ asset('storage/'.auth()->user()->foto_profil) }}"
                                        class="rounded-circle object-fit-cover border" width="96" height="96"
                                        alt="Foto profil {{ auth()->user()->name }}">
                                @else
                                    <span class="avatar-title rounded-circle bg-primary-subtle text-primary" style="width: 96px; height: 96px;">
                                        <i class="ri-user-3-line fs-1"></i>
                                    </span>
                                @endif
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label" for="foto">Foto Profil</label>
                                <input type="file" wire:model="foto" id="foto"
                                    class="form-control @error('foto') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">JPG, JPEG, PNG, atau WebP. Maksimal 2 MB.</div>
                                @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div wire:loading wire:target="foto" class="text-muted small mt-2">Mengunggah foto...</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Nama</label>
                                <input type="text" wire:model="name" id="name"
                                    class="form-control @error('name') is-invalid @enderror" autocomplete="name">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" wire:model="username" id="username"
                                    class="form-control @error('username') is-invalid @enderror" autocomplete="username">
                                @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" wire:model="email" id="email"
                                    class="form-control @error('email') is-invalid @enderror" autocomplete="email">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updateProfile,foto">
                            <span wire:loading.remove wire:target="updateProfile"><i class="ri-save-line me-1"></i> Simpan Profil</span>
                            <span wire:loading wire:target="updateProfile">Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <form wire:submit="updatePassword">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Ubah Password</h5>
                        <p class="text-muted mb-0">Gunakan password kuat dan berbeda dari sebelumnya.</p>
                    </div>
                    <div class="card-body">
                        @if (session('password-success'))
                            <div class="alert alert-success" role="alert">{{ session('password-success') }}</div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label" for="current_password">Password Saat Ini</label>
                            <input type="password" wire:model="current_password" id="current_password"
                                class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                            @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password Baru</label>
                            <input type="password" wire:model="password" id="password"
                                class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="form-label" for="password_confirmation">Konfirmasi Password Baru</label>
                            <input type="password" wire:model="password_confirmation" id="password_confirmation"
                                class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updatePassword">
                            <span wire:loading.remove wire:target="updatePassword"><i class="ri-lock-password-line me-1"></i> Ubah Password</span>
                            <span wire:loading wire:target="updatePassword">Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>