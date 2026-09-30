<?php

use App\Models\ApiIntegrasi;
use App\Support\Akademik\AkademikClient;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $nama = 'API Akademik';

    public string $base_url = '';

    public string $login_url = '';

    public string $username = '';

    public string $password = '';

    public bool $is_aktif = false;

    public $timeout = 30;

    public bool $sudah_tersimpan = false;

    public ?array $status_token = null;

    public ?string $hasil_tes = null;

    public bool $tes_berhasil = false;

    public function mount(): void
    {
        $this->pastikanBerhak();

        $integrasi = ApiIntegrasi::query()->find(1);

        if (! $integrasi) {
            return;
        }

        $this->nama = $integrasi->nama;
        $this->base_url = $integrasi->base_url;
        $this->login_url = $integrasi->login_url;
        $this->username = $integrasi->username;
        $this->is_aktif = $integrasi->is_aktif;
        $this->timeout = $integrasi->timeout;
        $this->sudah_tersimpan = true;
        $this->status_token = app(AkademikClient::class)->cachedTokenStatus();
    }

    public function save(): void
    {
        $this->pastikanBerhak();

        $this->nama = trim($this->nama);
        $this->base_url = rtrim(trim($this->base_url), '/');
        $this->login_url = trim($this->login_url);
        $this->username = trim($this->username);

        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url:https', 'max:2048'],
            'login_url' => ['required', 'url:https', 'max:2048'],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$this->sudah_tersimpan ? 'nullable' : 'required', 'string', 'max:4096'],
            'is_aktif' => ['boolean'],
            'timeout' => ['required', 'integer', 'min:1', 'max:120'],
        ], [
            'nama.required' => 'Nama integrasi wajib diisi.',
            'base_url.required' => 'Base URL wajib diisi.',
            'base_url.url' => 'Base URL harus berupa URL HTTPS yang valid.',
            'login_url.required' => 'URL login wajib diisi.',
            'login_url.url' => 'URL login harus berupa URL HTTPS yang valid.',
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi saat konfigurasi pertama.',
            'timeout.required' => 'Timeout wajib diisi.',
            'timeout.integer' => 'Timeout harus berupa angka.',
            'timeout.min' => 'Timeout minimal 1 detik.',
            'timeout.max' => 'Timeout maksimal 120 detik.',
        ]);

        if ($validated['password'] === '') {
            unset($validated['password']);
        }

        $integrasi = ApiIntegrasi::query()->find(1) ?? new ApiIntegrasi;
        $integrasi->id = 1;
        $integrasi->fill($validated)->save();

        app(AkademikClient::class)->forgetToken();

        $this->reset('password');
        $this->sudah_tersimpan = true;
        $this->status_token = null;
        $this->hasil_tes = null;
        session()->flash('success', 'Pengaturan API integrasi berhasil disimpan.');
    }

    public function tesLogin(): void
    {
        $this->pastikanBerhak();
        $this->hasil_tes = null;
        $this->tes_berhasil = false;

        $integrasi = ApiIntegrasi::query()->find(1);

        if (! $integrasi) {
            $this->hasil_tes = 'Simpan konfigurasi API sebelum tes login.';

            return;
        }

        try {
            $this->status_token = app(AkademikClient::class)->login($integrasi);
            $this->tes_berhasil = true;
            $this->hasil_tes = 'Login API berhasil. Token tersimpan sementara di cache.';
        } catch (DomainException $exception) {
            $this->status_token = null;
            $this->hasil_tes = $exception->getMessage();
        } catch (Throwable) {
            $this->status_token = null;
            $this->hasil_tes = 'Tes login gagal karena terjadi kesalahan internal.';
        }
    }

    private function pastikanBerhak(): void
    {
        abort_unless(auth()->user()?->can('api-integrasi:'), 403);
    }
};

?>

