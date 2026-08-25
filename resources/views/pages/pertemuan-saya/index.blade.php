<?php

use App\Models\Blok;
use App\Models\PertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portal dosen: daftar pertemuan yang diampu user yang login, dengan pengelolaan
 * tautan modul dan video per pertemuan.
 *
 * Identitas dosen selalu dibaca ulang dari `auth()` setiap request, tidak disimpan
 * sebagai properti komponen, supaya cakupan data tidak pernah bergantung pada
 * payload yang dikirim klien.
 */
new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    /**
     * Tab yang tersedia pada modal pelaksanaan. Dipakai untuk memvalidasi mode yang dikirim
     * klien, baik saat membuka modal maupun saat berpindah tab.
     *
     * @var array<int, string>
     */
    public const MODE_PELAKSANAAN = ['pelaksanaan', 'nilai', 'logbook'];

    public string $blok_id = '';

    public string $search = '';

    public ?int $pertemuan_terpilih = null;

    public ?int $materi_rinci_terpilih = null;

    public string $modul_judul = '';

    public ?int $pelaksanaan_pertemuan_id = null;

    public string $pelaksanaan_mode = 'pelaksanaan';

    public string $pelaksanaan_judul = '';

    public bool $validasi_setelah_simpan = false;

    public bool $jurnal_tersimpan = false;

    public bool $presensi_tersimpan = false;

    public function mount(): void
    {
        $this->pastikanAkses();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBlokId(): void
    {
        $this->resetPage();
    }

    /**
     * `dosen.user_id` nullable dan `Dosen` memakai soft delete, jadi user berrole
     * dosen belum tentu punya baris dosen yang bisa dipakai.
     */
    private function pastikanAkses(): int
    {
        abort_unless(auth()->user()?->can('pertemuan-saya:'), 403);

        $dosen = auth()->user()?->dosen;

        abort_unless($dosen, 403, 'Akun ini belum terhubung ke data dosen.');

        return (int) $dosen->id_dosen;
    }

    /**
     * Filter dan urutan dikerjakan di SQL karena satu dosen bisa mengampu banyak
     * pertemuan di beberapa blok sekaligus. Query berangkat dari `PertemuanBlok`
     * sehingga pertemuan yang sudah di-soft-delete otomatis tidak ikut, walaupun
     * baris `dosen_pertemuan_blok`-nya masih ada (tabel itu tanpa soft delete).
     */
    public function pertemuanQuery()
    {
        $dosenId = $this->pastikanAkses();

        return PertemuanBlok::query()
            ->whereHas('dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->when($this->blok_id !== '', fn ($query) => $query->where('blok_id', (int) $this->blok_id))
            ->when($this->search !== '', function ($query) {
                $search = '%'.$this->search.'%';

                $query->where(function ($inner) use ($search) {
                    $inner->where('topik', 'like', $search)
                        ->orWhere('ruangan', 'like', $search)
                        ->orWhereHas('materi_rinci_blok', fn ($materi) => $materi->where('judul', 'like', $search));
                });
            })
            ->with([
                'blok:id,kode,nama',
                'kelompok_blok' => fn ($query) => $query
                    ->select('id_kelompok_blok', 'kode', 'nama')
                    ->withCount('anggota_kelompok_blok'),
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
                'aturan_kegiatan_blok' => fn ($query) => $query
                    ->select('id', 'jenis_kegiatan_id', 'perlu_presensi', 'perlu_penilaian')
                    ->withCount('komponen_penilaian_blok'),
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama,perlu_logbook',
                'monitoring_pertemuan_blok',
            ])
            ->withCount([
                'lampiran_materi_blok',
                'presensi_pertemuan_blok as presensi_hadir_count' => fn ($query) => $query->where('status', 'hadir'),
                'presensi_pertemuan_blok as presensi_tercatat_count',
                'nilai_pertemuan_blok as nilai_tercatat_count',
            ])
            ->orderByRaw('tanggal IS NULL')
            ->orderBy('tanggal')
            ->orderBy('jam_mulai');
    }

    public function blokOptions()
    {
        $dosenId = $this->pastikanAkses();

        return Blok::query()
            ->whereHas('pertemuan_blok.dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);
    }

    public function kelolaModul(string $id): void
    {
        $dosenId = $this->pastikanAkses();

        $pertemuan = PertemuanBlok::query()
            ->whereHas('dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->with([
                'kelompok_blok:id_kelompok_blok,kode',
                'materi_rinci_blok:id_materi_rinci_blok,judul',
            ])
            ->findOrFail((int) $id);

        $this->pertemuan_terpilih = $pertemuan->id_pertemuan_blok;
        $this->materi_rinci_terpilih = (int) $pertemuan->materi_rinci_blok_id;
        $this->modul_judul = trim(
            ($pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik ?: 'Materi')
            .' - '.($pertemuan->kelompok_blok?->kode ?: '')
        );

        $this->dispatch('show-modul-pertemuan-modal');
    }

    public function tutupModul(): void
    {
        $this->reset(['pertemuan_terpilih', 'materi_rinci_terpilih', 'modul_judul']);
    }

    /**
     * Badge jumlah tautan dihitung ulang setiap komponen lampiran menyimpan sesuatu.
     */
    #[On('lampiran-materi-tersimpan')]
    public function refreshLampiran(): void
    {
        //
    }

    /**
     * Presensi dan monitoring disatukan sebagai pelaksanaan, sedangkan nilai dan logbook
     * tetap menjadi proses terpisah dalam modal yang sama.
     */
    public function kelolaPelaksanaan(string $id, string $mode = 'pelaksanaan'): void
    {
        $dosenId = $this->pastikanAkses();

        $pertemuan = PertemuanBlok::query()
            ->whereHas('dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->with([
                'kelompok_blok:id_kelompok_blok,kode',
                'materi_rinci_blok:id_materi_rinci_blok,judul',
            ])
            ->findOrFail((int) $id);

        $this->pelaksanaan_pertemuan_id = $pertemuan->id_pertemuan_blok;
        $this->pelaksanaan_mode = in_array($mode, self::MODE_PELAKSANAAN, true) ? $mode : 'pelaksanaan';
        $this->pelaksanaan_judul = trim(
            ($pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik ?: 'Pertemuan')
            .' - '.($pertemuan->kelompok_blok?->kode ?: '')
        );

        $this->dispatch('show-pelaksanaan-modal');
    }

    public function setPelaksanaanMode(string $mode): void
    {
        if (in_array($mode, self::MODE_PELAKSANAAN, true)) {
            $this->pelaksanaan_mode = $mode;
        }
    }

    public function tutupPelaksanaan(): void
    {
        $this->resetStateSimpanPelaksanaan();
        $this->reset(['pelaksanaan_pertemuan_id', 'pelaksanaan_mode', 'pelaksanaan_judul']);
    }

    public function simpanPelaksanaan(): void
    {
        abort_unless(
            $this->pelaksanaan_pertemuan_id
                && AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $this->pelaksanaan_pertemuan_id),
            403
        );

        $this->resetStateSimpanPelaksanaan();
        $this->dispatch(
            'simpan-pelaksanaan',
            pertemuan_blok_id: $this->pelaksanaan_pertemuan_id
        );
    }

    public function validasiPelaksanaan(): void
    {
        abort_unless(
            $this->pelaksanaan_pertemuan_id
                && AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $this->pelaksanaan_pertemuan_id),
            403
        );

        $this->resetStateSimpanPelaksanaan();
        $this->validasi_setelah_simpan = true;
        $this->dispatch(
            'simpan-pelaksanaan',
            pertemuan_blok_id: $this->pelaksanaan_pertemuan_id
        );
    }

    #[On('presensi-pertemuan-tersimpan')]
    public function tandaiPresensiTersimpan(): void
    {
        $this->presensi_tersimpan = true;
        $this->lanjutkanValidasi();
    }

    #[On('jurnal-pertemuan-tersimpan')]
    public function tandaiJurnalTersimpan(): void
    {
        $this->jurnal_tersimpan = true;
        $this->lanjutkanValidasi();
    }

    private function lanjutkanValidasi(): void
    {
        if (
            $this->validasi_setelah_simpan
            && $this->jurnal_tersimpan
            && $this->presensi_tersimpan
            && $this->pelaksanaan_pertemuan_id
        ) {
            $this->validasi_setelah_simpan = false;
            $this->dispatch(
                'validasi-pelaksanaan',
                pertemuan_blok_id: $this->pelaksanaan_pertemuan_id
            );
        }
    }

    private function resetStateSimpanPelaksanaan(): void
    {
        $this->reset(['validasi_setelah_simpan', 'jurnal_tersimpan', 'presensi_tersimpan']);
    }

    /**
     * Badge hadir/total dan status validasi dihitung di query, jadi halaman perlu
     * ikut segar setiap presensi, jurnal, atau nilai disimpan.
     */
    #[On('nilai-pertemuan-tersimpan')]
    public function refreshPelaksanaan(): void
    {
        //
    }

    public function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }
}; ?>

