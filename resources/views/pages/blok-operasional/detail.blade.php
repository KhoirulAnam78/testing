<?php

use App\Models\Blok;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public int $blok_id;
    public string $tab = 'ringkasan';

    public function mount($id): void
    {
        try {
            $this->blok_id = (int) Crypt::decrypt($id);
        } catch (DecryptException $e) {
            abort(404, 'Enkripsi tidak valid !');
        }

        $blok = Blok::select('id')->findOrFail($this->blok_id);

        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['ringkasan', 'peserta', 'kelompok', 'pertemuan', 'monitoring', 'logbook'], true)) {
            $this->tab = $tab;
        }
    }

    /**
     * Ringkasan blok dalam satu query: seluruh hitungan memakai subselect,
     * bukan memuat koleksi peserta/kelompok/pertemuan ke memori.
     */
    public function blok(): Blok
    {
        return Blok::query()
            ->with([
                'prodi:id_prodi,kode,nama',
                'semester:id_semester,nama,tahun,kode',
                'koordinator:id_dosen,nama',
                'asisten_koordinator:id_dosen,nama',
                'pengelola_blok.dosen:id_dosen,nama',
            ])
            ->withCount([
                'peserta_blok as peserta_count',
                'peserta_blok as peserta_aktif_count' => fn ($query) => $query->where('status', 'aktif'),
                'kelas as rombel_count',
                'aturan_kegiatan_blok as kegiatan_count',
                'kelompok_blok as kelompok_count',
                'pertemuan_blok as pertemuan_count',
                'pertemuan_blok as pertemuan_terjadwal_count' => fn ($query) => $query->whereNotNull('tanggal'),
                'pertemuan_blok as pertemuan_tanpa_dosen_count' => fn ($query) => $query->whereDoesntHave('dosen_pertemuan_blok'),
                'pertemuan_blok as pertemuan_belum_dijurnal_count' => fn ($query) => $query->whereDoesntHave('monitoring_pertemuan_blok'),
                'pertemuan_blok as pertemuan_tervalidasi_count' => fn ($query) => $query
                    ->whereHas('monitoring_pertemuan_blok', fn ($jurnal) => $jurnal->whereNotNull('divalidasi_pada')),
            ])
            ->findOrFail($this->blok_id);
    }
}; ?>

