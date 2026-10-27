<?php

use App\Models\AnggotaKelasKuliah;
use App\Models\Blok;
use App\Models\FinalisasiDpnaBlok;
use App\Models\KelasKuliah;
use App\Models\Mahasiswa;
use App\Models\PesertaBlok;
use App\Models\Semester;
use App\Support\Akademik\Sync\SinkronisasiKelasKuliah;
use App\Support\Akademik\Sync\SinkronisasiPesertaKrs;
use App\Support\PembagianKelasKuliah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $blok_id = '';

    public string $semester_id = '';

    public string $tab = 'kelas';

    public string $kelas_kuliah_id = '';

    #[Locked]
    public ?int $kelola_peserta_kelas_id = null;

    #[Locked]
    public ?int $lihat_peserta_kelas_id = null;

    #[Locked]
    public ?int $edit_kelas_id = null;

    public string $edit_kode = '';

    public string $edit_nama = '';

    public string $edit_kapasitas = '';

    public string $edit_status = 'aktif';

    public string $edit_blok_id = '';

    public string $kelas_baru_kode = '';

    public string $kelas_baru_nama = '';

    public string $kelas_baru_kapasitas = '';

    public string $jumlah_kelas = '';

    public string $peserta_search = '';

    public string $daftar_peserta_search = '';

    public array $peserta_ids = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        $this->semester_id = (string) (Semester::query()->where('is_aktif', true)->value('id_semester') ?? '');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['kelas', 'dpna', 'monitoring'], true)) {
            $this->tab = $tab;
        }
    }

    #[Computed]
    public function semesterList()
    {
        return Semester::query()
            ->orderByDesc('tahun')
            ->orderBy('nama')
            ->get(['id_semester', 'kode', 'nama', 'tahun', 'is_aktif']);
    }

    #[Computed]
    public function blokList()
    {
        return Blok::query()
            ->with(['prodi:id_prodi,kode,nama', 'semester:id_semester,kode,nama,tahun'])
            ->when($this->semester_id !== '', fn (Builder $query) => $query->where('semester_id', $this->semester_id))
            ->orderByDesc('tanggal_mulai')
            ->orderBy('nama')
            ->get();
    }

    #[Computed]
    public function semesterTerpilih(): ?Semester
    {
        return $this->semester_id === '' ? null : Semester::query()->find($this->semester_id);
    }

    #[Computed]
    public function hasilSinkronisasiKelas(): ?array
    {
        return $this->semester_id === ''
            ? null
            : Cache::get(SinkronisasiKelasKuliah::cacheKey((int) $this->semester_id));
    }

    #[Computed]
    public function hasilSinkronisasiPeserta(): ?array
    {
        return $this->semester_id === ''
            ? null
            : Cache::get(SinkronisasiPesertaKrs::cacheKey((int) $this->semester_id));
    }

    #[Computed]
    public function kelasList()
    {
        $blok = $this->blok_id !== '' ? Blok::query()->find($this->blok_id) : null;

        return KelasKuliah::query()
            ->when($blok, fn (Builder $query) => $query
                ->where('semester_id', $blok->semester_id)
                ->where('mata_kuliah_id', $blok->mata_kuliah_id)
                ->whereHas('mata_kuliah', fn (Builder $query) => $query->where('prodi_id', $blok->prodi_id))
                ->where(fn (Builder $query) => $query->whereNull('blok_id')->orWhere('blok_id', $blok->id)))
            ->when(! $blok && $this->semester_id !== '', fn (Builder $query) => $query->where('semester_id', $this->semester_id))
            ->with([
                'blok.prodi:id_prodi,kode,nama',
                'semester:id_semester,kode,nama,tahun',
                'mata_kuliah.prodi:id_prodi,kode,nama',
                'mata_kuliah:id,prodi_id,kode,nama',
            ])
            ->withCount('anggota_kelas_kuliah')
            ->orderBy('blok_id')
            ->orderBy('kode')
            ->get();
    }

    #[Computed]
    public function blokEditList()
    {
        if ($this->edit_kelas_id === null) {
            return collect();
        }

        $kelas = KelasKuliah::query()
            ->with('mata_kuliah:id,prodi_id')
            ->find($this->edit_kelas_id);

        if ($kelas === null) {
            return collect();
        }

        return Blok::query()
            ->where('semester_id', $kelas->semester_id)
            ->where('mata_kuliah_id', $kelas->mata_kuliah_id)
            ->where('prodi_id', $kelas->mata_kuliah?->prodi_id)
            ->with(['prodi:id_prodi,kode,nama', 'semester:id_semester,kode,nama,tahun'])
            ->orderByDesc('tanggal_mulai')
            ->orderBy('nama')
            ->get();
    }

    public function updatedSemesterId(): void
    {
        $this->blok_id = '';
        $this->updatedBlokId();
        unset($this->semesterList, $this->semesterTerpilih, $this->hasilSinkronisasiKelas, $this->hasilSinkronisasiPeserta, $this->blokList, $this->kelasList, $this->monitoring);
    }

    public function ambilKelas(SinkronisasiKelasKuliah $sinkronisasi): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        $semester = $this->semesterApi();

        try {
            $hasil = $sinkronisasi->handle($semester);
            unset($this->hasilSinkronisasiKelas, $this->kelasList, $this->daftarPeserta, $this->pesertaKelas, $this->dpna, $this->monitoring);
            $this->dispatch('notify', message: ['status' => 'success', 'message' => $hasil['pesan']]);
        } catch (DomainException $exception) {
            $this->simpanHasilSinkronisasiGagal(SinkronisasiKelasKuliah::cacheKey($semester->id_semester), 'hasilSinkronisasiKelas', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->simpanHasilSinkronisasiGagal(SinkronisasiKelasKuliah::cacheKey($semester->id_semester), 'hasilSinkronisasiKelas', 'Pengambilan kelas gagal. Periksa koneksi, data lokal, dan format data API.');
        }
    }

    public function sinkronkanAnggotaKelas(SinkronisasiPesertaKrs $sinkronisasi): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        $semester = $this->semesterApi();

        try {
            $hasil = $sinkronisasi->handle($semester);
            unset($this->hasilSinkronisasiPeserta, $this->kelasList, $this->daftarPeserta, $this->pesertaKelas, $this->dpna, $this->monitoring);
            $this->dispatch('notify', message: ['status' => 'success', 'message' => $hasil['pesan']]);
        } catch (DomainException $exception) {
            $this->simpanHasilSinkronisasiGagal(SinkronisasiPesertaKrs::cacheKey($semester->id_semester), 'hasilSinkronisasiPeserta', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->simpanHasilSinkronisasiGagal(SinkronisasiPesertaKrs::cacheKey($semester->id_semester), 'hasilSinkronisasiPeserta', 'Sinkronisasi anggota kelas gagal. Periksa koneksi, data lokal, dan format data API.');
        }
    }

    private function semesterApi(): Semester
    {
        $this->validate([
            'semester_id' => ['required', Rule::exists('semester', 'id_semester')->whereNull('deleted_at')],
        ], [
            'semester_id.required' => 'Semester wajib dipilih.',
            'semester_id.exists' => 'Semester tidak ditemukan.',
        ]);

        $semester = Semester::query()->findOrFail((int) $this->semester_id);

        if ($semester->dikelolaLokal()) {
            throw ValidationException::withMessages([
                'semester_id' => 'Sinkronisasi API hanya berlaku sampai semester 20261.',
            ]);
        }

        return $semester;
    }

    private function simpanHasilSinkronisasiGagal(string $cacheKey, string $computed, string $pesan): void
    {
        Cache::put($cacheKey, [
            'status' => 'error',
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'pesan' => $pesan,
        ], now()->addDays(30));
        unset($this->{$computed});
        $this->dispatch('notify', message: ['status' => 'error', 'message' => $pesan]);
    }

    public function updatedBlokId(): void
    {
        $this->tutupKelolaPeserta();
        $this->tutupDaftarPeserta();
        $this->tutupEditKelas();
        $this->kelas_kuliah_id = '';
        $this->reset(['kelas_baru_kode', 'kelas_baru_nama', 'kelas_baru_kapasitas', 'jumlah_kelas']);
        $this->resetValidation(['kelas_baru_kode', 'kelas_baru_nama', 'kelas_baru_kapasitas', 'jumlah_kelas']);
        unset($this->kelasList);
        unset($this->dpna, $this->monitoring);
    }

    public function updatedPesertaSearch(): void
    {
        $this->resetPage('pesertaKelasPage');
    }

    public function updatedDaftarPesertaSearch(): void
    {
        $this->resetPage('daftarPesertaPage');
    }

    #[Computed]
    public function daftarPeserta()
    {
        return AnggotaKelasKuliah::query()
            ->select([
                'anggota_kelas_kuliah.*',
                DB::raw('COALESCE(mahasiswa.nim, anggota_kelas_kuliah.nim_sumber) as peserta_nim'),
                'mahasiswa.nama as peserta_nama',
            ])
            ->leftJoin('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'anggota_kelas_kuliah.mahasiswa_id')
            ->where('anggota_kelas_kuliah.kelas_kuliah_id', $this->lihat_peserta_kelas_id ?: 0)
            ->when($this->daftar_peserta_search !== '', function (Builder $query): void {
                $search = '%'.trim($this->daftar_peserta_search).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('mahasiswa.nama', 'like', $search)
                        ->orWhere('mahasiswa.nim', 'like', $search)
                        ->orWhere('anggota_kelas_kuliah.nim_sumber', 'like', $search);
                });
            })
            ->orderByRaw('COALESCE(mahasiswa.nama, anggota_kelas_kuliah.nim_sumber)')
            ->paginate(25, pageName: 'daftarPesertaPage');
    }

    #[Computed]
    public function pesertaKelas()
    {
        return PesertaBlok::query()
            ->select('peserta_blok.*')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
            ->where('peserta_blok.blok_id', $this->blok_id ?: 0)
            ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
            ->when($this->peserta_search !== '', function (Builder $query): void {
                $search = '%'.trim($this->peserta_search).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('mahasiswa.nama', 'like', $search)
                        ->orWhere('mahasiswa.nim', 'like', $search);
                });
            })
            ->with([
                'mahasiswa:id_mahasiswa,nim,nama',
                'anggota_kelas_kuliah.kelas_kuliah:id_kelas_kuliah,kode,nama',
            ])
            ->orderBy('mahasiswa.nama')
            ->paginate(25, pageName: 'pesertaKelasPage');
    }

    #[Computed]
    public function dpna(): ?array
    {
        if (! $this->kelas_kuliah_id) {
            return null;
        }

        $kelas = KelasKuliah::query()
            ->when($this->blok_id !== '', fn (Builder $query) => $query->where('blok_id', $this->blok_id))
            ->when($this->semester_id !== '', fn (Builder $query) => $query->where('semester_id', $this->semester_id))
            ->whereNotNull('blok_id')
            ->where('status', 'aktif')
            ->with(['blok.prodi:id_prodi,kode,nama', 'blok.semester:id_semester,nama,tahun'])
            ->findOrFail((int) $this->kelas_kuliah_id);
        $finalisasi = FinalisasiDpnaBlok::query()
            ->where('blok_id', $kelas->blok_id)
            ->where('status', 'final')
            ->with('snapshot_dpna_peserta')
            ->orderByDesc('versi')
            ->first();
        $snapshot = $finalisasi?->snapshot_dpna_peserta->keyBy('peserta_blok_id') ?? collect();
        $anggota = $kelas->anggota_kelas_kuliah()
            ->with('mahasiswa:id_mahasiswa,nim,nama')
            ->get()
            ->sortBy(fn ($item) => $item->mahasiswa?->nama)
            ->values();

        return compact('kelas', 'finalisasi', 'snapshot', 'anggota');
    }

    #[Computed]
    public function monitoring()
    {
        return Blok::query()
            ->with(['prodi:id_prodi,kode,nama', 'semester:id_semester,nama,tahun'])
            ->when($this->semester_id !== '', fn (Builder $query) => $query->where('semester_id', $this->semester_id))
            ->when($this->blok_id !== '', fn (Builder $query) => $query->whereKey($this->blok_id))
            ->withCount([
                'kelas_kuliah as kelas_aktif_count' => fn (Builder $query) => $query->where('status', 'aktif'),
                'peserta_blok as peserta_aktif_count' => fn (Builder $query) => $query->whereIn('status', ['aktif', 'mengulang']),
                'peserta_blok as peserta_terbagi_count' => fn (Builder $query) => $query
                    ->whereIn('status', ['aktif', 'mengulang'])
                    ->whereHas('anggota_kelas_kuliah.kelas_kuliah', fn (Builder $kelas) => $kelas->where('status', 'aktif')),
                'finalisasi_dpna_blok as dpna_final_count' => fn (Builder $query) => $query->where('status', 'final'),
            ])
            ->orderByDesc('tanggal_mulai')
            ->orderBy('nama')
            ->get();
    }

    public function petakan(int $id): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        $blok = Blok::query()->findOrFail((int) $this->blok_id);
        $kelas = KelasKuliah::query()
            ->where('semester_id', $blok->semester_id)
            ->where('mata_kuliah_id', $blok->mata_kuliah_id)
            ->whereHas('mata_kuliah', fn (Builder $query) => $query->where('prodi_id', $blok->prodi_id))
            ->where(fn (Builder $query) => $query->whereNull('blok_id')->orWhere('blok_id', $blok->id))
            ->findOrFail($id);

        if ($kelas->semester()->firstOrFail()->dikelolaLokal()) {
            throw ValidationException::withMessages(['kelas' => 'Pemetaan blok hanya berlaku untuk kelas sumber API.']);
        }
        $ditautkan = DB::transaction(function () use ($kelas, $blok): int {
            $kelas = KelasKuliah::query()->lockForUpdate()->findOrFail($kelas->id_kelas_kuliah);
            $kelas->update(['blok_id' => $blok->id]);

            return $kelas->tautkanPesertaBlok();
        });

        $this->dispatch('notify', message: ['status' => 'success', 'message' => "Kelas {$kelas->kode} dipetakan ke blok {$blok->nama}; {$ditautkan} peserta blok ditautkan."]);
        unset($this->kelasList, $this->monitoring);
    }

    public function lepasPemetaan(int $id): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        $kelas = KelasKuliah::query()->where('blok_id', $this->blok_id ?: 0)->findOrFail($id);

        if ($kelas->semester()->firstOrFail()->dikelolaLokal()) {
            throw ValidationException::withMessages(['kelas' => 'Pemetaan kelas lokal tidak dapat dilepas dari blok asal.']);
        }

        if ($kelas->anggota_kelas_kuliah()->exists()) {
            throw ValidationException::withMessages(['pembagian' => 'Pemetaan tidak dapat dilepas karena kelas masih memiliki peserta.']);
        }

        $kelas->update(['blok_id' => null]);
        $this->dispatch('notify', message: ['status' => 'success', 'message' => "Pemetaan kelas {$kelas->kode} dilepas."]);
        unset($this->kelasList, $this->monitoring);
    }

    public function editKelas(int $id): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        $kelas = KelasKuliah::query()->findOrFail($id);

        $this->tutupKelolaPeserta();
        $this->tutupDaftarPeserta();
        $this->edit_kelas_id = (int) $kelas->id_kelas_kuliah;
        $this->edit_kode = $kelas->kode;
        $this->edit_nama = $kelas->nama ?? '';
        $this->edit_kapasitas = $kelas->kapasitas === null ? '' : (string) $kelas->kapasitas;
        $this->edit_status = $kelas->status;
        $this->edit_blok_id = $kelas->blok_id === null ? '' : (string) $kelas->blok_id;
        $this->resetValidation(['edit_kode', 'edit_nama', 'edit_kapasitas', 'edit_status', 'edit_blok_id']);
        unset($this->blokEditList);
    }

    public function simpanEditKelas(): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        abort_if($this->edit_kelas_id === null, 404);
        $kelasTarget = KelasKuliah::query()->with('semester')->findOrFail($this->edit_kelas_id);
        $blokId = $this->edit_blok_id === '' ? null : (int) $this->edit_blok_id;

        if ($kelasTarget->semester->bersumberDariApi()) {
            $this->validate([
                'edit_blok_id' => ['nullable', 'integer', Rule::exists('blok', 'id')->whereNull('deleted_at')],
            ]);

            DB::transaction(function () use ($blokId): void {
                $kelas = KelasKuliah::query()->lockForUpdate()->findOrFail($this->edit_kelas_id);
                $jumlahPeserta = $kelas->anggota_kelas_kuliah()->count();

                if ($blokId !== null) {
                    $prodiId = $kelas->mata_kuliah()->value('prodi_id');
                    $blokValid = Blok::query()
                        ->whereKey($blokId)
                        ->where('semester_id', $kelas->semester_id)
                        ->where('mata_kuliah_id', $kelas->mata_kuliah_id)
                        ->where('prodi_id', $prodiId)
                        ->exists();

                    if (! $blokValid) {
                        throw ValidationException::withMessages([
                            'edit_blok_id' => 'Blok wajib memiliki semester, program studi, dan mata kuliah yang sama dengan kelas.',
                        ]);
                    }
                }

                if (! KelasKuliah::pemetaanDapatDiubah($kelas->blok_id, $blokId, $jumlahPeserta)) {
                    throw ValidationException::withMessages(['edit_blok_id' => 'Pemetaan tidak dapat diubah karena kelas masih memiliki peserta.']);
                }

                $kelas->update(['blok_id' => $blokId]);
                if ($blokId !== null) {
                    $kelas->tautkanPesertaBlok();
                }
            });

            $kode = $kelasTarget->kode;
            $this->tutupEditKelas();
            unset($this->kelasList, $this->monitoring, $this->dpna);
            $this->dispatch('notify', message: ['status' => 'success', 'message' => "Pemetaan kelas {$kode} berhasil diperbarui."]);

            return;
        }

        $kodeRules = ['required', 'string', 'max:255'];
        if ($blokId !== null) {
            $kodeRules[] = Rule::unique('kelas_kuliah', 'kode')
                ->where('blok_id', $blokId)
                ->ignore($this->edit_kelas_id, 'id_kelas_kuliah');
        }

        $data = $this->validate([
            'edit_kode' => $kodeRules,
            'edit_nama' => ['nullable', 'string', 'max:255'],
            'edit_kapasitas' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'edit_status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'edit_blok_id' => ['nullable', 'integer', Rule::exists('blok', 'id')->whereNull('deleted_at')],
        ], [
            'edit_kode.unique' => 'Kode kelas sudah dipakai pada blok tujuan.',
            'edit_blok_id.exists' => 'Blok tujuan tidak valid.',
        ]);

        DB::transaction(function () use ($data, $blokId): void {
            $kelas = KelasKuliah::query()->lockForUpdate()->findOrFail($this->edit_kelas_id);
            $jumlahPeserta = $kelas->anggota_kelas_kuliah()->count();

            if ($blokId !== (int) $kelas->blok_id) {
                throw ValidationException::withMessages(['edit_blok_id' => 'Kelas lokal tidak dapat dipindahkan dari blok asal.']);
            }

            if ($blokId !== null) {
                $prodiId = $kelas->mata_kuliah()->value('prodi_id');
                $blokValid = Blok::query()
                    ->whereKey($blokId)
                    ->where('semester_id', $kelas->semester_id)
                    ->where('mata_kuliah_id', $kelas->mata_kuliah_id)
                    ->where('prodi_id', $prodiId)
                    ->exists();

                if (! $blokValid) {
                    throw ValidationException::withMessages([
                        'edit_blok_id' => 'Blok wajib memiliki semester, program studi, dan mata kuliah yang sama dengan kelas.',
                    ]);
                }
            }

            $blokAsalId = $kelas->blok_id === null ? null : (int) $kelas->blok_id;
            if (! KelasKuliah::pemetaanDapatDiubah($blokAsalId, $blokId, $jumlahPeserta)) {
                throw ValidationException::withMessages([
                    'edit_blok_id' => 'Pemetaan tidak dapat diubah karena kelas masih memiliki peserta.',
                ]);
            }

            $kapasitas = in_array($data['edit_kapasitas'], [null, ''], true) ? null : (int) $data['edit_kapasitas'];
            if ($kapasitas !== null && $kapasitas < $jumlahPeserta) {
                throw ValidationException::withMessages([
                    'edit_kapasitas' => "Kapasitas tidak boleh kurang dari {$jumlahPeserta} peserta saat ini.",
                ]);
            }

            $kelas->update([
                'blok_id' => $blokId,
                'kode' => $data['edit_kode'],
                'nama' => in_array($data['edit_nama'], [null, ''], true) ? null : $data['edit_nama'],
                'kapasitas' => $kapasitas,
                'status' => $data['edit_status'],
            ]);

            if ($blokAsalId === null && $blokId !== null) {
                $kelas->tautkanPesertaBlok();
            }
        });

        $kode = $data['edit_kode'];
        $this->tutupEditKelas();
        unset($this->kelasList, $this->monitoring, $this->dpna);
        $this->dispatch('notify', message: ['status' => 'success', 'message' => "Kelas {$kode} berhasil diperbarui."]);
    }

    public function hapusKelas(int $id): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);

        $kode = DB::transaction(function () use ($id): string {
            $kelas = KelasKuliah::query()->lockForUpdate()->findOrFail($id);

            if ($kelas->semester()->firstOrFail()->bersumberDariApi()) {
                throw ValidationException::withMessages(['kelas' => 'Kelas sumber API tidak dapat dihapus secara lokal.']);
            }

            if (! KelasKuliah::dapatDihapus($kelas->anggota_kelas_kuliah()->count())) {
                throw ValidationException::withMessages([
                    'kelas' => 'Kelas tidak dapat dihapus karena masih memiliki peserta.',
                ]);
            }

            $kode = $kelas->kode;
            $kelas->delete();

            return $kode;
        });

        if ($this->edit_kelas_id === $id) {
            $this->tutupEditKelas();
        }

        if ($this->kelola_peserta_kelas_id === $id) {
            $this->tutupKelolaPeserta();
        }

        if ($this->lihat_peserta_kelas_id === $id) {
            $this->tutupDaftarPeserta();
        }

        unset($this->kelasList, $this->monitoring, $this->dpna);
        $this->dispatch('notify', message: ['status' => 'success', 'message' => "Kelas {$kode} berhasil dihapus."]);
    }

    public function tutupEditKelas(): void
    {
        $this->edit_kelas_id = null;
        $this->edit_kode = '';
        $this->edit_nama = '';
        $this->edit_kapasitas = '';
        $this->edit_status = 'aktif';
        $this->edit_blok_id = '';
        $this->resetValidation(['edit_kode', 'edit_nama', 'edit_kapasitas', 'edit_status', 'edit_blok_id']);
        unset($this->blokEditList);
    }

    public function bagiKelas(PembagianKelasKuliah $pembagian): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        $data = $this->validate([
            'blok_id' => ['required', Rule::exists('blok', 'id')->whereNull('deleted_at')],
        ]);
        $blok = Blok::query()->findOrFail((int) $data['blok_id']);
        $jumlahPeserta = $pembagian->bagi($blok);

        session()->flash('success', $jumlahPeserta.' peserta berhasil dibagi ke kelas lokal.');
        $this->kelas_kuliah_id = '';
        unset($this->kelasList);
        unset($this->dpna, $this->monitoring);
    }

    public function kelolaPeserta(int $id): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);

        $this->tutupDaftarPeserta();
        $this->tutupEditKelas();
        $kelas = KelasKuliah::query()
            ->whereHas('semester', fn (Builder $query) => $query->where('kode', '>', Semester::SEMESTER_TERAKHIR_SUMBER_API))
            ->whereNotNull('blok_id')
            ->when($this->blok_id !== '', fn (Builder $query) => $query->where('blok_id', $this->blok_id))
            ->when($this->semester_id !== '', fn (Builder $query) => $query->where('semester_id', $this->semester_id))
            ->where('status', 'aktif')
            ->findOrFail($id);

        $this->blok_id = (string) $kelas->blok_id;
        $this->kelola_peserta_kelas_id = (int) $kelas->id_kelas_kuliah;
        $this->peserta_ids = $kelas->anggota_kelas_kuliah()
            ->whereHas('peserta_blok', fn (Builder $query) => $query
                ->where('blok_id', $this->blok_id)
                ->whereIn('status', ['aktif', 'mengulang']))
            ->pluck('peserta_blok_id')
            ->map(fn ($id) => (string) $id)
            ->all();
        $this->peserta_search = '';
        $this->resetPage('pesertaKelasPage');
        $this->resetValidation('peserta_ids');
        unset($this->pesertaKelas);
    }

    public function lihatDaftarPeserta(int $id): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);

        KelasKuliah::query()->findOrFail($id);
        $this->tutupKelolaPeserta();
        $this->tutupEditKelas();
        $this->lihat_peserta_kelas_id = $id;
        $this->daftar_peserta_search = '';
        $this->resetPage('daftarPesertaPage');
        unset($this->daftarPeserta);
    }

    public function tutupDaftarPeserta(): void
    {
        $this->lihat_peserta_kelas_id = null;
        $this->daftar_peserta_search = '';
        $this->resetPage('daftarPesertaPage');
        unset($this->daftarPeserta);
    }

    public function togglePagePesertaKelas(): void
    {
        $ids = $this->pesertaKelas->pluck('id_peserta_blok')->map(fn ($id) => (string) $id)->all();

        if ($ids === []) {
            return;
        }

        $selected = array_map('strval', $this->peserta_ids);
        $this->peserta_ids = empty(array_diff($ids, $selected))
            ? array_values(array_diff($selected, $ids))
            : array_values(array_unique([...$selected, ...$ids]));
    }

    public function simpanPesertaKelas(): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);

        $data = $this->validate([
            'peserta_ids' => ['array'],
            'peserta_ids.*' => ['integer'],
        ]);
        $ids = collect($data['peserta_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        DB::transaction(function () use ($ids): void {
            $blok = Blok::query()->findOrFail((int) $this->blok_id);
            Blok::query()
                ->where('semester_id', $blok->semester_id)
                ->where('mata_kuliah_id', $blok->mata_kuliah_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $kelas = KelasKuliah::query()
                ->where('blok_id', $this->blok_id ?: 0)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->findOrFail($this->kelola_peserta_kelas_id);

            if ($kelas->semester()->firstOrFail()->bersumberDariApi()) {
                throw ValidationException::withMessages(['peserta_ids' => 'Anggota kelas sumber API tidak dapat diubah secara lokal.']);
            }
            $validIds = PesertaBlok::query()
                ->where('blok_id', $this->blok_id)
                ->whereIn('status', ['aktif', 'mengulang'])
                ->whereIn('id_peserta_blok', $ids)
                ->lockForUpdate()
                ->pluck('mahasiswa_id', 'id_peserta_blok');

            if ($validIds->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'peserta_ids' => 'Pilihan peserta tidak valid untuk blok ini.',
                ]);
            }

            if ($kelas->kapasitas !== null && $validIds->count() > $kelas->kapasitas) {
                throw ValidationException::withMessages([
                    'peserta_ids' => 'Kapasitas '.$kelas->kode.' maksimal '.$kelas->kapasitas.' peserta.',
                ]);
            }

            $konflikAntarblok = AnggotaKelasKuliah::query()
                ->whereIn('mahasiswa_id', $validIds->values())
                ->whereHas('kelas_kuliah', fn (Builder $query) => $query
                    ->where('semester_id', $kelas->semester_id)
                    ->where('mata_kuliah_id', $kelas->mata_kuliah_id)
                    ->whereNotNull('blok_id')
                    ->where('blok_id', '!=', $kelas->blok_id))
                ->exists();

            if ($konflikAntarblok) {
                throw ValidationException::withMessages([
                    'peserta_ids' => 'Pilihan ditolak karena peserta sudah berada pada kelas di blok lain.',
                ]);
            }

            AnggotaKelasKuliah::query()->where('kelas_kuliah_id', $kelas->id_kelas_kuliah)->delete();

            if ($validIds->isNotEmpty()) {
                AnggotaKelasKuliah::query()
                    ->whereHas('kelas_kuliah', fn (Builder $query) => $query
                        ->where('semester_id', $kelas->semester_id)
                        ->where('mata_kuliah_id', $kelas->mata_kuliah_id))
                    ->whereIn('mahasiswa_id', $validIds->values())
                    ->delete();
                $sekarang = now();
                $nim = Mahasiswa::query()->whereIn('id_mahasiswa', $validIds->values())->pluck('nim', 'id_mahasiswa');
                AnggotaKelasKuliah::query()->insert($validIds->map(fn ($mahasiswaId, $id) => [
                    'kelas_kuliah_id' => $kelas->id_kelas_kuliah,
                    'mahasiswa_id' => $mahasiswaId,
                    'nim_sumber' => strtoupper(trim($nim[$mahasiswaId])),
                    'peserta_blok_id' => $id,
                    'sumber' => 'lokal',
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ])->all());
            }
        });

        unset($this->kelasList, $this->pesertaKelas, $this->dpna, $this->monitoring);
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $ids->count().' peserta kelas berhasil disimpan.',
        ]);
    }

    public function tutupKelolaPeserta(): void
    {
        $this->kelola_peserta_kelas_id = null;
        $this->peserta_search = '';
        $this->peserta_ids = [];
        $this->resetPage('pesertaKelasPage');
        $this->resetValidation('peserta_ids');
        unset($this->pesertaKelas);
    }

    public function buatKelasManual(PembagianKelasKuliah $pembagian): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        $data = $this->validate([
            'blok_id' => ['required', Rule::exists('blok', 'id')->whereNull('deleted_at')],
            'kelas_baru_kode' => ['required', 'string', 'regex:/\S/', 'max:255'],
            'kelas_baru_nama' => ['nullable', 'string', 'max:255'],
            'kelas_baru_kapasitas' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);
        $kelas = $pembagian->buatManual(Blok::query()->findOrFail((int) $data['blok_id']), [
            'kode' => trim($data['kelas_baru_kode']),
            'nama' => in_array($data['kelas_baru_nama'], [null, ''], true) ? null : trim($data['kelas_baru_nama']),
            'kapasitas' => in_array($data['kelas_baru_kapasitas'], [null, ''], true) ? null : (int) $data['kelas_baru_kapasitas'],
        ]);

        $this->reset(['kelas_baru_kode', 'kelas_baru_nama', 'kelas_baru_kapasitas']);
        unset($this->kelasList, $this->monitoring);
        $this->dispatch('notify', message: ['status' => 'success', 'message' => "Kelas {$kelas->kode} berhasil dibuat."]);
    }

    public function buatKelasOtomatis(PembagianKelasKuliah $pembagian): void
    {
        abort_unless(auth()->user()?->can('sinkronisasi-kelas:'), 403);
        $data = $this->validate([
            'blok_id' => ['required', Rule::exists('blok', 'id')->whereNull('deleted_at')],
            'jumlah_kelas' => ['required', 'integer', 'min:1', 'max:26'],
        ]);
        $jumlahPeserta = $pembagian->buatOtomatisDanBagi(
            Blok::query()->findOrFail((int) $data['blok_id']),
            (int) $data['jumlah_kelas'],
        );

        $this->reset('jumlah_kelas');
        unset($this->kelasList, $this->pesertaKelas, $this->dpna, $this->monitoring);
        $this->dispatch('notify', message: ['status' => 'success', 'message' => "Kelas otomatis dibuat; {$jumlahPeserta} peserta berhasil dibagi."]);
    }
}; ?>

