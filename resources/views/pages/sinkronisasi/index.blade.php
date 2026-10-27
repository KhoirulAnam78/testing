<?php

use App\Models\Prodi;
use App\Models\Semester;
use App\Support\Akademik\Sync\SinkronisasiDosen;
use App\Support\Akademik\Sync\SinkronisasiKurikulum;
use App\Support\Akademik\Sync\SinkronisasiMahasiswa;
use App\Support\Akademik\Sync\SinkronisasiMataKuliah;
use App\Support\Akademik\Sync\SinkronisasiSemester;
use App\Support\Akademik\Sync\SinkronisasiStatusRegistrasiMahasiswa;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $prodi_mata_kuliah_id = '';

    public string $prodi_dosen_id = '';

    public string $prodi_kurikulum_id = '';

    public string $kode_semester = '';

    public string $mulai_tahun = '';

    public string $semester_registrasi_id = '';

    public array $prodi = [];

    public array $semester = [];

    #[Locked]
    public array $hasil = [];

    public function mount(): void
    {
        $this->pastikanAkses('sinkronisasi-kelola:');
        $this->mulai_tahun = (string) now()->year;
        $this->prodi = Prodi::query()
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id_prodi', 'kode', 'nama'])
            ->map(fn (Prodi $prodi) => [
                'id' => (string) $prodi->id_prodi,
                'label' => $prodi->kode.' - '.$prodi->nama,
            ])->all();
        $this->semester = Semester::query()
            ->orderByDesc('tahun')
            ->orderByDesc('kode')
            ->get(['id_semester', 'kode', 'nama', 'tahun', 'is_aktif'])
            ->map(fn (Semester $semester) => [
                'id' => (string) $semester->id_semester,
                'label' => $semester->kode.' - '.ucfirst($semester->nama).' '.$semester->tahun.($semester->is_aktif ? ' (Aktif)' : ''),
                'aktif' => $semester->is_aktif,
            ])->all();
        $this->semester_registrasi_id = (string) (collect($this->semester)->firstWhere('aktif', true)['id'] ?? '');
        $this->muatHasil();
    }

    public function updatedSemesterRegistrasiId(): void
    {
        $this->muatHasilRegistrasi();
    }

    public function sinkronkanMataKuliah(SinkronisasiMataKuliah $sinkronisasi): void
    {
        $this->pastikanAkses('sinkronisasi-kelola:', 'mata-kuliah:tambah', 'mata-kuliah:edit');
        $prodi = $this->validasiProdi('prodi_mata_kuliah_id');
        $this->jalankan('mata_kuliah', fn () => $sinkronisasi->handle($prodi), 'Sinkronisasi mata kuliah gagal. Periksa koneksi dan format data API.', SinkronisasiMataKuliah::cacheKey());
    }

    public function sinkronkanMahasiswa(SinkronisasiMahasiswa $sinkronisasi): void
    {
        $this->pastikanAkses('sinkronisasi-kelola:', 'mahasiswa:tambah', 'mahasiswa:edit');
        $this->jalankan('mahasiswa', fn () => $sinkronisasi->handle(), 'Sinkronisasi mahasiswa gagal. Periksa koneksi, data lokal, dan format API.', SinkronisasiMahasiswa::cacheKey());
    }

    public function sinkronkanDosen(SinkronisasiDosen $sinkronisasi): void
    {
        $this->pastikanAkses('sinkronisasi-kelola:', 'dosen:tambah', 'dosen:edit');
        $prodi = $this->validasiProdi('prodi_dosen_id');
        $this->jalankan('dosen', fn () => $sinkronisasi->handle($prodi), 'Sinkronisasi dosen gagal. Periksa koneksi dan format data API.', SinkronisasiDosen::cacheKey());
    }

    public function sinkronkanKurikulum(SinkronisasiKurikulum $sinkronisasi): void
    {
        $this->pastikanAkses('sinkronisasi-kelola:', 'kurikulum:');
        $prodi = $this->validasiProdi('prodi_kurikulum_id');
        $this->jalankan('kurikulum', fn () => $sinkronisasi->handle($prodi), 'Sinkronisasi kurikulum gagal. Periksa koneksi, data lokal, dan format API.', SinkronisasiKurikulum::cacheKey());
    }

    public function sinkronkanSemester(SinkronisasiSemester $sinkronisasi): void
    {
        $this->pastikanAkses('sinkronisasi-kelola:', 'semester:');
        $this->kode_semester = trim($this->kode_semester);
        $this->validate([
            'kode_semester' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'mulai_tahun' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
        ], [
            'kode_semester.regex' => 'Kode semester harus terdiri dari 5 digit, contoh: 20261.',
            'mulai_tahun.required' => 'Tahun awal wajib diisi.',
            'mulai_tahun.integer' => 'Tahun awal harus berupa angka.',
            'mulai_tahun.digits' => 'Tahun awal harus terdiri dari 4 digit.',
            'mulai_tahun.min' => 'Tahun awal minimal 2000.',
            'mulai_tahun.max' => 'Tahun awal maksimal 2100.',
        ]);

        $this->jalankan(
            'semester',
            fn () => $sinkronisasi->handle($this->kode_semester !== '' ? $this->kode_semester : null, (int) $this->mulai_tahun),
            'Sinkronisasi semester gagal. Periksa koneksi dan format data API.',
            SinkronisasiSemester::cacheKey()
        );
    }

    public function sinkronkanStatusRegistrasi(SinkronisasiStatusRegistrasiMahasiswa $sinkronisasi): void
    {
        $this->pastikanAkses('sinkronisasi-kelola:', 'status-registrasi-mahasiswa:');
        $this->validate([
            'semester_registrasi_id' => ['required', Rule::exists('semester', 'id_semester')->whereNull('deleted_at')],
        ], [
            'semester_registrasi_id.required' => 'Semester wajib dipilih.',
            'semester_registrasi_id.exists' => 'Semester tidak ditemukan.',
        ]);

        $semester = Semester::query()->findOrFail((int) $this->semester_registrasi_id);
        $this->jalankan(
            'status_registrasi',
            fn () => $sinkronisasi->handle($semester),
            'Sinkronisasi status registrasi mahasiswa gagal karena kesalahan internal.',
            SinkronisasiStatusRegistrasiMahasiswa::cacheKey($semester->id_semester)
        );
    }

    private function muatHasil(): void
    {
        $this->hasil = [
            'mata_kuliah' => Cache::get(SinkronisasiMataKuliah::cacheKey()),
            'mahasiswa' => Cache::get(SinkronisasiMahasiswa::cacheKey()),
            'dosen' => Cache::get(SinkronisasiDosen::cacheKey()),
            'kurikulum' => Cache::get(SinkronisasiKurikulum::cacheKey()),
            'semester' => Cache::get(SinkronisasiSemester::cacheKey()),
        ];
        $this->muatHasilRegistrasi();
    }

    private function muatHasilRegistrasi(): void
    {
        $this->hasil['status_registrasi'] = $this->semester_registrasi_id !== ''
            ? Cache::get(SinkronisasiStatusRegistrasiMahasiswa::cacheKey((int) $this->semester_registrasi_id))
            : null;
    }

    private function validasiProdi(string $property): Prodi
    {
        $this->validate([
            $property => [
                'required',
                Rule::exists('prodi', 'id_prodi')->where(fn (Builder $query) => $query
                    ->where('status', 'aktif')
                    ->whereNull('deleted_at')),
            ],
        ], [
            $property.'.required' => 'Program studi wajib dipilih.',
            $property.'.exists' => 'Program studi aktif tidak ditemukan.',
        ]);

        return Prodi::query()->findOrFail((int) $this->{$property});
    }

    private function jalankan(string $key, callable $action, string $pesanGagal, string $cacheKey): void
    {
        try {
            $this->hasil[$key] = $action();
            $this->dispatch('notify', message: ['status' => 'success', 'message' => $this->hasil[$key]['pesan']]);
        } catch (DomainException $exception) {
            $this->simpanGagal($key, $cacheKey, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->simpanGagal($key, $cacheKey, $pesanGagal);
        }
    }

    private function simpanGagal(string $key, string $cacheKey, string $pesan): void
    {
        $this->hasil[$key] = [
            'status' => 'error',
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'pesan' => $pesan,
        ];
        Cache::put($cacheKey, $this->hasil[$key], now()->addDays(30));
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }

    private function pastikanAkses(string ...$permissions): void
    {
        abort_unless(collect($permissions)->every(fn (string $permission) => auth()->user()?->can($permission)), 403);
    }
}; ?>

