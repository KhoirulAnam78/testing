<?php

use App\Models\PertemuanBlok;
use App\Models\PesertaBlok;
use App\Models\PresensiPertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

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
    use WithFileUploads;

    public int $pertemuan_blok_id;

    public bool $tampilkan_tombol_simpan = true;

    /** @var array<int|string, string> */
    public array $status = [];

    /** @var array<int|string, string> */
    public array $keterangan = [];

    /** @var array<int|string, mixed> */
    public array $surat = [];

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

        if (! in_array($status, ['sakit', 'izin'], true)) {
            unset($this->surat[$pesertaId]);
        }

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
            unset($this->surat[$peserta->id_peserta_blok]);
        }
    }

    public function hapusSurat(int $pesertaId): void
    {
        abort_unless($this->bolehIsi(), 403);
        abort_unless($this->anggota()->contains(
            fn ($peserta) => (int) $peserta->id_peserta_blok === $pesertaId
        ), 404);

        $presensi = PresensiPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->where('peserta_blok_id', $pesertaId)
            ->firstOrFail();
        $pathLama = $presensi->path_surat_keterangan;

        $presensi->update([
            'path_surat_keterangan' => null,
            'nama_file_surat_keterangan' => null,
            'ukuran_file_surat_keterangan' => null,
            'mime_surat_keterangan' => null,
        ]);

        if ($pathLama) {
            Storage::disk('local')->delete($pathLama);
        }

        unset($this->surat[$pesertaId]);
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Surat keterangan berhasil dihapus.',
        ]);
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
            'surat.*' => ['nullable', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'status.*.required' => 'Status kehadiran wajib dipilih.',
            'status.*.in' => 'Status kehadiran tidak valid.',
            'keterangan.*.max' => 'Keterangan maksimal 255 karakter.',
            'surat.*.file' => 'Surat keterangan wajib berupa file.',
            'surat.*.mimetypes' => 'Surat keterangan wajib berformat PDF, JPG, atau PNG.',
            'surat.*.mimes' => 'Surat keterangan wajib berformat PDF, JPG, atau PNG.',
            'surat.*.max' => 'Ukuran surat keterangan maksimal 5 MB.',
        ]);

        $anggota = $this->anggota();

        if ($anggota->isEmpty()) {
            $this->dispatch('notify', message: [
                'status' => 'failed',
                'message' => 'Kelompok pertemuan ini belum punya anggota aktif.',
            ]);

            return;
        }

        foreach ($anggota as $peserta) {
            $id = $peserta->id_peserta_blok;
            if (isset($this->surat[$id]) && ! in_array($this->status[$id] ?? 'hadir', ['sakit', 'izin'], true)) {
                $this->addError("surat.{$id}", 'Surat keterangan hanya dapat diunggah untuk status Sakit atau Izin.');
            }
            if (isset($this->surat[$id]) && mb_strlen($this->surat[$id]->getClientOriginalName()) > 255) {
                $this->addError("surat.{$id}", 'Nama file surat keterangan maksimal 255 karakter.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $fileBaru = [];
        try {
            foreach ($anggota as $peserta) {
                $id = $peserta->id_peserta_blok;
                if (! isset($this->surat[$id])) {
                    continue;
                }

                $namaFile = $this->surat[$id]->getClientOriginalName();
                $ukuranFile = $this->surat[$id]->getSize();
                $mime = $this->surat[$id]->getMimeType();
                $path = $this->surat[$id]->store("surat-keterangan/{$this->pertemuan_blok_id}", 'local');
                abort_unless($path, 500, 'Surat keterangan gagal disimpan.');
                $fileBaru[$id] = [
                    'path_surat_keterangan' => $path,
                    'nama_file_surat_keterangan' => $namaFile,
                    'ukuran_file_surat_keterangan' => $ukuranFile,
                    'mime_surat_keterangan' => $mime,
                ];
            }
        } catch (Throwable $e) {
            Storage::disk('local')->delete(array_column($fileBaru, 'path_surat_keterangan'));
            throw $e;
        }

        $tersimpan = PresensiPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->whereIn('peserta_blok_id', $anggota->pluck('id_peserta_blok'))
            ->get()
            ->keyBy('peserta_blok_id');
        $fileLamaDihapus = [];

        // Iterasi atas daftar dari database, bukan atas kunci $this->status, supaya
        // peserta dari kelompok atau blok lain tidak bisa disusupkan dari klien.
        try {
            DB::transaction(function () use ($anggota, $fileBaru, $tersimpan, &$fileLamaDihapus) {
                foreach ($anggota as $peserta) {
                    $id = $peserta->id_peserta_blok;
                    $status = $this->status[$id] ?? 'hadir';
                    $status = in_array($status, PresensiPertemuanBlok::SEMUA_STATUS, true) ? $status : 'hadir';
                    $keterangan = trim((string) ($this->keterangan[$id] ?? ''));
                    $lama = $tersimpan->get($id);
                    $dataSurat = [];

                    if (isset($fileBaru[$id])) {
                        $dataSurat = $fileBaru[$id];
                        if ($lama?->path_surat_keterangan) {
                            $fileLamaDihapus[] = $lama->path_surat_keterangan;
                        }
                    } elseif (! in_array($status, ['sakit', 'izin'], true)) {
                        $dataSurat = [
                            'path_surat_keterangan' => null,
                            'nama_file_surat_keterangan' => null,
                            'ukuran_file_surat_keterangan' => null,
                            'mime_surat_keterangan' => null,
                        ];
                        if ($lama?->path_surat_keterangan) {
                            $fileLamaDihapus[] = $lama->path_surat_keterangan;
                        }
                    }

                    PresensiPertemuanBlok::updateOrCreate(
                        [
                            'pertemuan_blok_id' => $this->pertemuan_blok_id,
                            'peserta_blok_id' => $id,
                        ],
                        [
                            'status' => $status,
                            'keterangan' => $keterangan !== '' ? $keterangan : null,
                            'dicatat_oleh_user_id' => auth()->id(),
                            ...$dataSurat,
                        ]
                    );
                }
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete(array_column($fileBaru, 'path_surat_keterangan'));
            throw $e;
        }

        Storage::disk('local')->delete($fileLamaDihapus);
        $this->reset('surat');

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
            'suratTersimpan' => PresensiPertemuanBlok::query()
                ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
                ->whereNotNull('path_surat_keterangan')
                ->get([
                    'id_presensi_pertemuan_blok',
                    'peserta_blok_id',
                    'nama_file_surat_keterangan',
                    'ukuran_file_surat_keterangan',
                ])
                ->keyBy('peserta_blok_id'),
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
                            <th>Surat Keterangan</th>
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
                                <td style="min-width: 240px;">
                                    @php($suratAda = $suratTersimpan->get($id))
                                    @if (in_array($statusAktif, ['sakit', 'izin'], true))
                                        @if ($suratAda)
                                            <div class="small text-truncate" style="max-width: 260px;" title="{{ $suratAda->nama_file_surat_keterangan }}">
                                                <i class="ri-attachment-2"></i> {{ $suratAda->nama_file_surat_keterangan }}
                                            </div>
                                            <div class="small text-muted mb-1">{{ number_format($suratAda->ukuran_file_surat_keterangan / 1024, 0, ',', '.') }} KB</div>
                                            <a class="btn btn-light btn-sm" href="{{ route('surat-keterangan.download', $suratAda) }}">
                                                <i class="ri-download-line"></i> Unduh
                                            </a>
                                            @if ($bolehIsi)
                                                <button type="button" class="btn btn-danger btn-sm"
                                                    wire:click="hapusSurat({{ $id }})"
                                                    wire:confirm="Hapus surat keterangan ini?">
                                                    <i class="ri-delete-bin-line"></i> Hapus
                                                </button>
                                            @endif
                                        @endif

                                        @if ($bolehIsi)
                                            <input type="file" class="form-control form-control-sm mt-1"
                                                wire:model="surat.{{ $id }}"
                                                accept="application/pdf,image/jpeg,image/png">
                                            <div class="form-text">{{ $suratAda ? 'Pilih file untuk mengganti.' : 'Opsional.' }} PDF/JPG/PNG, maks. 5 MB.</div>
                                            <div wire:loading wire:target="surat.{{ $id }}" class="small text-muted">Mengunggah...</div>
                                            @error('surat.'.$id) <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                                        @elseif (! $suratAda)
                                            <span class="text-muted small">Belum ada surat.</span>
                                        @endif
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