<div>
    <x-full-page-loading target="kelolaModul,kelolaPelaksanaan" message="Memuat data pertemuan..." />

    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Pertemuan Saya</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">Portal Saya</a></li>
                    <li class="breadcrumb-item active">Pertemuan Saya</li>
                </ol>
            </div>
        </div>
    </div>

    @php($blokOptions = $this->blokOptions())
    @php($pertemuanList = $this->pertemuanQuery()->paginate(15))

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-1">Jadwal &amp; Materi yang Saya Ampu</h5>
                    <div class="text-muted small">
                        Lengkapi tautan modul dan video untuk setiap pertemuan. Tautan bertanda
                        <span class="fw-semibold">dari materi</span> disiapkan pengelola dan berlaku untuk semua kelompok.
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Blok</label>
                            <select class="form-select" wire:model.live="blok_id">
                                <option value="">Semua blok</option>
                                @foreach ($blokOptions as $blok)
                                    <option value="{{ $blok->id }}">{{ $blok->kode }} - {{ $blok->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Cari</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input type="text" class="form-control" placeholder="Materi, topik, atau ruangan"
                                    wire:model.live.debounce.400ms="search">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal Pelaksanaan</th>
                                    <th>Blok</th>
                                    <th>Kegiatan</th>
                                    <th>Materi</th>
                                    <th>Monitoring</th>
                                    <th>Presensi</th>
                                    <th>Nilai</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pertemuanList as $item)
                                    <tr wire:key="pertemuan-saya-{{ $item->id_pertemuan_blok }}">
                                        <td>
                                            @if ($item->monitoring_pertemuan_blok?->tanggal_realisasi)
                                                <div>{{ $item->monitoring_pertemuan_blok->tanggal_realisasi->format('d/m/Y') }}</div>
                                            @else
                                                <div class="text-warning small">belum diisi</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="small fw-semibold">{{ $item->blok?->kode }}</div>
                                            <div class="text-muted small">{{ $item->blok?->nama }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info">
                                                {{ $item->aturan_kegiatan_blok?->jenis_kegiatan?->nama }}
                                            </span>
                                            <br>
                                            <div class="small fw-semibold">{{ $item->kelompok_blok?->kode }}</div>
                                            <div class="text-muted small">{{ $item->kelompok_blok?->nama }}</div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column align-items-start gap-1">
                                                @if ($item->materi_rinci_blok?->pertemuan_ke)
                                                    <span class="badge bg-light text-dark border">Pertemuan {{ $item->materi_rinci_blok->pertemuan_ke }}</span>
                                                @endif
                                                <div class="small fw-semibold text-wrap">
                                                    {{ $item->materi_rinci_blok?->judul ?: $item->topik }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @php($jurnal = $item->monitoring_pertemuan_blok)
                                            @if (! $jurnal)
                                                <span class="badge bg-light text-dark border">belum diisi</span>
                                            @elseif ($jurnal->divalidasi_pada)
                                                <span class="badge bg-success-subtle text-success">
                                                    <i class="ri-shield-check-line"></i> Tervalidasi
                                                </span>
                                            @else
                                                <span class="badge bg-info-subtle text-info">sudah diisi</span>
                                                <div class="small text-muted mt-1">belum divalidasi</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if (! $item->aturan_kegiatan_blok?->perlu_presensi)
                                                <span class="text-muted small">tidak perlu</span>
                                            @elseif (! $item->presensi_tercatat_count)
                                                <span class="badge bg-warning-subtle text-warning">belum diisi</span>
                                            @else
                                                <div class="small fw-semibold">{{ $item->presensi_hadir_count }} hadir</div>
                                                <div class="text-muted small">
                                                    dari {{ $item->kelompok_blok?->anggota_kelompok_blok_count ?? $item->presensi_tercatat_count }} anggota
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if (! $item->aturan_kegiatan_blok?->perlu_penilaian)
                                                <span class="text-muted small">tidak dinilai</span>
                                            @else
                                                @php($komponenCount = (int) ($item->aturan_kegiatan_blok?->komponen_penilaian_blok_count ?? 0))
                                                @php($selTarget = $komponenCount * (int) ($item->kelompok_blok?->anggota_kelompok_blok_count ?? 0))
                                                @if ($komponenCount === 0)
                                                    <span class="badge bg-danger-subtle text-danger">rubrik kosong</span>
                                                @elseif (! $item->nilai_tercatat_count)
                                                    <span class="badge bg-warning-subtle text-warning">belum diisi</span>
                                                @elseif ($selTarget > 0 && $item->nilai_tercatat_count < $selTarget)
                                                    <span class="badge bg-warning-subtle text-warning">
                                                        {{ $item->nilai_tercatat_count }}/{{ $selTarget }} isian
                                                    </span>
                                                @else
                                                    <span class="badge bg-success-subtle text-success">lengkap</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                wire:click="kelolaModul('{{ $item->id_pertemuan_blok }}')">
                                                <i class="ri-links-line"></i> Modul
                                                @if ($item->lampiran_materi_blok_count > 0)
                                                    <span class="badge bg-primary ms-1"
                                                        title="tautan khusus kelompok ini">{{ $item->lampiran_materi_blok_count }}</span>
                                                @endif
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1"
                                                wire:click="kelolaPelaksanaan('{{ $item->id_pertemuan_blok }}', 'pelaksanaan')">
                                                <i class="ri-booklet-line"></i> Isi Monitoring
                                            </button>
                                            @if ($item->aturan_kegiatan_blok?->perlu_penilaian)
                                                <button type="button" class="btn btn-outline-info btn-sm mt-1"
                                                    wire:click="kelolaPelaksanaan('{{ $item->id_pertemuan_blok }}', 'nilai')">
                                                    <i class="ri-graduation-cap-line"></i> Nilai
                                                </button>
                                            @endif
                                            @if ($item->aturan_kegiatan_blok?->jenis_kegiatan?->perlu_logbook)
                                                <button type="button" class="btn btn-outline-warning btn-sm mt-1"
                                                    wire:click="kelolaPelaksanaan('{{ $item->id_pertemuan_blok }}', 'logbook')">
                                                    <i class="ri-file-list-3-line"></i> Logbook
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            Belum ada pertemuan yang diplotkan untuk Anda.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">{{ $pertemuanList->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modulPertemuanModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tautan Modul &amp; Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" wire:click="tutupModul"></button>
                </div>
                <div class="modal-body">
                    @if (! $pertemuan_terpilih || ! $materi_rinci_terpilih)
                        <div class="text-muted">Pilih tombol Kelola pada salah satu pertemuan.</div>
                    @else
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="text-muted small">Pertemuan</div>
                            <div class="fw-semibold">{{ $modul_judul }}</div>
                        </div>

                        <livewire:blok-operasional.lampiran-materi
                            :materi_rinci_blok_id="$materi_rinci_terpilih"
                            :pertemuan_blok_id="$pertemuan_terpilih"
                            :key="'lampiran-saya-'.$pertemuan_terpilih" />
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" wire:click="tutupModul">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pelaksanaanModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Monitoring Pertemuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" wire:click="tutupPelaksanaan"></button>
                </div>
                <div class="modal-body">
                    @if (! $pelaksanaan_pertemuan_id)
                        <div class="text-muted">Pilih aksi monitoring pada salah satu pertemuan.</div>
                    @else
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="text-muted small">Pertemuan</div>
                            <div class="fw-semibold">{{ $pelaksanaan_judul }}</div>
                        </div>

                        <ul class="nav nav-pills mb-3">
                            <li class="nav-item">
                                <button type="button"
                                    class="nav-link {{ $pelaksanaan_mode === 'pelaksanaan' ? 'active' : '' }}"
                                    wire:click="setPelaksanaanMode('pelaksanaan')">
                                    <i class="ri-calendar-check-line"></i> Monitoring
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button"
                                    class="nav-link {{ $pelaksanaan_mode === 'nilai' ? 'active' : '' }}"
                                    wire:click="setPelaksanaanMode('nilai')">
                                    <i class="ri-graduation-cap-line"></i> Nilai
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button"
                                    class="nav-link {{ $pelaksanaan_mode === 'logbook' ? 'active' : '' }}"
                                    wire:click="setPelaksanaanMode('logbook')">
                                    <i class="ri-file-list-3-line"></i> Logbook
                                </button>
                            </li>
                        </ul>

                        @if ($pelaksanaan_mode === 'nilai')
                            <livewire:blok-operasional.nilai-pertemuan
                                :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                :key="'nilai-saya-'.$pelaksanaan_pertemuan_id" />
                        @elseif ($pelaksanaan_mode === 'logbook')
                            <livewire:logbook-pertemuan
                                :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                :key="'logbook-saya-'.$pelaksanaan_pertemuan_id" />
                        @else
                            <div class="mb-4">
                                <h6 class="mb-3"><i class="ri-booklet-line"></i> Monitoring</h6>
                                <livewire:blok-operasional.jurnal-pertemuan
                                    :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                    :tampilkan_tombol_simpan="false"
                                    :tampilkan_tombol_validasi="false"
                                    :key="'jurnal-saya-'.$pelaksanaan_pertemuan_id" />
                            </div>

                            <div class="border-top pt-4">
                                <h6 class="mb-3"><i class="ri-user-follow-line"></i> Presensi</h6>
                                <livewire:blok-operasional.presensi-pertemuan
                                    :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                    :tampilkan_tombol_simpan="false"
                                    :key="'presensi-saya-'.$pelaksanaan_pertemuan_id" />
                            </div>

                            @if (AksesPertemuanBlok::bolehIsiPelaksanaan(auth()->user(), $pelaksanaan_pertemuan_id))
                                <div class="border-top pt-3 mt-4 d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-primary btn-sm"
                                        wire:click="simpanPelaksanaan"
                                        wire:loading.attr="disabled"
                                        wire:target="simpanPelaksanaan,validasiPelaksanaan">
                                        <i class="ri-save-line"></i> SIMPAN
                                    </button>
                                    <button type="button" class="btn btn-success btn-sm"
                                        wire:click="validasiPelaksanaan"
                                        wire:confirm="Validasi pertemuan ini? Presensi dan monitoring akan terkunci."
                                        wire:loading.attr="disabled"
                                        wire:target="simpanPelaksanaan,validasiPelaksanaan">
                                        <i class="ri-shield-check-line"></i> SIMPAN & VALIDASI
                                    </button>
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" wire:click="tutupPelaksanaan">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const getModal = (modalId) => {
                const element = document.getElementById(modalId);

                if (! element || ! window.bootstrap?.Modal) {
                    return null;
                }

                return window.bootstrap.Modal.getOrCreateInstance(element);
            };

            const bind = (modalId, showEvent, hideEvent) => {
                document.addEventListener(showEvent, () => {
                    setTimeout(() => getModal(modalId)?.show(), 50);
                });

                document.addEventListener(hideEvent, () => {
                    getModal(modalId)?.hide();
                });
            };

            bind('modulPertemuanModal', 'show-modul-pertemuan-modal', 'hide-modul-pertemuan-modal');
            bind('pelaksanaanModal', 'show-pelaksanaan-modal', 'hide-pelaksanaan-modal');
        })();
    </script>
@endpush