<div>
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
            <h4 class="mb-sm-0">Dashboard Sinkronisasi</h4>
            <div class="text-muted small mt-1">Jalankan dan pantau sinkronisasi master data dari satu halaman.</div>
        </div>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item active">Sinkronisasi</li></ol>
    </div>

    <livewire:alert />

    <div class="row g-3">
        <div class="col-xl-6">
            @php($status = $hasil['mata_kuliah'] ?? null)
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start gap-2">
                    <div><h5 class="mb-1">Mata Kuliah</h5><div class="text-muted small">Sinkronisasi per program studi.</div></div>
                    <span class="badge {{ ($status['status'] ?? null) === 'success' ? 'bg-success-subtle text-success' : (($status['status'] ?? null) === 'error' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }}">{{ ($status['status'] ?? null) === 'success' ? 'Berhasil' : (($status['status'] ?? null) === 'error' ? 'Gagal' : 'Belum pernah') }}</span>
                </div>
                <div class="card-body">
                    @if (auth()->user()?->can('mata-kuliah:tambah') && auth()->user()?->can('mata-kuliah:edit'))
                        <form wire:submit="sinkronkanMataKuliah" class="row g-2 align-items-end">
                            <div class="col-md-8"><label for="sync-mata-kuliah-prodi" class="form-label">Program Studi</label><select id="sync-mata-kuliah-prodi" wire:model="prodi_mata_kuliah_id" class="form-select @error('prodi_mata_kuliah_id') is-invalid @enderror"><option value="">Pilih program studi</option>@foreach ($prodi as $item)<option value="{{ $item['id'] }}">{{ $item['label'] }}</option>@endforeach</select>@error('prodi_mata_kuliah_id') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-md-4"><button class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="sinkronkanMataKuliah"><span wire:loading.remove wire:target="sinkronkanMataKuliah"><i class="ri-refresh-line"></i> Sinkronkan</span><span wire:loading wire:target="sinkronkanMataKuliah">Memproses...</span></button></div>
                        </form>
                    @else
                        <div class="text-muted small">Tidak memiliki izin sinkronisasi mata kuliah.</div>
                    @endif
                    @if ($status)<div class="alert {{ $status['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mt-3 mb-0"><div class="small fw-semibold">Selesai {{ $status['selesai_pada'] }}</div><div>{{ $status['pesan'] }}</div></div>@endif
                </div>
                @can('mata-kuliah:')<div class="card-footer text-end"><a href="{{ route('mata-kuliah.index') }}" wire:navigate class="btn btn-light btn-sm">Lihat Data</a></div>@endcan
            </div>
        </div>

        <div class="col-xl-6">
            @php($status = $hasil['mahasiswa'] ?? null)
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start gap-2"><div><h5 class="mb-1">Mahasiswa</h5><div class="text-muted small">Sinkronisasi seluruh mahasiswa beserta akun dan foto.</div></div><span class="badge {{ ($status['status'] ?? null) === 'success' ? 'bg-success-subtle text-success' : (($status['status'] ?? null) === 'error' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }}">{{ ($status['status'] ?? null) === 'success' ? 'Berhasil' : (($status['status'] ?? null) === 'error' ? 'Gagal' : 'Belum pernah') }}</span></div>
                <div class="card-body">
                    @if (auth()->user()?->can('mahasiswa:tambah') && auth()->user()?->can('mahasiswa:edit'))<button type="button" class="btn btn-primary" wire:click="sinkronkanMahasiswa" wire:loading.attr="disabled" wire:target="sinkronkanMahasiswa"><span wire:loading.remove wire:target="sinkronkanMahasiswa"><i class="ri-refresh-line"></i> Sinkronkan Mahasiswa</span><span wire:loading wire:target="sinkronkanMahasiswa">Memproses...</span></button>@else<div class="text-muted small">Tidak memiliki izin sinkronisasi mahasiswa.</div>@endif
                    @if ($status)<div class="alert {{ $status['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mt-3 mb-0"><div class="small fw-semibold">Selesai {{ $status['selesai_pada'] }}</div><div>{{ $status['pesan'] }}</div></div>@endif
                </div>
                @can('mahasiswa:')<div class="card-footer text-end"><a href="{{ route('mahasiswa.index') }}" wire:navigate class="btn btn-light btn-sm">Lihat Data</a></div>@endcan
            </div>
        </div>

        <div class="col-xl-6">
            @php($status = $hasil['dosen'] ?? null)
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start gap-2"><div><h5 class="mb-1">Dosen</h5><div class="text-muted small">Sinkronisasi dosen per program studi.</div></div><span class="badge {{ ($status['status'] ?? null) === 'success' ? 'bg-success-subtle text-success' : (($status['status'] ?? null) === 'error' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }}">{{ ($status['status'] ?? null) === 'success' ? 'Berhasil' : (($status['status'] ?? null) === 'error' ? 'Gagal' : 'Belum pernah') }}</span></div>
                <div class="card-body">
                    @if (auth()->user()?->can('dosen:tambah') && auth()->user()?->can('dosen:edit'))
                        <form wire:submit="sinkronkanDosen" class="row g-2 align-items-end"><div class="col-md-8"><label for="sync-dosen-prodi" class="form-label">Program Studi</label><select id="sync-dosen-prodi" wire:model="prodi_dosen_id" class="form-select @error('prodi_dosen_id') is-invalid @enderror"><option value="">Pilih program studi</option>@foreach ($prodi as $item)<option value="{{ $item['id'] }}">{{ $item['label'] }}</option>@endforeach</select>@error('prodi_dosen_id') <div class="invalid-feedback">{{ $message }}</div> @enderror</div><div class="col-md-4"><button class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="sinkronkanDosen"><span wire:loading.remove wire:target="sinkronkanDosen"><i class="ri-refresh-line"></i> Sinkronkan</span><span wire:loading wire:target="sinkronkanDosen">Memproses...</span></button></div></form>
                    @else<div class="text-muted small">Tidak memiliki izin sinkronisasi dosen.</div>@endif
                    @if ($status)<div class="alert {{ $status['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mt-3 mb-0"><div class="small fw-semibold">Selesai {{ $status['selesai_pada'] }}</div><div>{{ $status['pesan'] }}</div></div>@endif
                </div>
                @can('dosen:')<div class="card-footer text-end"><a href="{{ route('dosen.index') }}" wire:navigate class="btn btn-light btn-sm">Lihat Data</a></div>@endcan
            </div>
        </div>

        <div class="col-xl-6">
            @php($status = $hasil['kurikulum'] ?? null)
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start gap-2"><div><h5 class="mb-1">Kurikulum + Mata Kuliah Kurikulum</h5><div class="text-muted small">Satu proses atomik untuk kurikulum dan pemetaan mata kuliahnya.</div></div><span class="badge {{ ($status['status'] ?? null) === 'success' ? 'bg-success-subtle text-success' : (($status['status'] ?? null) === 'error' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }}">{{ ($status['status'] ?? null) === 'success' ? 'Berhasil' : (($status['status'] ?? null) === 'error' ? 'Gagal' : 'Belum pernah') }}</span></div>
                <div class="card-body">
                    @can('kurikulum:')<form wire:submit="sinkronkanKurikulum" class="row g-2 align-items-end"><div class="col-md-8"><label for="sync-kurikulum-prodi" class="form-label">Program Studi</label><select id="sync-kurikulum-prodi" wire:model="prodi_kurikulum_id" class="form-select @error('prodi_kurikulum_id') is-invalid @enderror"><option value="">Pilih program studi</option>@foreach ($prodi as $item)<option value="{{ $item['id'] }}">{{ $item['label'] }}</option>@endforeach</select>@error('prodi_kurikulum_id') <div class="invalid-feedback">{{ $message }}</div> @enderror</div><div class="col-md-4"><button class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="sinkronkanKurikulum"><span wire:loading.remove wire:target="sinkronkanKurikulum"><i class="ri-refresh-line"></i> Sinkronkan</span><span wire:loading wire:target="sinkronkanKurikulum">Memproses...</span></button></div></form>@else<div class="text-muted small">Tidak memiliki izin sinkronisasi kurikulum.</div>@endcan
                    @if ($status)<div class="alert {{ $status['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mt-3 mb-0"><div class="small fw-semibold">Selesai {{ $status['selesai_pada'] }}</div><div>{{ $status['pesan'] }}</div></div>@endif
                </div>
                @can('kurikulum:')<div class="card-footer text-end"><a href="{{ route('kurikulum.index') }}" wire:navigate class="btn btn-light btn-sm">Lihat Data</a></div>@endcan
            </div>
        </div>

        <div class="col-xl-6">
            @php($status = $hasil['semester'] ?? null)
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start gap-2"><div><h5 class="mb-1">Semester</h5><div class="text-muted small">Kode semester opsional; tanpa kode memakai rentang tahun.</div></div><span class="badge {{ ($status['status'] ?? null) === 'success' ? 'bg-success-subtle text-success' : (($status['status'] ?? null) === 'error' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }}">{{ ($status['status'] ?? null) === 'success' ? 'Berhasil' : (($status['status'] ?? null) === 'error' ? 'Gagal' : 'Belum pernah') }}</span></div>
                <div class="card-body">
                    @can('semester:')<form wire:submit="sinkronkanSemester" class="row g-2 align-items-end"><div class="col-md-4"><label for="sync-kode-semester" class="form-label">Kode Semester</label><input id="sync-kode-semester" type="text" inputmode="numeric" maxlength="5" wire:model="kode_semester" class="form-control @error('kode_semester') is-invalid @enderror" placeholder="20261">@error('kode_semester') <div class="invalid-feedback">{{ $message }}</div> @enderror</div><div class="col-md-4"><label for="sync-mulai-tahun" class="form-label">Tahun Awal</label><input id="sync-mulai-tahun" type="number" min="2000" max="2100" wire:model="mulai_tahun" class="form-control @error('mulai_tahun') is-invalid @enderror">@error('mulai_tahun') <div class="invalid-feedback">{{ $message }}</div> @enderror</div><div class="col-md-4"><button class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="sinkronkanSemester"><span wire:loading.remove wire:target="sinkronkanSemester"><i class="ri-refresh-line"></i> Sinkronkan</span><span wire:loading wire:target="sinkronkanSemester">Memproses...</span></button></div></form>@else<div class="text-muted small">Tidak memiliki izin sinkronisasi semester.</div>@endcan
                    @if ($status)<div class="alert {{ $status['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mt-3 mb-0"><div class="small fw-semibold">Selesai {{ $status['selesai_pada'] }}</div><div>{{ $status['pesan'] }}</div></div>@endif
                </div>
                @can('semester:')<div class="card-footer text-end"><a href="{{ route('semester.index') }}" wire:navigate class="btn btn-light btn-sm">Lihat Data</a></div>@endcan
            </div>
        </div>

        <div class="col-xl-6">
            @php($status = $hasil['status_registrasi'] ?? null)
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start gap-2"><div><h5 class="mb-1">Status Registrasi Mahasiswa</h5><div class="text-muted small">Sinkronisasi registrasi untuk semester terpilih.</div></div><span class="badge {{ ($status['status'] ?? null) === 'success' ? 'bg-success-subtle text-success' : (($status['status'] ?? null) === 'error' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }}">{{ ($status['status'] ?? null) === 'success' ? 'Berhasil' : (($status['status'] ?? null) === 'error' ? 'Gagal' : 'Belum pernah') }}</span></div>
                <div class="card-body">
                    @can('status-registrasi-mahasiswa:')<form wire:submit="sinkronkanStatusRegistrasi" class="row g-2 align-items-end"><div class="col-md-8"><label for="sync-status-semester" class="form-label">Semester</label><select id="sync-status-semester" wire:model.live="semester_registrasi_id" class="form-select @error('semester_registrasi_id') is-invalid @enderror"><option value="">Pilih semester</option>@foreach ($semester as $item)<option value="{{ $item['id'] }}">{{ $item['label'] }}</option>@endforeach</select>@error('semester_registrasi_id') <div class="invalid-feedback">{{ $message }}</div> @enderror</div><div class="col-md-4"><button class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="sinkronkanStatusRegistrasi"><span wire:loading.remove wire:target="sinkronkanStatusRegistrasi"><i class="ri-refresh-line"></i> Sinkronkan</span><span wire:loading wire:target="sinkronkanStatusRegistrasi">Memproses...</span></button></div></form>@else<div class="text-muted small">Tidak memiliki izin sinkronisasi status registrasi.</div>@endcan
                    @if ($status)<div class="alert {{ $status['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mt-3 mb-0"><div class="small fw-semibold">Selesai {{ $status['selesai_pada'] }}</div><div>{{ $status['pesan'] }}</div></div>@endif
                </div>
                @can('status-registrasi-mahasiswa:')<div class="card-footer text-end"><a href="{{ route('status-registrasi-mahasiswa.index') }}" wire:navigate class="btn btn-light btn-sm">Lihat Data</a></div>@endcan
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start gap-2"><div><h5 class="mb-1">Program Studi</h5><div class="text-muted small">Data program studi tetap dikelola pada halaman sumber.</div></div><span class="badge bg-warning-subtle text-warning">Belum tersedia</span></div>
                <div class="card-body"><div class="alert alert-warning mb-0"><div class="fw-semibold">Sinkronisasi belum tersedia</div><div>Kontrak endpoint program studi belum tersedia.</div></div></div>
                @can('prodi:')<div class="card-footer text-end"><a href="{{ route('prodi.index') }}" wire:navigate class="btn btn-light btn-sm">Lihat Data</a></div>@endcan
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-1">Sinkronisasi Kelas</h5><div class="text-muted small">Pengelolaan kelas lokal, pembagian peserta, DPNA, dan monitoring.</div></div>
                <div class="card-body"><div class="alert alert-info mb-0">Sinkronisasi API kelas kuliah dan anggota kelas ditunda. Pengelolaan kelas lokal tetap tersedia.</div></div>
                @can('sinkronisasi-kelas:')<div class="card-footer text-end"><a href="{{ route('sinkronisasi-kelas.index') }}" wire:navigate class="btn btn-light btn-sm">Buka Sinkronisasi Kelas</a></div>@endcan
            </div>
        </div>
    </div>
</div>