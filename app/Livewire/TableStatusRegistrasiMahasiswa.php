<?php

namespace App\Livewire;

use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StatusRegistrasiMahasiswa;
use App\Support\Akademik\Sync\SinkronisasiStatusRegistrasiMahasiswa;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

final class TableStatusRegistrasiMahasiswa extends Component
{
    use WithPagination;

    #[Locked]
    public $edit_mahasiswa_id = null;

    #[Locked]
    public $edit_semester_id = null;

    public string $edit_status = 'aktif';

    public $filter_semester_id = '';

    public $filter_prodi_id = '';

    public $filter_angkatan = '';

    public string $filter_status = '';

    public string $filter_pencarian = '';

    #[Locked]
    public ?array $hasil_sinkronisasi = null;

    protected string $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $this->pastikanBerhak();
        $this->filter_semester_id = Semester::where('is_aktif', true)->value('id_semester') ?: '';
        $this->muatHasilSinkronisasi();
    }

    public function updated($property): void
    {
        if (str_starts_with($property, 'filter_')) {
            $this->resetPage();
            $this->tutupPengaturan();
        }

        if ($property === 'filter_semester_id') {
            $this->muatHasilSinkronisasi();
        }
    }

    public function bukaPengaturan(int $id): void
    {
        $this->pastikanBerhak();
        $semester = Semester::findOrFail($this->filter_semester_id);
        $mahasiswa = Mahasiswa::findOrFail($id);

        if (! StatusRegistrasiMahasiswa::dapatDitetapkanUntuk($mahasiswa, $semester)) {
            throw ValidationException::withMessages([
                'edit_status' => 'Mahasiswa lulus tidak dapat diberi registrasi pada semester aktif.',
            ]);
        }

        $registrasi = StatusRegistrasiMahasiswa::query()
            ->where('semester_id', $semester->id_semester)
            ->where('mahasiswa_id', $mahasiswa->id_mahasiswa)
            ->first();

        $this->resetValidation();
        $this->edit_mahasiswa_id = $mahasiswa->id_mahasiswa;
        $this->edit_semester_id = $semester->id_semester;
        $this->edit_status = $registrasi?->status ?? 'aktif';
    }

    public function tutupPengaturan(): void
    {
        $this->reset(['edit_mahasiswa_id', 'edit_semester_id', 'edit_status']);
        $this->edit_status = 'aktif';
        $this->resetValidation();
    }

    #[On('status-registrasi-mahasiswa-diperbarui')]
    public function refreshTable(): void {}

    public function simpanIndividual(): void
    {
        $this->pastikanBerhak();
        $this->validate([
            'edit_status' => ['required', Rule::in(StatusRegistrasiMahasiswa::STATUS)],
        ], [
            'edit_status.required' => 'Status registrasi wajib dipilih.',
            'edit_status.in' => 'Status registrasi tidak valid.',
        ]);

        DB::transaction(function (): void {
            $semester = Semester::query()->lockForUpdate()->findOrFail($this->edit_semester_id);
            $mahasiswa = Mahasiswa::query()->lockForUpdate()->findOrFail($this->edit_mahasiswa_id);

            if (! StatusRegistrasiMahasiswa::dapatDitetapkanUntuk($mahasiswa, $semester)) {
                throw ValidationException::withMessages([
                    'edit_status' => 'Mahasiswa lulus tidak dapat diberi registrasi pada semester aktif.',
                ]);
            }

            StatusRegistrasiMahasiswa::updateOrCreate([
                'mahasiswa_id' => $mahasiswa->id_mahasiswa,
                'semester_id' => $semester->id_semester,
            ], [
                'status' => $this->edit_status,
            ]);
        });

        $this->tutupPengaturan();
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Status registrasi mahasiswa berhasil diperbarui.',
        ]);
    }

    public function sinkronkan(SinkronisasiStatusRegistrasiMahasiswa $sinkronisasi): void
    {
        $this->pastikanBerhak();
        $this->validate([
            'filter_semester_id' => ['required', Rule::exists('semester', 'id_semester')->whereNull('deleted_at')],
        ], [
            'filter_semester_id.required' => 'Semester wajib dipilih sebelum sinkronisasi.',
            'filter_semester_id.exists' => 'Semester tidak ditemukan.',
        ]);

        $semester = Semester::query()->findOrFail($this->filter_semester_id);

        try {
            $this->hasil_sinkronisasi = $sinkronisasi->handle($semester);
            $this->resetPage();
            $this->dispatch('notify', message: [
                'status' => 'success',
                'message' => 'Status registrasi mahasiswa berhasil disinkronkan.',
            ]);
        } catch (DomainException $exception) {
            $this->simpanHasilGagal($semester, $exception->getMessage());
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->simpanHasilGagal($semester, 'Sinkronisasi gagal karena kesalahan internal.');
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Sinkronisasi gagal karena kesalahan internal.',
            ]);
        }
    }

    public function sinkronkanMahasiswa(int $id, SinkronisasiStatusRegistrasiMahasiswa $sinkronisasi): void
    {
        $this->pastikanBerhak();
        $this->validate([
            'filter_semester_id' => ['required', Rule::exists('semester', 'id_semester')->whereNull('deleted_at')],
        ], [
            'filter_semester_id.required' => 'Semester wajib dipilih sebelum sinkronisasi.',
            'filter_semester_id.exists' => 'Semester tidak ditemukan.',
        ]);

        $semester = Semester::query()->findOrFail($this->filter_semester_id);
        $mahasiswa = Mahasiswa::query()->findOrFail($id);

        try {
            $hasil = $sinkronisasi->handleMahasiswa($semester, $mahasiswa);
            $this->dispatch('notify', message: [
                'status' => 'success',
                'message' => $hasil['pesan'],
            ]);
        } catch (DomainException $exception) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Sinkronisasi mahasiswa gagal karena kesalahan internal.',
            ]);
        }
    }

    public function render(): View
    {
        $semesterId = $this->filter_semester_id;
        $mahasiswa = Mahasiswa::query()
            ->when(! $semesterId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->with([
                'prodi:id_prodi,kode,nama',
                'status_registrasi' => fn ($query) => $query
                    ->where('semester_id', $semesterId)
                    ->select(
                        'id_status_registrasi_mahasiswa',
                        'mahasiswa_id',
                        'semester_id',
                        'status',
                        'jenis_registrasi',
                        'status_bayar',
                        'last_synced_at'
                    ),
            ])
            ->when($this->filter_prodi_id, fn (Builder $query) => $query->where('prodi_id', $this->filter_prodi_id))
            ->when($this->filter_angkatan, fn (Builder $query) => $query->where('angkatan', $this->filter_angkatan))
            ->when($this->filter_status === 'belum_diatur', fn (Builder $query) => $query->whereDoesntHave(
                'status_registrasi',
                fn (Builder $registrasi) => $registrasi->where('semester_id', $semesterId)
            ))
            ->when(in_array($this->filter_status, StatusRegistrasiMahasiswa::STATUS, true), fn (Builder $query) => $query->whereHas(
                'status_registrasi',
                fn (Builder $registrasi) => $registrasi
                    ->where('semester_id', $semesterId)
                    ->where('status', $this->filter_status)
            ))
            ->when(trim($this->filter_pencarian), function (Builder $query): void {
                $search = trim($this->filter_pencarian);
                $query->where(fn (Builder $subquery) => $subquery
                    ->where('nim', 'like', '%'.$search.'%')
                    ->orWhere('nama', 'like', '%'.$search.'%'));
            })
            ->orderByDesc('angkatan')
            ->orderBy('nama')
            ->paginate(10);

        $mahasiswaEdit = $this->edit_mahasiswa_id
            ? Mahasiswa::with('prodi:id_prodi,kode,nama')->find($this->edit_mahasiswa_id)
            : null;

        return view('livewire.table-status-registrasi-mahasiswa', [
            'mahasiswa' => $mahasiswa,
            'mahasiswaEdit' => $mahasiswaEdit,
            'semesterEdit' => $this->edit_semester_id ? Semester::find($this->edit_semester_id) : null,
            'semester' => Semester::orderByDesc('tahun')->orderByDesc('kode')->get(),
            'prodi' => Prodi::orderBy('nama')->get(['id_prodi', 'kode', 'nama']),
            'angkatan' => Mahasiswa::select('angkatan')->distinct()->orderByDesc('angkatan')->pluck('angkatan'),
        ]);
    }

    private function pastikanBerhak(): void
    {
        abort_unless(auth()->user()?->can('status-registrasi-mahasiswa:'), 403);
    }

    private function muatHasilSinkronisasi(): void
    {
        $this->hasil_sinkronisasi = $this->filter_semester_id
            ? Cache::get(SinkronisasiStatusRegistrasiMahasiswa::cacheKey((int) $this->filter_semester_id))
            : null;
    }

    private function simpanHasilGagal(Semester $semester, string $pesan): void
    {
        $this->hasil_sinkronisasi = [
            'status' => 'error',
            'semester' => $semester->kode,
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => 0,
            'dibuat' => 0,
            'diubah' => 0,
            'tetap' => 0,
            'dilewati' => 0,
            'gagal' => 0,
            'pesan' => $pesan,
        ];

        Cache::put(
            SinkronisasiStatusRegistrasiMahasiswa::cacheKey($semester->id_semester),
            $this->hasil_sinkronisasi,
            now()->addDays(30)
        );
    }
}
