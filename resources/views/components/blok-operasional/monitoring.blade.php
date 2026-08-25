<?php

use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\KelompokBlok;
use App\Models\PertemuanBlok;
use App\Models\PresensiPertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Monitoring pelaksanaan perkuliahan satu blok.
 *
 * Sudut pandang pengelola: melihat pertemuan mana yang sudah dijurnal dan
 * divalidasi, lalu masuk ke presensi atau jurnal pertemuan itu
 * memakai komponen yang sama dengan halaman dosen.
 *
 * Semua filter, urutan, dan paginasi dikerjakan di SQL karena satu blok bisa berisi
 * ratusan pertemuan (jumlah materi dikali jumlah kelompok).
 */
new class extends Component
{
    use WithPagination;

    /**
     * Tab yang tersedia pada modal pelaksanaan. Dipakai untuk memvalidasi mode yang dikirim
     * klien, baik saat membuka modal maupun saat berpindah tab.
     *
     * @var array<int, string>
     */
    public const MODE_PELAKSANAAN = ['pelaksanaan', 'nilai'];

    public int $blok_id;

    public string $aturan_kegiatan_blok_id = '';

    public string $kelompok_blok_id = '';

    public string $status_pengisian = '';

    public string $search = '';

    public ?int $pelaksanaan_pertemuan_id = null;

    public string $pelaksanaan_mode = 'pelaksanaan';

    public string $pelaksanaan_judul = '';

    public bool $validasi_setelah_simpan = false;

    public bool $jurnal_tersimpan = false;

    public bool $presensi_tersimpan = false;

    public function mount($blok_id): void
    {
        $this->blok_id = (int) $blok_id;

        $blok = Blok::select(['id', 'koordinator_id', 'asisten_koordinator_id'])->findOrFail($this->blok_id);

        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);
    }

    public function updatedAturanKegiatanBlokId(): void
    {
        $this->kelompok_blok_id = '';
        $this->resetPage('monitoringPage');
    }

    public function updatedKelompokBlokId(): void
    {
        $this->resetPage('monitoringPage');
    }

    public function updatedStatusPengisian(): void
    {
        $this->resetPage('monitoringPage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('monitoringPage');
    }

    /**
     * Query dasar tanpa eager load, dipakai ulang untuk daftar maupun ringkasan.
     */
    private function dasarQuery()
    {
        return PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->when($this->aturan_kegiatan_blok_id !== '', fn ($query) => $query
                ->where('aturan_kegiatan_blok_id', (int) $this->aturan_kegiatan_blok_id))
            ->when($this->kelompok_blok_id !== '', fn ($query) => $query
                ->where('kelompok_blok_id', (int) $this->kelompok_blok_id))
            ->when($this->status_pengisian === 'belum', fn ($query) => $query
                ->whereDoesntHave('monitoring_pertemuan_blok'))
            ->when($this->status_pengisian === 'terisi', fn ($query) => $query
                ->whereHas('monitoring_pertemuan_blok', fn ($jurnal) => $jurnal->whereNull('divalidasi_pada')))
            ->when($this->status_pengisian === 'tervalidasi', fn ($query) => $query
                ->whereHas('monitoring_pertemuan_blok', fn ($jurnal) => $jurnal->whereNotNull('divalidasi_pada')))
            ->when($this->search !== '', function ($query) {
                $search = '%'.$this->search.'%';

                $query->where(function ($inner) use ($search) {
                    $inner->where('topik', 'like', $search)
                        ->orWhere('ruangan', 'like', $search)
                        ->orWhereHas('materi_rinci_blok', fn ($materi) => $materi->where('judul', 'like', $search))
                        ->orWhereHas('kelompok_blok', fn ($kelompok) => $kelompok->where('kode', 'like', $search));
                });
            });
    }

    public function pertemuanList()
    {
        return $this->dasarQuery()
            ->with([
                'kelompok_blok' => fn ($query) => $query
                    ->select('id_kelompok_blok', 'kode', 'nama')
                    ->withCount('anggota_kelompok_blok'),
                'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
                'aturan_kegiatan_blok' => fn ($query) => $query
                    ->select('id', 'jenis_kegiatan_id', 'perlu_presensi', 'perlu_penilaian')
                    ->withCount('komponen_penilaian_blok'),
                'aturan_kegiatan_blok.jenis_kegiatan:id,nama',
                'dosen_pertemuan_blok.dosen:id_dosen,nama',
                'monitoring_pertemuan_blok',
            ])
            ->withCount([
                'presensi_pertemuan_blok as presensi_hadir_count' => fn ($query) => $query->where('status', 'hadir'),
                'presensi_pertemuan_blok as presensi_tercatat_count',
                'nilai_pertemuan_blok as nilai_tercatat_count',
            ])
            ->orderByRaw('tanggal IS NULL')
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->paginate(15, pageName: 'monitoringPage');
    }

    /**
     * @return array<string, int|float|null>
     */
    public function ringkasan(): array
    {
        $total = $this->dasarQuery()->count();

        $dijurnal = $this->dasarQuery()->whereHas('monitoring_pertemuan_blok')->count();

        $tervalidasi = $this->dasarQuery()
            ->whereHas('monitoring_pertemuan_blok', fn ($jurnal) => $jurnal->whereNotNull('divalidasi_pada'))
            ->count();

        // Persentase kehadiran dihitung dari baris presensi yang benar-benar tercatat,
        // bukan dari jumlah anggota kelompok, supaya pertemuan yang belum diisi tidak
        // menurunkan angkanya secara palsu.
        $presensi = PresensiPertemuanBlok::query()
            ->whereIn('pertemuan_blok_id', $this->dasarQuery()->select('id_pertemuan_blok'))
            ->selectRaw("count(*) as tercatat, sum(case when status = 'hadir' then 1 else 0 end) as hadir")
            ->first();

        $tercatat = (int) ($presensi->tercatat ?? 0);
        $hadir = (int) ($presensi->hadir ?? 0);

        return [
            'total' => $total,
            'dijurnal' => $dijurnal,
            'belum_dijurnal' => $total - $dijurnal,
            'tervalidasi' => $tervalidasi,
            'presensi_tercatat' => $tercatat,
            'persen_hadir' => $tercatat > 0 ? round($hadir / $tercatat * 100, 1) : null,
        ];
    }

    public function aturanOptions()
    {
        return AturanKegiatanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->with('jenis_kegiatan:id,nama')
            ->orderBy('urutan')
            ->get(['id', 'jenis_kegiatan_id', 'urutan']);
    }

    public function kelompokOptions()
    {
        return KelompokBlok::query()
            ->where('blok_id', $this->blok_id)
            ->when($this->aturan_kegiatan_blok_id !== '', fn ($query) => $query
                ->where('aturan_kegiatan_blok_id', (int) $this->aturan_kegiatan_blok_id))
            ->orderBy('kode')
            ->get(['id_kelompok_blok', 'kode', 'nama']);
    }

    public function kelolaPelaksanaan(string $id, string $mode = 'pelaksanaan'): void
    {
        // Dibatasi ke blok ini supaya id dari klien tidak bisa menunjuk blok lain.
        $pertemuan = PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
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

    #[On('nilai-pertemuan-tersimpan')]
    public function refreshMonitoring(): void
    {
        //
    }

    public function render()
    {
        return $this->view([
            'pertemuanList' => $this->pertemuanList(),
            'ringkasan' => $this->ringkasan(),
            'aturanOptions' => $this->aturanOptions(),
            'kelompokOptions' => $this->kelompokOptions(),
        ]);
    }
};
?>

<div>
    <x-full-page-loading message="Memproses operasional blok..." />
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card mb-0">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Pertemuan</div>
                    <div class="fs-5 fw-semibold">{{ $ringkasan['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card mb-0">
                <div class="card-body py-2">
                    <div class="text-muted small">Belum Diisi</div>
                    <div class="fs-5 fw-semibold text-warning">{{ $ringkasan['belum_dijurnal'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card mb-0">
                <div class="card-body py-2">
                    <div class="text-muted small">Tervalidasi</div>
                    <div class="fs-5 fw-semibold text-success">{{ $ringkasan['tervalidasi'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card mb-0">
                <div class="card-body py-2">
                    <div class="text-muted small">Kehadiran</div>
                    <div class="fs-5 fw-semibold">
                        @if ($ringkasan['persen_hadir'] === null)
                            <span class="text-muted fs-6">belum ada data</span>
                        @else
                            {{ $ringkasan['persen_hadir'] }}%
                        @endif
                    </div>
                    <div class="text-muted small">{{ $ringkasan['presensi_tercatat'] }} presensi tercatat</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-1">Monitoring Pertemuan</h5>
            <div class="text-muted small">
                Pengelola dapat mengisi maupun mengoreksi presensi, nilai, dan catatan monitoring,
                serta membuka kembali validasi yang keliru.
                Nilai tidak terkunci oleh validasi, jadi masih bisa diperbaiki kapan saja.
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Jenis Kegiatan</label>
                    <select class="form-select" wire:model.live="aturan_kegiatan_blok_id">
                        <option value="">Semua kegiatan</option>
                        @foreach ($aturanOptions as $aturan)
                            <option value="{{ $aturan->id }}">{{ $aturan->jenis_kegiatan?->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelompok</label>
                    <select class="form-select" wire:model.live="kelompok_blok_id">
                        <option value="">Semua kelompok</option>
                        @foreach ($kelompokOptions as $kelompok)
                            <option value="{{ $kelompok->id_kelompok_blok }}">{{ $kelompok->kode }} - {{ $kelompok->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status Pengisian</label>
                    <select class="form-select" wire:model.live="status_pengisian">
                        <option value="">Semua status</option>
                        <option value="belum">Belum diisi</option>
                        <option value="terisi">Sudah diisi</option>
                        <option value="tervalidasi">Tervalidasi</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cari</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ri-search-line"></i></span>
                        <input type="text" class="form-control" placeholder="Materi, kelompok, ruangan"
                            wire:model.live.debounce.400ms="search">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jadwal</th>
                            <th>Kegiatan</th>
                            <th>Kelompok</th>
                            <th>Materi</th>
                            <th>Dosen</th>
                            <th>Monitoring</th>
                            <th>Presensi</th>
                            <th>Nilai</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pertemuanList as $item)
                            <tr wire:key="monitoring-{{ $item->id_pertemuan_blok }}">
                                <td>
                                    @if ($item->tanggal)
                                        <div>{{ $item->tanggal->format('d/m/Y') }}</div>
                                    @else
                                        <div class="text-warning small">belum dijadwalkan</div>
                                    @endif
                                    @if ($item->jam_mulai)
                                        <div class="text-muted small">
                                            {{ substr((string) $item->jam_mulai, 0, 5) }}{{ $item->jam_selesai ? '-'.substr((string) $item->jam_selesai, 0, 5) : '' }}
                                        </div>
                                    @endif
                                    @if ($item->ruangan)
                                        <div class="text-muted small">{{ $item->ruangan }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info">
                                        {{ $item->aturan_kegiatan_blok?->jenis_kegiatan?->nama }}
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-semibold">{{ $item->kelompok_blok?->kode }}</div>
                                    <div class="text-muted small">{{ $item->kelompok_blok?->anggota_kelompok_blok_count }} anggota</div>
                                </td>
                                <td>
                                    <div class="small">
                                        @if ($item->materi_rinci_blok?->pertemuan_ke)
                                            <span class="badge bg-light text-dark border">P{{ $item->materi_rinci_blok->pertemuan_ke }}</span>
                                        @endif
                                        {{ $item->materi_rinci_blok?->judul ?: $item->topik }}
                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted small">
                                        {{ $item->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->join(', ') ?: 'belum ada dosen' }}
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
                                        <div class="small text-muted mt-1">{{ $jurnal->divalidasi_pada->format('d/m/Y') }}</div>
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
                                        <div class="small fw-semibold">
                                            {{ $item->presensi_hadir_count }}/{{ $item->kelompok_blok?->anggota_kelompok_blok_count ?? $item->presensi_tercatat_count }}
                                        </div>
                                        <div class="text-muted small">hadir</div>
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
                                        @else
                                            <div class="small fw-semibold">
                                                {{ $item->nilai_tercatat_count }}{{ $selTarget > 0 ? '/'.$selTarget : '' }}
                                            </div>
                                            <div class="text-muted small">isian nilai</div>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        wire:click="kelolaPelaksanaan('{{ $item->id_pertemuan_blok }}', 'pelaksanaan')">
                                        <i class="ri-booklet-line"></i> Isi Monitoring
                                    </button>
                                    @if ($item->aturan_kegiatan_blok?->perlu_penilaian)
                                        <button type="button" class="btn btn-outline-info btn-sm mt-1"
                                            wire:click="kelolaPelaksanaan('{{ $item->id_pertemuan_blok }}', 'nilai')">
                                            <i class="ri-graduation-cap-line"></i> Nilai
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    Tidak ada pertemuan yang cocok dengan filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $pertemuanList->links() }}</div>
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
                        <div class="text-muted">Pilih tombol Monitoring atau Nilai pada salah satu pertemuan.</div>
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
                                    <i class="ri-booklet-line"></i> Monitoring
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button"
                                    class="nav-link {{ $pelaksanaan_mode === 'nilai' ? 'active' : '' }}"
                                    wire:click="setPelaksanaanMode('nilai')">
                                    <i class="ri-graduation-cap-line"></i> Nilai
                                </button>
                            </li>
                        </ul>

                        @if ($pelaksanaan_mode === 'nilai')
                            <livewire:blok-operasional.nilai-pertemuan
                                :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                :key="'nilai-monitoring-'.$pelaksanaan_pertemuan_id" />
                        @else
                            <div class="mb-4">
                                <h6 class="mb-3"><i class="ri-booklet-line"></i> Monitoring</h6>
                                <livewire:blok-operasional.jurnal-pertemuan
                                    :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                    :tampilkan_tombol_simpan="false"
                                    :tampilkan_tombol_validasi="false"
                                    :key="'jurnal-monitoring-'.$pelaksanaan_pertemuan_id" />
                            </div>

                            <div class="border-top pt-4">
                                <h6 class="mb-3"><i class="ri-user-follow-line"></i> Presensi</h6>
                                <livewire:blok-operasional.presensi-pertemuan
                                    :pertemuan_blok_id="$pelaksanaan_pertemuan_id"
                                    :tampilkan_tombol_simpan="false"
                                    :key="'presensi-monitoring-'.$pelaksanaan_pertemuan_id" />
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
                                        wire:confirm="Validasi pertemuan ini? Presensi dan jurnal akan terkunci."
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