<div>
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">Sinkronisasi Kelas</h4>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item active">Sinkronisasi Kelas</li></ol>
    </div>

    <livewire:alert />
    @error('pembagian') <div class="alert alert-danger">{{ $message }}</div> @enderror
    @php($semesterTerpilih = $this->semesterTerpilih)

    <div class="card">
        <div class="card-header"><h5 class="mb-1">Filter Kelas</h5><div class="text-muted small">Kelola kelas dan peserta lokal berdasarkan semester dan blok.</div></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="semester-kelas-kuliah" class="form-label">Semester</label>
                    <select id="semester-kelas-kuliah" wire:model.live="semester_id" class="form-select">
                        <option value="">Semua semester</option>
                        @foreach ($this->semesterList as $semester)
                            <option value="{{ $semester->id_semester }}">{{ ucfirst($semester->nama) }} {{ $semester->tahun }}{{ $semester->is_aktif ? ' (aktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="blok-kelas-kuliah" class="form-label">Blok</label>
                    <select id="blok-kelas-kuliah" wire:model.live="blok_id" class="form-select">
                        <option value="">Semua blok</option>
                        @foreach ($this->blokList as $blok)
                            <option value="{{ $blok->id }}">{{ $blok->nama }} · {{ $blok->prodi?->kode }} · {{ $blok->semester?->nama }} {{ $blok->semester?->tahun }}</option>
                        @endforeach
                    </select>
                    @error('blok_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
        <li class="nav-item"><button type="button" class="nav-link {{ $tab === 'kelas' ? 'active' : '' }}" wire:click="setTab('kelas')"><i class="ri-layout-grid-line"></i> Kelas Kuliah</button></li>
        <li class="nav-item"><button type="button" class="nav-link {{ $tab === 'dpna' ? 'active' : '' }}" wire:click="setTab('dpna')"><i class="ri-file-list-3-line"></i> DPNA Kelas</button></li>
        <li class="nav-item"><button type="button" class="nav-link {{ $tab === 'monitoring' ? 'active' : '' }}" wire:click="setTab('monitoring')"><i class="ri-pulse-line"></i> Monitoring</button></li>
    </ul>

    @if ($tab === 'kelas')
        <div class="card">
            <div class="card-header">
                <h5 class="mb-1">Daftar Kelas</h5>
                <div class="text-muted small">Semester sampai 20261 memakai API SIA. Mulai 20262 kelas dibuat dan dibagi lokal.</div>
            </div>
            <div class="card-body border-bottom">
                @php($hasilKelas = $this->hasilSinkronisasiKelas)
                @php($hasilPeserta = $this->hasilSinkronisasiPeserta)
                @php($semesterApi = $semesterTerpilih?->bersumberDariApi() ?? false)
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h5 class="mb-1">Sinkronisasi API SIA</h5>
                        <div class="text-muted small">Ambil dan simpan seluruh kelas semester dari endpoint kelas, lalu sinkronkan anggota dari endpoint KRS.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" wire:click="ambilKelas" wire:loading.attr="disabled" wire:target="ambilKelas" @disabled(! $semesterApi)>
                            <span wire:loading.remove wire:target="ambilKelas"><i class="ri-download-cloud-2-line"></i> Ambil Kelas</span>
                            <span wire:loading wire:target="ambilKelas">Mengambil...</span>
                        </button>
                        <button type="button" class="btn btn-success" wire:click="sinkronkanAnggotaKelas" wire:confirm="Sinkronkan seluruh anggota kelas dari KRS untuk semester terpilih? Pastikan kelas sudah disimpan." wire:loading.attr="disabled" wire:target="sinkronkanAnggotaKelas" @disabled(! $semesterApi)>
                            <span wire:loading.remove wire:target="sinkronkanAnggotaKelas"><i class="ri-group-line"></i> Sinkronisasi Anggota Kelas</span>
                            <span wire:loading wire:target="sinkronkanAnggotaKelas">Menyinkronkan...</span>
                        </button>
                    </div>
                </div>
                @error('semester_id') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                @if (! $semesterTerpilih)
                    <div class="alert alert-warning mt-3 mb-0">Pilih semester untuk menjalankan sinkronisasi.</div>
                @elseif (! $semesterApi)
                    <div class="alert alert-info mt-3 mb-0">Semester {{ $semesterTerpilih->kode }} dikelola lokal dan tidak menerima sinkronisasi inbound dari API.</div>
                @else
                    <div class="row g-3 mt-1">
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div><div class="fw-semibold">Hasil Kelas</div><div class="text-muted small">Endpoint kelas · semester {{ $semesterTerpilih->kode }}</div></div>
                                    <span class="badge {{ ($hasilKelas['status'] ?? null) === 'success' ? 'bg-success-subtle text-success' : (($hasilKelas['status'] ?? null) === 'error' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }}">{{ ($hasilKelas['status'] ?? null) === 'success' ? 'Berhasil' : (($hasilKelas['status'] ?? null) === 'error' ? 'Gagal' : 'Belum pernah') }}</span>
                                </div>
                                @if ($hasilKelas)
                                    <div class="small mt-2">{{ $hasilKelas['pesan'] }}</div>
                                    <div class="text-muted small mt-1">Selesai {{ $hasilKelas['selesai_pada'] }}</div>
                                    @if (($hasilKelas['status'] ?? null) === 'success')
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <span class="badge bg-light text-body">Diterima {{ $hasilKelas['diterima'] }}</span>
                                            <span class="badge bg-light text-body">Dibuat {{ $hasilKelas['dibuat'] }}</span>
                                            <span class="badge bg-light text-body">Diubah {{ $hasilKelas['diubah'] }}</span>
                                            <span class="badge bg-light text-body">Dipulihkan {{ $hasilKelas['dipulihkan'] }}</span>
                                            <span class="badge bg-light text-body">Tetap {{ $hasilKelas['tetap'] }}</span>
                                            <span class="badge bg-light text-body">Tanpa mata kuliah {{ $hasilKelas['tanpa_mata_kuliah'] }}</span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div><div class="fw-semibold">Hasil Anggota Kelas</div><div class="text-muted small">Endpoint KRS · semester {{ $semesterTerpilih->kode }}</div></div>
                                    <span class="badge {{ ($hasilPeserta['status'] ?? null) === 'success' ? 'bg-success-subtle text-success' : (($hasilPeserta['status'] ?? null) === 'error' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary') }}">{{ ($hasilPeserta['status'] ?? null) === 'success' ? 'Berhasil' : (($hasilPeserta['status'] ?? null) === 'error' ? 'Gagal' : 'Belum pernah') }}</span>
                                </div>
                                @if ($hasilPeserta)
                                    <div class="small mt-2">{{ $hasilPeserta['pesan'] }}</div>
                                    <div class="text-muted small mt-1">Selesai {{ $hasilPeserta['selesai_pada'] }}</div>
                                    @if (($hasilPeserta['status'] ?? null) === 'success')
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <span class="badge bg-light text-body">Diterima {{ $hasilPeserta['diterima'] }}</span>
                                            <span class="badge bg-light text-body">Dibuat {{ $hasilPeserta['dibuat'] }}</span>
                                            <span class="badge bg-light text-body">Diubah {{ $hasilPeserta['diubah'] }}</span>
                                            <span class="badge bg-light text-body">Ditautkan {{ $hasilPeserta['ditautkan'] }}</span>
                                            <span class="badge bg-light text-body">Dilewati {{ $hasilPeserta['dilewati'] }}</span>
                                            <span class="badge bg-light text-body">Tanpa mahasiswa {{ $hasilPeserta['tanpa_mahasiswa'] }}</span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            @if ($blok_id && $semesterTerpilih?->dikelolaLokal())
                <div class="card-body border-bottom">
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <form wire:submit="buatKelasManual" class="row g-2">
                                <div class="col-md-3"><label class="form-label">Kode</label><input type="text" class="form-control" wire:model="kelas_baru_kode">@error('kelas_baru_kode') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-md-4"><label class="form-label">Nama</label><input type="text" class="form-control" wire:model="kelas_baru_nama">@error('kelas_baru_nama') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-md-3"><label class="form-label">Kapasitas</label><input type="number" min="1" max="65535" class="form-control" wire:model="kelas_baru_kapasitas">@error('kelas_baru_kapasitas') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100">Buat</button></div>
                            </form>
                        </div>
                        <div class="col-lg-5 border-start">
                            <form wire:submit="buatKelasOtomatis" class="row g-2">
                                <div class="col-8"><label class="form-label">Jumlah kelas otomatis</label><input type="number" min="1" max="26" class="form-control" wire:model="jumlah_kelas">@error('jumlah_kelas') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-4 d-flex align-items-end"><button type="submit" class="btn btn-success w-100" wire:confirm="Buat kelas A–Z dan langsung bagi semua peserta?">Buat & Bagi</button></div>
                            </form>
                            <div class="text-muted small mt-1">Hanya saat blok belum punya riwayat kelas. Maksimal 26.</div>
                        </div>
                    </div>
                </div>
                <form wire:submit="bagiKelas" class="card-body border-bottom">
                    <button type="submit" class="btn btn-success" wire:confirm="Buat ulang pembagian peserta ke seluruh kelas lokal aktif pada blok ini?" wire:loading.attr="disabled" wire:target="bagiKelas">
                        <span wire:loading.remove wire:target="bagiKelas"><i class="ri-layout-grid-line"></i> Bagi Peserta</span>
                        <span wire:loading wire:target="bagiKelas">Membagi...</span>
                    </button>
                    <span class="text-muted small ms-2">Memakai semua kelas lokal aktif; pembagian deterministik dan merata.</span>
                </form>
            @endif
            @if ($edit_kelas_id)
                @php($kelasDiedit = $this->kelasList->firstWhere('id_kelas_kuliah', $edit_kelas_id))
                <form wire:submit="simpanEditKelas" class="card-body border-bottom bg-light-subtle">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Edit Kelas Kuliah</h5>
                            <div class="text-muted small">Kelas API hanya dapat diubah pemetaan bloknya. Kelas lokal dapat diubah penuh.</div>
                        </div>
                        <button type="button" class="btn btn-light btn-sm" wire:click="tutupEditKelas">Tutup</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label for="edit-kode-kelas" class="form-label">Kode</label>
                            <input id="edit-kode-kelas" type="text" class="form-control" wire:model="edit_kode" @disabled($kelasDiedit?->semester?->bersumberDariApi())>
                            @error('edit_kode') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="edit-nama-kelas" class="form-label">Nama</label>
                            <input id="edit-nama-kelas" type="text" class="form-control" wire:model="edit_nama" @disabled($kelasDiedit?->semester?->bersumberDariApi())>
                            @error('edit_nama') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label for="edit-kapasitas-kelas" class="form-label">Kapasitas</label>
                            <input id="edit-kapasitas-kelas" type="number" min="1" max="65535" class="form-control" wire:model="edit_kapasitas" @disabled($kelasDiedit?->semester?->bersumberDariApi())>
                            @error('edit_kapasitas') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label for="edit-status-kelas" class="form-label">Status</label>
                            <select id="edit-status-kelas" class="form-select" wire:model="edit_status" @disabled($kelasDiedit?->semester?->bersumberDariApi())>
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                            @error('edit_status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="edit-blok-kelas" class="form-label">Mapping Blok</label>
                            <select id="edit-blok-kelas" class="form-select" wire:model="edit_blok_id" @disabled($kelasDiedit?->semester?->dikelolaLokal())>
                                <option value="">Belum dipetakan</option>
                                @foreach ($this->blokEditList as $blokEdit)
                                    <option value="{{ $blokEdit->id }}">{{ $blokEdit->nama }} · {{ $blokEdit->prodi?->kode }} · {{ $blokEdit->semester?->nama }} {{ $blokEdit->semester?->tahun }}</option>
                                @endforeach
                            </select>
                            @error('edit_blok_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpanEditKelas">
                            <span wire:loading.remove wire:target="simpanEditKelas">Simpan Perubahan</span>
                            <span wire:loading wire:target="simpanEditKelas">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            @endif
            @error('kelas') <div class="alert alert-danger rounded-0 mb-0">{{ $message }}</div> @enderror
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>ID SIA</th><th>Prodi / Mata Kuliah</th><th>Kelas</th><th>Blok</th><th>Kapasitas</th><th>Anggota</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($this->kelasList as $kelas)
                            <tr>
                                <td><code>{{ $kelas->id_sumber ?: 'lokal' }}</code><br><small class="text-muted">{{ $kelas->semester?->kode }}</small></td>
                                <td><span class="fw-semibold">{{ $kelas->mata_kuliah?->prodi?->kode ?? $kelas->kode_prodi_sumber }}</span><br><small>{{ $kelas->mata_kuliah?->kode ?? $kelas->kode_mata_kuliah_sumber }}{{ $kelas->mata_kuliah?->nama ? ' · '.$kelas->mata_kuliah->nama : ' · Belum dipetakan' }}</small></td>
                                <td class="fw-semibold">{{ $kelas->kode }}<br><small class="text-muted">{{ $kelas->nama }}</small></td>
                                <td>@if ($kelas->blok)<span class="badge bg-success-subtle text-success">{{ $kelas->blok->nama }}</span>@else<span class="badge bg-secondary-subtle text-secondary">Belum dipetakan</span>@endif</td>
                                <td>{{ $kelas->kapasitas }}</td>
                                <td>{{ $kelas->anggota_kelas_kuliah_count }}<br><span class="badge {{ $kelas->status === 'aktif' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ ucfirst($kelas->status) }}</span></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-soft-info btn-sm" wire:click="lihatDaftarPeserta({{ $kelas->id_kelas_kuliah }})">Lihat Daftar Peserta</button>
                                    @if ($kelas->semester?->dikelolaLokal() && $kelas->blok_id && $kelas->status === 'aktif')
                                        <button type="button" class="btn btn-soft-success btn-sm" wire:click="kelolaPeserta({{ $kelas->id_kelas_kuliah }})">Peserta</button>
                                    @endif
                                    <button type="button" class="btn btn-soft-primary btn-sm" wire:click="editKelas({{ $kelas->id_kelas_kuliah }})">Edit</button>
                                    @if ($kelas->semester?->bersumberDariApi() && $blok_id)
                                        @if ((int) $kelas->blok_id === (int) $blok_id)
                                            <button type="button" class="btn btn-soft-danger btn-sm" wire:click="lepasPemetaan({{ $kelas->id_kelas_kuliah }})" wire:confirm="Lepas pemetaan kelas dari blok?">Lepas</button>
                                        @else
                                            <button type="button" class="btn btn-soft-primary btn-sm" wire:click="petakan({{ $kelas->id_kelas_kuliah }})">Petakan</button>
                                        @endif
                                    @endif
                                    @if ($kelas->semester?->dikelolaLokal())
                                        <button type="button" class="btn btn-soft-danger btn-sm" wire:click="hapusKelas({{ $kelas->id_kelas_kuliah }})" wire:confirm="Hapus kelas lokal {{ $kelas->kode }}?">Hapus</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada kelas kuliah pada filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
                @if ($lihat_peserta_kelas_id)
                    @php($kelasDilihat = $this->kelasList->firstWhere('id_kelas_kuliah', $lihat_peserta_kelas_id))
                    @if ($kelasDilihat)
                        <div class="card-body border-top">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1">Daftar Peserta {{ $kelasDilihat->kode }}{{ $kelasDilihat->nama ? ' - '.$kelasDilihat->nama : '' }}</h5>
                                    <div class="text-muted small">Read-only. Nilai tidak ditampilkan karena data KRS lokal belum menyimpan nilai.</div>
                                </div>
                                <button type="button" class="btn btn-light btn-sm" wire:click="tutupDaftarPeserta">Tutup</button>
                            </div>

                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input type="search" class="form-control" placeholder="Cari nama atau NIM" wire:model.live.debounce.400ms="daftar_peserta_search">
                            </div>

                            <div class="table-responsive mb-3">
                                <table class="table table-hover align-middle mb-0">
                                    <thead><tr><th style="width: 70px">No.</th><th>NIM</th><th>Nama Mahasiswa</th><th>Status Tautan</th></tr></thead>
                                    <tbody>
                                        @forelse ($this->daftarPeserta as $anggota)
                                            <tr>
                                                <td>{{ $this->daftarPeserta->firstItem() + $loop->index }}</td>
                                                <td><code>{{ $anggota->peserta_nim }}</code></td>
                                                <td>{{ $anggota->peserta_nama ?: 'Belum terhubung ke mahasiswa lokal' }}</td>
                                                <td>
                                                    @if ($anggota->peserta_blok_id)
                                                        <span class="badge bg-success-subtle text-success">Tertaut</span>
                                                    @else
                                                        <span class="badge bg-warning-subtle text-warning">Belum tertaut</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center text-muted py-4">Peserta tidak ditemukan.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{ $this->daftarPeserta->links() }}
                        </div>
                    @endif
                @endif
                @if ($kelola_peserta_kelas_id)
                    @php($kelasKelola = $this->kelasList->firstWhere('id_kelas_kuliah', $kelola_peserta_kelas_id))
                    @if ($kelasKelola)
                        <form wire:submit="simpanPesertaKelas" class="card-body border-top">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1">Peserta {{ $kelasKelola->kode }}{{ $kelasKelola->nama ? ' - '.$kelasKelola->nama : '' }}</h5>
                                    <div class="text-muted small">Centang untuk memasukkan atau memindahkan peserta ke kelas ini. Hapus centang untuk mengeluarkan.</div>
                                </div>
                                <button type="button" class="btn btn-light btn-sm" wire:click="tutupKelolaPeserta">Tutup</button>
                            </div>

                            @error('peserta_ids') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input type="search" class="form-control" placeholder="Cari nama atau NIM" wire:model.live.debounce.400ms="peserta_search">
                                <span class="input-group-text">{{ count($peserta_ids) }} dipilih</span>
                            </div>

                            @php($pageIds = $this->pesertaKelas->pluck('id_peserta_blok')->map(fn ($id) => (string) $id)->all())
                            @php($pageAllSelected = $pageIds !== [] && empty(array_diff($pageIds, array_map('strval', $peserta_ids))))
                            <div class="border rounded mb-3">
                                <div class="form-check border-bottom p-3 ps-5 mb-0">
                                    <input class="form-check-input" type="checkbox" id="peserta-kelas-page-all" wire:click="togglePagePesertaKelas" @checked($pageAllSelected) @disabled($pageIds === [])>
                                    <label class="form-check-label fw-semibold" for="peserta-kelas-page-all">Pilih semua di halaman ini</label>
                                </div>
                                @forelse ($this->pesertaKelas as $peserta)
                                    @php($kelasSaatIni = $peserta->anggota_kelas_kuliah?->kelas_kuliah)
                                    <div class="form-check border-bottom p-3 ps-5 mb-0">
                                        <input class="form-check-input" type="checkbox" value="{{ $peserta->id_peserta_blok }}" wire:model="peserta_ids" id="peserta-kelas-{{ $peserta->id_peserta_blok }}">
                                        <label class="form-check-label d-flex flex-wrap justify-content-between gap-2 w-100" for="peserta-kelas-{{ $peserta->id_peserta_blok }}">
                                            <span><strong>{{ $peserta->mahasiswa?->nama }}</strong><br><small class="text-muted">{{ $peserta->mahasiswa?->nim }}</small></span>
                                            @if ($kelasSaatIni)
                                                <span class="badge {{ (int) $kelasSaatIni->id_kelas_kuliah === (int) $kelola_peserta_kelas_id ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }} align-self-center">Saat ini: {{ $kelasSaatIni->kode }}</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary align-self-center">Belum terbagi</span>
                                            @endif
                                        </label>
                                    </div>
                                @empty
                                    <div class="text-center text-muted p-4">Peserta tidak ditemukan.</div>
                                @endforelse
                            </div>

                            {{ $this->pesertaKelas->links() }}
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-success" wire:loading.attr="disabled" wire:target="simpanPesertaKelas">
                                    <span wire:loading.remove wire:target="simpanPesertaKelas">Simpan Peserta</span>
                                    <span wire:loading wire:target="simpanPesertaKelas">Menyimpan...</span>
                                </button>
                            </div>
                        </form>
                    @endif
                @endif
            </div>
    @elseif ($tab === 'dpna')
        <div class="alert alert-info"><i class="ri-information-line"></i> Nilai hanya dibaca dari <code>snapshot_dpna_peserta</code> pada finalisasi berstatus <code>final</code>.</div>
        <div class="card">
            <div class="card-body">
                <label for="kelas-dpna" class="form-label">Kelas Kuliah Aktif</label>
                <select id="kelas-dpna" wire:model.live="kelas_kuliah_id" class="form-select">
                    <option value="">Pilih kelas</option>
                    @foreach ($this->kelasList->where('status', 'aktif')->whereNotNull('blok_id') as $kelas)
                        <option value="{{ $kelas->id_kelas_kuliah }}">{{ $kelas->blok?->nama }} · {{ $kelas->kode }}{{ $kelas->nama ? ' - '.$kelas->nama : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($this->dpna)
            @php($data = $this->dpna)
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><h5 class="mb-1">{{ $data['kelas']->kode }}{{ $data['kelas']->nama ? ' - '.$data['kelas']->nama : '' }}</h5><div class="text-muted small">{{ $data['kelas']->blok?->nama }} · {{ $data['kelas']->blok?->prodi?->nama }}</div></div>
                    @if ($data['finalisasi'])<span class="badge bg-success-subtle text-success">Final versi {{ $data['finalisasi']->versi }}</span>@else<span class="badge bg-warning-subtle text-warning">Belum final</span>@endif
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>#</th><th>NIM</th><th>Mahasiswa</th><th class="text-center">Nilai Akhir</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($data['anggota'] as $anggota)
                                @php($nilai = $data['snapshot']->get($anggota->peserta_blok_id))
                                <tr><td>{{ $loop->iteration }}</td><td>{{ $anggota->mahasiswa?->nim }}</td><td>{{ $anggota->mahasiswa?->nama }}</td><td class="text-center fw-semibold">{{ $nilai ? number_format((float) $nilai->nilai_akhir, 2, ',', '.') : '-' }}</td><td>@if ($nilai)<span class="badge bg-success-subtle text-success">Snapshot final</span>@else<span class="badge bg-warning-subtle text-warning">Belum tersedia</span>@endif</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Kelas belum memiliki anggota.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @else
        <div class="alert alert-secondary"><i class="ri-database-2-line"></i> Monitoring lokal. Koneksi, batch, retry, dan status API menunggu kontrak eksternal.</div>
        <div class="card">
            <div class="card-header"><h5 class="mb-1">Kesiapan Outbound per Blok</h5><div class="text-muted small">Siap bila semua peserta aktif terbagi dan DPNA final tersedia.</div></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Blok</th><th>Prodi / Semester</th><th class="text-center">Kelas</th><th class="text-center">Pembagian</th><th class="text-center">DPNA</th><th class="text-center">Status</th></tr></thead>
                    <tbody>
                        @forelse ($this->monitoring as $blok)
                            @php($siap = $blok->kelas_aktif_count > 0 && $blok->peserta_aktif_count > 0 && $blok->peserta_aktif_count === $blok->peserta_terbagi_count && $blok->dpna_final_count > 0)
                            <tr><td class="fw-semibold">{{ $blok->nama }}</td><td>{{ $blok->prodi?->kode }} · {{ $blok->semester?->nama }} {{ $blok->semester?->tahun }}</td><td class="text-center">{{ $blok->kelas_aktif_count }}</td><td class="text-center">{{ $blok->peserta_terbagi_count }} / {{ $blok->peserta_aktif_count }}</td><td class="text-center">@if ($blok->dpna_final_count)<span class="badge bg-success-subtle text-success">Final</span>@else<span class="badge bg-warning-subtle text-warning">Belum final</span>@endif</td><td class="text-center"><span class="badge {{ $siap ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $siap ? 'Siap' : 'Belum siap' }}</span></td></tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada blok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>