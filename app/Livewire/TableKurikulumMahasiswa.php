<?php

namespace App\Livewire;

use App\Models\Kurikulum;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Support\Akademik\Sync\SinkronisasiMahasiswa;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

final class TableKurikulumMahasiswa extends Component
{
    use WithPagination;

    #[Locked]
    public $edit_mahasiswa_id = null;

    public $edit_kurikulum_id = '';

    public $filter_prodi_id = '';

    public $filter_angkatan = '';

    public $filter_kurikulum_id = '';

    public string $filter_pencarian = '';

    #[Locked]
    public ?array $hasil_sinkronisasi = null;

    protected string $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $this->pastikanBerhak();
        $this->hasil_sinkronisasi = Cache::get(SinkronisasiMahasiswa::cacheKeyKurikulum());
    }

    public function updated($property): void
    {
        if (str_starts_with($property, 'filter_')) {
            $this->resetPage();
        }
    }

    public function bukaPengaturan(int $id): void
    {
        $this->pastikanBerhak();
        $mahasiswa = Mahasiswa::findOrFail($id);
        $this->resetValidation();
        $this->edit_mahasiswa_id = $mahasiswa->id_mahasiswa;
        $this->edit_kurikulum_id = $mahasiswa->kurikulum_id ?? '';
    }

    public function tutupPengaturan(): void
    {
        $this->reset(['edit_mahasiswa_id', 'edit_kurikulum_id']);
        $this->resetValidation();
    }

    #[On('kurikulum-mahasiswa-diperbarui')]
    public function refreshTable(): void {}

    public function simpanIndividual(): void
    {
        $this->pastikanBerhak();

        DB::transaction(function () {
            $mahasiswa = Mahasiswa::query()->lockForUpdate()->findOrFail($this->edit_mahasiswa_id);

            if ($this->edit_kurikulum_id && ! Kurikulum::query()
                ->whereKey($this->edit_kurikulum_id)
                ->where('prodi_id', $mahasiswa->prodi_id)
                ->where('status', 'aktif')
                ->exists()) {
                throw ValidationException::withMessages([
                    'edit_kurikulum_id' => 'Kurikulum harus aktif dan berasal dari program studi mahasiswa.',
                ]);
            }

            $mahasiswa->update(['kurikulum_id' => $this->edit_kurikulum_id ?: null]);
        });

        $this->tutupPengaturan();
        $this->dispatch('notify', message: ['status' => 'success', 'message' => 'Kurikulum mahasiswa berhasil diperbarui.']);
    }

    public function sinkronkan(SinkronisasiMahasiswa $sinkronisasi): void
    {
        $this->pastikanBerhak();

        try {
            $this->hasil_sinkronisasi = $sinkronisasi->handleKurikulum();
            $this->resetPage();
            $this->dispatch('notify', message: [
                'status' => 'success',
                'message' => $this->hasil_sinkronisasi['pesan'],
            ]);
        } catch (DomainException $exception) {
            $this->simpanHasilGagal($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->simpanHasilGagal('Sinkronisasi kurikulum mahasiswa gagal. Periksa koneksi, data lokal, dan format API.');
        }
    }

    public function sinkronkanMahasiswa(int $id, SinkronisasiMahasiswa $sinkronisasi): void
    {
        $this->pastikanBerhak();
        $mahasiswa = Mahasiswa::query()->findOrFail($id);

        try {
            $hasil = $sinkronisasi->handleKurikulumMahasiswa($mahasiswa);
            $this->dispatch('notify', message: ['status' => 'success', 'message' => $hasil['pesan']]);
        } catch (DomainException $exception) {
            $this->dispatch('notify', message: ['status' => 'error', 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Sinkronisasi kurikulum mahasiswa gagal. Periksa koneksi, data lokal, dan format API.',
            ]);
        }
    }

    public function render(): View
    {
        $mahasiswa = Mahasiswa::query()
            ->with([
                'prodi:id_prodi,kode,nama',
                'kurikulum:id_kurikulum,kode,nama,status,deleted_at',
            ])
            ->when($this->filter_prodi_id, fn (Builder $query) => $query->where('prodi_id', $this->filter_prodi_id))
            ->when($this->filter_angkatan, fn (Builder $query) => $query->where('angkatan', $this->filter_angkatan))
            ->when($this->filter_kurikulum_id === 'kosong', fn (Builder $query) => $query->whereNull('kurikulum_id'))
            ->when($this->filter_kurikulum_id && $this->filter_kurikulum_id !== 'kosong', fn (Builder $query) => $query->where('kurikulum_id', $this->filter_kurikulum_id))
            ->when(trim($this->filter_pencarian), function (Builder $query) {
                $search = trim($this->filter_pencarian);
                $query->where(fn (Builder $subquery) => $subquery
                    ->where('nim', 'like', '%'.$search.'%')
                    ->orWhere('nama', 'like', '%'.$search.'%'));
            })
            ->orderByDesc('angkatan')
            ->orderBy('nama')
            ->paginate(10);

        $mahasiswaEdit = $this->edit_mahasiswa_id
            ? Mahasiswa::with(['prodi', 'kurikulum'])->find($this->edit_mahasiswa_id)
            : null;

        return view('livewire.table-kurikulum-mahasiswa', [
            'mahasiswa' => $mahasiswa,
            'mahasiswaEdit' => $mahasiswaEdit,
            'prodi' => Prodi::orderBy('nama')->get(['id_prodi', 'kode', 'nama']),
            'angkatan' => Mahasiswa::select('angkatan')->whereNotNull('angkatan')->distinct()->orderByDesc('angkatan')->pluck('angkatan'),
            'kurikulumFilter' => Kurikulum::withTrashed()->with('prodi:id_prodi,kode')->orderByDesc('tahun_berlaku')->orderBy('nama')->get(),
            'kurikulumEdit' => $mahasiswaEdit
                ? Kurikulum::where('prodi_id', $mahasiswaEdit->prodi_id)->where('status', 'aktif')->orderByDesc('tahun_berlaku')->orderBy('nama')->get()
                : collect(),
        ]);
    }

    private function pastikanBerhak(): void
    {
        abort_unless(auth()->user()?->can('kurikulum-mahasiswa:'), 403);
    }

    private function simpanHasilGagal(string $pesan): void
    {
        $this->hasil_sinkronisasi = [
            'status' => 'error',
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diperiksa' => 0,
            'diubah' => 0,
            'tetap' => 0,
            'dilewati' => 0,
            'rincian' => [],
            'pesan' => $pesan,
        ];

        Cache::put(
            SinkronisasiMahasiswa::cacheKeyKurikulum(),
            $this->hasil_sinkronisasi,
            now()->addDays(30)
        );
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }
}