<div>
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">API Integrasi</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item">Pengaturan Aplikasi</li>
                        <li class="breadcrumb-item active">API Integrasi</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <form wire:submit="save">
        <div class="row justify-content-center">
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-1">Akun API Akademik</h5>
                        <p class="text-muted mb-0">Simpan alamat API dan akun integrasi, lalu tes login untuk memperoleh token.</p>
                    </div>
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
                        @endif

                        @if ($hasil_tes)
                            <div class="alert {{ $tes_berhasil ? 'alert-success' : 'alert-danger' }}" role="alert">
                                {{ $hasil_tes }}
                            </div>
                        @endif

                        @if ($status_token)
                            <div class="alert alert-success" role="status">
                                <div class="fw-semibold mb-1">Token tersedia di cache</div>
                                <div>Token: <code>{{ $status_token['masked_token'] }}</code></div>
                                <div>Tipe: {{ $status_token['token_type'] }}</div>
                                <div>Kedaluwarsa: {{ $status_token['expires_at'] }}</div>
                                <div>Cache aktif sampai: {{ $status_token['cached_until'] }} ({{ max(1, (int) ceil($status_token['ttl_seconds'] / 60)) }} menit)</div>
                            </div>
                        @endif

                        <div class="alert alert-info" role="alert">
                            Password dienkripsi memakai <code>APP_KEY</code> jika <code>APP_KEY</code> berubah maka inputkan ulang password.
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="nama">Nama Integrasi</label>
                            <input type="text" id="nama" wire:model="nama"
                                class="form-control @error('nama') is-invalid @enderror"
                                placeholder="Contoh: API Akademik Kampus">
                            @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="base_url">Base URL</label>
                                <input type="url" id="base_url" wire:model="base_url"
                                    class="form-control @error('base_url') is-invalid @enderror"
                                    placeholder="https://api.example.ac.id">
                                @error('base_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="login_url">URL Login</label>
                                <input type="url" id="login_url" wire:model="login_url"
                                    class="form-control @error('login_url') is-invalid @enderror"
                                    placeholder="https://api.example.ac.id/login">
                                @error('login_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" id="username" wire:model="username"
                                    class="form-control @error('username') is-invalid @enderror"
                                    autocomplete="off">
                                @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="password">Password</label>
                                <input type="password" id="password" wire:model="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    autocomplete="new-password"
                                    placeholder="{{ $sudah_tersimpan ? 'Kosongkan untuk mempertahankan password lama' : 'Masukkan password API' }}">
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if ($sudah_tersimpan)
                                    <div class="form-text">Password sudah tersimpan. Isi hanya jika ingin menggantinya.</div>
                                @endif
                            </div>
                        </div>

                        <div class="row align-items-end">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="timeout">Timeout Request</label>
                                <div class="input-group">
                                    <input type="number" id="timeout" wire:model="timeout" min="1" max="120"
                                        class="form-control @error('timeout') is-invalid @enderror">
                                    <span class="input-group-text">detik</span>
                                    @error('timeout') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_aktif" wire:model="is_aktif">
                                    <label class="form-check-label" for="is_aktif">Integrasi aktif</label>
                                </div>
                                <div class="form-text">Status aktif diperlukan untuk tes login dan request API.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="position: fixed; bottom: 50px; left: 0; width: 100%; display: flex; justify-content: center; gap: 0.5rem; z-index: 1050;">
            <button type="button" class="btn btn-info shadow d-flex align-items-center gap-2"
                wire:click="tesLogin" wire:loading.attr="disabled" wire:target="save,tesLogin"
                @disabled(! $sudah_tersimpan)>
                <span wire:loading.remove wire:target="tesLogin"><i class="ri-key-2-line"></i> TES & AMBIL TOKEN</span>
                <span wire:loading wire:target="tesLogin">Menghubungkan...</span>
            </button>
            <button type="submit" class="btn btn-primary shadow d-flex align-items-center gap-2 fab-save"
                wire:loading.attr="disabled" wire:target="save,tesLogin">
                <span wire:loading.remove wire:target="save"><i class="ri-save-line"></i> SIMPAN</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>