<div>
    @php($blok = $this->blok())

    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">Operasional Blok</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('blok-operasional.index') }}" wire:navigate>Operasional Blok</a></li>
                    <li class="breadcrumb-item active">{{ $blok->kode }}</li>
                </ol>
            </div>
        </div>
    </div>

    <livewire:alert/>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <div class="text-muted small">Blok</div>
                    <div class="fw-semibold">{{ $blok->kode }} - {{ $blok->nama }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Koordinator</div>
                    <div class="fw-semibold">{{ $blok->koordinator?->nama ?: '-' }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Asisten Koordinator</div>
                    <div class="fw-semibold">{{ $blok->asisten_koordinator?->nama ?: '-' }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Kontributor</div>
                    <div class="fw-semibold">
                        {{ $blok->pengelola_blok->where('jabatan', 'kontributor')->pluck('dosen.nama')->filter()->join(', ') ?: '-' }}
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="text-muted small">Prodi</div>
                    <div class="fw-semibold">{{ $blok->prodi?->nama ?: '-' }}</div>
                </div>
                <div class="col-md-1">
                    <div class="text-muted small">Semester</div>
                    <div class="fw-semibold">{{ $blok->semester ? ucfirst($blok->semester->nama).' '.$blok->semester->tahun : '-' }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Periode</div>
                    <div class="fw-semibold">
                        {{ $blok->tanggal_mulai?->format('d/m/Y') ?: '-' }} &ndash; {{ $blok->tanggal_selesai?->format('d/m/Y') ?: '-' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'ringkasan' ? 'active' : '' }}" wire:click="setTab('ringkasan')">
                <i class="ri-dashboard-line"></i> Ringkasan
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'peserta' ? 'active' : '' }}" wire:click="setTab('peserta')">
                <i class="ri-user-add-line"></i> Peserta
                <span class="badge bg-light text-dark border ms-1">{{ $blok->peserta_count }}</span>
            </button>
        </li>
        {{-- <li class="nav-item">
            <button type="button" class="nav-link {{ $tab == ? 'active' : '' }}" wire:click="setTab('rombel')">
                <i class="ri-community-line"></i> Rombel
                <span class="badge bg-light text-dark border ms-1">{{ $blok->rombel_count }}</span>
            </button>
        </li> --}}
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'kelompok' ? 'active' : '' }}" wire:click="setTab('kelompok')">
                <i class="ri-group-line"></i> Kelompok
                <span class="badge bg-light text-dark border ms-1">{{ $blok->kelompok_count }}</span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'pertemuan' ? 'active' : '' }}" wire:click="setTab('pertemuan')">
                <i class="ri-calendar-check-line"></i> Pertemuan
                <span class="badge bg-light text-dark border ms-1">{{ $blok->pertemuan_count }}</span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'logbook' ? 'active' : '' }}" wire:click="setTab('logbook')">
                <i class="ri-file-list-3-line"></i> Logbook
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'monitoring' ? 'active' : '' }}" wire:click="setTab('monitoring')">
                <i class="ri-pulse-line"></i> Monitoring Pelaksanaan
                @if ($blok->pertemuan_belum_dijurnal_count > 0)
                    <span class="badge bg-warning-subtle text-warning ms-1"
                        title="pertemuan yang belum dimonitoring">{{ $blok->pertemuan_belum_dijurnal_count }}</span>
                @else
                    <span class="badge bg-success-subtle text-success ms-1">lengkap</span>
                @endif
            </button>
        </li>
    </ul>

    <x-full-page-loading message="Memproses operasional blok..." />

    @if ($tab === 'ringkasan')
        <div class="row">
            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Peserta Aktif</div>
                    <h4 class="mb-0">{{ $blok->peserta_aktif_count }}</h4>
                    <div class="text-muted small">dari {{ $blok->peserta_count }} terdaftar</div>
                </div></div>
            </div>
            {{-- <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Rombel</div>
                    <h4 class="mb-0">{{ $blok->rombel_count }}</h4>
                    <div class="text-muted small">opsional</div>
                </div></div>
            </div> --}}
            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Jenis Kegiatan</div>
                    <h4 class="mb-0">{{ $blok->kegiatan_count }}</h4>
                    <div class="text-muted small">dari susunan blok</div>
                </div></div>
            </div>
            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Kelompok</div>
                    <h4 class="mb-0">{{ $blok->kelompok_count }}</h4>
                    <div class="text-muted small">semua kegiatan</div>
                </div></div>
            </div>
            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Pertemuan Terjadwal</div>
                    <h4 class="mb-0">{{ $blok->pertemuan_terjadwal_count }}</h4>
                    <div class="text-muted small">dari {{ $blok->pertemuan_count }} pertemuan</div>
                </div></div>
            </div>
            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100 {{ $blok->pertemuan_tanpa_dosen_count > 0 ? 'border border-warning' : '' }}"><div class="card-body">
                    <div class="text-muted small">Tanpa Dosen</div>
                    <h4 class="mb-0">{{ $blok->pertemuan_tanpa_dosen_count }}</h4>
                    <div class="text-muted small">perlu dilengkapi</div>
                </div></div>
            </div>
            <div class="col-md-4 col-xl-2 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Tervalidasi</div>
                    <h4 class="mb-0">{{ $blok->pertemuan_tervalidasi_count }}</h4>
                    <div class="text-muted small">{{ $blok->pertemuan_belum_dijurnal_count }} belum dimonitoring</div>
                </div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Urutan Pengerjaan</h5></div>
            <div class="card-body">
                <ol class="mb-0 ps-3">
                    <li class="mb-2">
                        <span class="fw-semibold">Peserta</span> &mdash; masukkan mahasiswa yang mengambil blok ini.
                        @if ($blok->peserta_count === 0)
                            <span class="badge bg-warning-subtle text-warning">belum ada peserta</span>
                        @else
                            <span class="badge bg-success-subtle text-success">{{ $blok->peserta_count }} peserta</span>
                        @endif
                    </li>
                    <li class="mb-2">
                        <span class="fw-semibold">Rombel</span> &mdash; opsional, hanya jika blok dipecah menjadi beberapa rombongan paralel.
                    </li>
                    <li class="mb-2">
                        <span class="fw-semibold">Kelompok</span> &mdash; bagi peserta per jenis kegiatan, misalnya Kuliah Pakar 2 kelompok dan Praktikum 4 kelompok.
                        @if ($blok->kelompok_count === 0)
                            <span class="badge bg-warning-subtle text-warning">belum ada kelompok</span>
                        @endif
                    </li>
                    <li>
                        <span class="fw-semibold">Pertemuan</span> &mdash; isi dosen pengampu dan jadwal tiap kelompok per rincian materi.
                        @if ($blok->pertemuan_tanpa_dosen_count > 0)
                            <span class="badge bg-warning-subtle text-warning">{{ $blok->pertemuan_tanpa_dosen_count }} pertemuan belum ada dosen</span>
                        @endif
                    </li>
                </ol>
            </div>
        </div>
    @endif

    @if ($tab === 'peserta')
        <livewire:blok-operasional.peserta :blok_id="$blok_id" :key="'blok-peserta-'.$blok_id" />
    @endif

    {{-- @if ($tab ==)
        <livewire:blok-operasional.rombel :blok_id="$blok_id" :key="'blok-rombel-'.$blok_id" />
    @endif --}}

    @if ($tab === 'kelompok')
        <livewire:blok-operasional.kelompok :blok_id="$blok_id" :key="'blok-kelompok-'.$blok_id" />
    @endif

    @if ($tab === 'pertemuan')
        <livewire:blok-operasional.pertemuan :blok_id="$blok_id" :key="'blok-pertemuan-'.$blok_id" />
    @endif

    @if ($tab === 'logbook')
        <livewire:blok-operasional.logbook :blok_id="$blok_id" :key="'blok-logbook-'.$blok_id" />
    @endif

    @if ($tab === 'monitoring')
        <livewire:blok-operasional.monitoring :blok_id="$blok_id" :key="'blok-monitoring-'.$blok_id" />
    @endif
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

            bind('mappingPertemuanModal', 'show-mapping-pertemuan-modal', 'hide-mapping-pertemuan-modal');
            bind('modulMateriModal', 'show-modul-materi-modal', 'hide-modul-materi-modal');
            bind('pelaksanaanModal', 'show-pelaksanaan-modal', 'hide-pelaksanaan-modal');
            bind('logbookModal', 'show-logbook-modal', 'hide-logbook-modal');
        })();
    </script>
@endpush
