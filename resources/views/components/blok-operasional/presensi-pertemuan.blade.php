<?php

use App\Models\PertemuanBlok;
use App\Models\PesertaBlok;
use App\Models\PresensiPertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pengisian presensi satu pertemuan.
 *
 * Peserta sesi berasal dari `anggota_kelompok_blok` pada kelompok pertemuan tersebut
 * (`task/task_3.md:252-255`), bukan dari seluruh peserta blok.
 *
 * Status awal setiap mahasiswa adalah `hadir`; dosen hanya mengubah yang tidak hadir.
 * Simpan menulis satu baris per peserta lewat `updateOrCreate`, seluruhnya dalam satu
 * `DB::transaction` sesuai aturan operasi massal di `task/task_3.md:73`.
 */
new class extends Component
{
    public int $pertemuan_blok_id;
    public bool $tampilkan_tombol_simpan = true;

    /** @var array<int|string, string> */
    public array $status = [];

    /** @var array<int|string, string> */
    public array $keterangan = [];

    private ?Collection $anggotaCache = null;

    public function mount(int $pertemuan_blok_id): void
    {
        $this->pertemuan_blok_id = $pertemuan_blok_id;

        abort_unless(
            AksesPertemuanBlok::bolehKelolaPertemuan(auth()->user(), $this->pertemuan_blok_id),
            403
        );

        $this->muatPresensi();
    }

    /**
     * Prefill: baris yang sudah ada dipakai apa adanya, yang belum ada dianggap hadir.
     */
    private function muatPresensi(): void
    {
        $tersimpan = PresensiPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->get(['peserta_blok_id', 'status', 'keterangan'])
            ->keyBy('peserta_blok_id');

        foreach ($this->anggota() as $peserta) {
            $id = $peserta->id_peserta_blok;
            $baris = $tersimpan->get($id);

            $this->status[$id] = $baris?->status ?? 'hadir';
            $this->keterangan[$id] = (string) ($baris?->keterangan ?? '');
        }
    }

    private function pertemuan(): PertemuanBlok
    {
        return PertemuanBlok::query()
            ->with('aturan_kegiatan_blok:id,perlu_presensi')
            ->findOrFail($this->pertemuan_blok_id);
    }

    /**
     * Daftar anggota kelompok pertemuan ini. Selalu dibaca dari database, tidak dari
     * state komponen, karena kunci array `status` bisa diubah dari sisi klien.
     */
    public function anggota(): Collection
    {
        if ($this->anggotaCache !== null) {
            return $this->anggotaCache;
        }

        $pertemuan = $this->pertemuan();

        return $this->anggotaCache = PesertaBlok::query()
            ->select('peserta_blok.*')
            ->join('anggota_kelompok_blok', 'anggota_kelompok_blok.peserta_blok_id', '=', 'peserta_blok.id_peserta_blok')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
            ->where('anggota_kelompok_blok.kelompok_blok_id', $pertemuan->kelompok_blok_id)
            ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
            ->with('mahasiswa:id_mahasiswa,nim,nama')
            ->orderBy('mahasiswa.nama')
            ->get();
    }

    public function terkunci(): bool
    {
        return AksesPertemuanBlok::terkunci($this->pertemuan_blok_id);
    }

    public function bolehIsi(): bool
    {
        return AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $this->pertemuan_blok_id);
    }

    public function setStatus(string $pesertaId, string $status): void
    {
        if (! $this->bolehIsi() || ! in_array($status, PresensiPertemuanBlok::SEMUA_STATUS, true)) {
            return;
        }

        $this->status[$pesertaId] = $status;

        if ($status === 'hadir') {
            $this->keterangan[$pesertaId] = '';
        }
    }

    public function semuaHadir(): void
    {
        if (! $this->bolehIsi()) {
            return;
        }

        foreach ($this->anggota() as $peserta) {
            $this->status[$peserta->id_peserta_blok] = 'hadir';
            $this->keterangan[$peserta->id_peserta_blok] = '';
        }
    }

    #[On('simpan-pelaksanaan')]
    public function simpanDariPelaksanaan(int $pertemuan_blok_id): void
    {
        if ($pertemuan_blok_id === $this->pertemuan_blok_id) {
            $this->simpan();
        }
    }

    public function simpan(): void
    {
        abort_unless($this->bolehIsi(), 403);

        $this->validate([
            'status.*' => ['required', 'in:hadir,sakit,izin,alpa'],
            'keterangan.*' => ['nullable', 'string', 'max:255'],
        ], [
            'status.*.required' => 'Status kehadiran wajib dipilih.',
            'status.*.in' => 'Status kehadiran tidak valid.',
            'keterangan.*.max' => 'Keterangan maksimal 255 karakter.',
        ]);

        $anggota = $this->anggota();

        if ($anggota->isEmpty()) {
            $this->dispatch('notify', message: [
                'status' => 'failed',
                'message' => 'Kelompok pertemuan ini belum punya anggota aktif.',
            ]);

            return;
        }

        // Iterasi atas daftar dari database, bukan atas kunci $this->status, supaya
        // peserta dari kelompok atau blok lain tidak bisa disusupkan dari klien.
        DB::transaction(function () use ($anggota) {
            foreach ($anggota as $peserta) {
                $id = $peserta->id_peserta_blok;
                $status = $this->status[$id] ?? 'hadir';
                $keterangan = trim((string) ($this->keterangan[$id] ?? ''));

                PresensiPertemuanBlok::updateOrCreate(
                    [
                        'pertemuan_blok_id' => $this->pertemuan_blok_id,
                        'peserta_blok_id' => $id,
                    ],
                    [
                        'status' => in_array($status, PresensiPertemuanBlok::SEMUA_STATUS, true) ? $status : 'hadir',
                        'keterangan' => $keterangan !== '' ? $keterangan : null,
                        'dicatat_oleh_user_id' => auth()->id(),
                    ]
                );
            }
        });

        $this->dispatch('presensi-pertemuan-tersimpan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Presensi berhasil disimpan.',
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function rekap(): array
    {
        $hitung = array_fill_keys(PresensiPertemuanBlok::SEMUA_STATUS, 0);

        foreach ($this->anggota() as $peserta) {
            $status = $this->status[$peserta->id_peserta_blok] ?? 'hadir';

            if (isset($hitung[$status])) {
                $hitung[$status]++;
            }
        }

        return $hitung;
    }

    public function render()
    {
        $pertemuan = $this->pertemuan();

        return $this->view([
            'anggota' => $this->anggota(),
            'rekap' => $this->rekap(),
            'terkunci' => $this->terkunci(),
            'bolehIsi' => $this->bolehIsi(),
            'perluPresensi' => (bool) ($pertemuan->aturan_kegiatan_blok?->perlu_presensi ?? true),
        ]);
    }
};
?>

<div>
    <x-full-page-loading message="Memproses operasional blok..." />
    @php($label = ['hadir' => 'Hadir', 'sakit' => 'Sakit', 'izin' => 'Izin', 'alpa' => 'Alpa'])
    @php($warna = ['hadir' => 'success', 'sakit' => 'warning', 'izin' => 'info', 'alpa' => 'danger'])

    @if ($terkunci)
        <div class="alert alert-secondary py-2 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-lock-line"></i>
            Pertemuan ini sudah divalidasi, presensi terkunci. Pengelola dapat membuka validasi dari tab Jurnal bila perlu koreksi.
        </div>
    @endif

    @if (! $perluPresensi)
        <div class="alert alert-warning py-2 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-information-line"></i>
            Jenis kegiatan ini ditandai <span class="fw-semibold">tidak perlu presensi</span> pada susunan blok.
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="d-flex flex-wrap gap-1">
            @foreach ($label as $kunci => $teks)
                <span class="badge bg-{{ $warna[$kunci] }}-subtle text-{{ $warna[$kunci] }}">
                    {{ $teks }}: {{ $rekap[$kunci] }}
                </span>
            @endforeach
            <span class="badge bg-light text-dark border">Total: {{ $anggota->count() }}</span>
        </div>

        @if ($bolehIsi)
            <button type="button" class="btn btn-secondary btn-sm" wire:click="semuaHadir">
                <i class="ri-check-double-line"></i> Tandai Semua Hadir
            </button>
        @endif
    </div>

    @if ($anggota->isEmpty())
        <div class="alert alert-warning py-2 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-group-line"></i>
            Kelompok pertemuan ini belum punya anggota aktif. Isi anggota kelompok terlebih dahulu.
        </div>
    @else
        <form wire:submit="simpan">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Mahasiswa</th>
                            <th>Kehadiran</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anggota as $index => $peserta)
                            @php($id = $peserta->id_peserta_blok)
                            @php($statusAktif = $status[$id] ?? 'hadir')
                            <tr wire:key="presensi-{{ $id }}">
                                <td class="text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <div class="small fw-semibold">{{ $peserta->mahasiswa?->nama }}</div>
                                    <div class="text-muted small">{{ $peserta->mahasiswa?->nim }}</div>
                                </td>
                                <td>
                                    @if ($bolehIsi)
                                        <div class="btn-group btn-group-sm" role="group">
                                            @foreach ($label as $kunci => $teks)
                                                <button type="button"
                                                    class="btn btn-sm {{ $statusAktif === $kunci ? 'btn-'.$warna[$kunci] : 'btn-light' }}"
                                                    wire:click="setStatus('{{ $id }}', '{{ $kunci }}')">
                                                    {{ $teks }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="badge bg-{{ $warna[$statusAktif] }}-subtle text-{{ $warna[$statusAktif] }}">
                                            {{ $label[$statusAktif] ?? $statusAktif }}
                                        </span>
                                    @endif
                                    @error('status.'.$id) <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                                </td>
                                <td style="min-width: 200px;">
                                    @if ($bolehIsi && $statusAktif !== 'hadir')
                                        <input type="text" class="form-control form-control-sm"
                                            placeholder="Alasan atau catatan"
                                            wire:model="keterangan.{{ $id }}">
                                        @error('keterangan.'.$id) <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                                    @elseif (($keterangan[$id] ?? '') !== '')
                                        <span class="text-muted small">{{ $keterangan[$id] }}</span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($bolehIsi && $tampilkan_tombol_simpan)
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="simpan">
                        <i class="ri-save-line"></i> SIMPAN PRESENSI
                    </button>
                </div>
            @endif
        </form>
    @endif
</div>
