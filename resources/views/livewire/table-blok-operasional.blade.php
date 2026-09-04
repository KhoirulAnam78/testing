<div>
    <x-full-page-loading message="Memproses daftar operasional blok..." />

    <style>
        .blok-card {
            --blok-accent: var(--vz-primary);
            --blok-accent-rgb: var(--vz-primary-rgb);
            border: 2px solid var(--blok-accent) !important;
            overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .blok-card:hover {
            box-shadow: 0 .65rem 1.5rem rgba(var(--blok-accent-rgb), .28) !important;
            transform: translateY(-2px);
        }

        .blok-card__header {
            background: var(--blok-accent);
            color: #fff;
        }

        .blok-card__icon {
            align-items: center;
            background: rgba(255, 255, 255, .2);
            border: 1px solid rgba(255, 255, 255, .45);
            border-radius: .65rem;
            color: #fff;
            display: inline-flex;
            flex: 0 0 2.5rem;
            font-size: 1.2rem;
            height: 2.5rem;
            justify-content: center;
        }

        .blok-card__stat {
            background: var(--blok-accent);
            color: #fff;
        }

        @media (prefers-reduced-motion: reduce) {
            .blok-card {
                transition: none;
            }

            .blok-card:hover {
                transform: none;
            }
        }
    </style>

    <div class="row g-2 mb-4">
        <div class="col-lg-6">
            <label class="visually-hidden" for="blok-search">Cari blok</label>
            <div class="input-group">
                <span class="input-group-text"><i class="ri-search-line"></i></span>
                <input
                    id="blok-search"
                    type="search"
                    class="form-control"
                    placeholder="Cari kode atau nama blok..."
                    wire:model.live.debounce.300ms="search"
                >
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <label class="visually-hidden" for="blok-prodi">Prodi</label>
            <select id="blok-prodi" class="form-select" wire:model.live="prodiId">
                <option value="">Semua prodi</option>
                @foreach ($prodis as $prodi)
                    <option value="{{ $prodi->id_prodi }}">{{ $prodi->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="visually-hidden" for="blok-semester">Semester</label>
            <select id="blok-semester" class="form-select" wire:model.live="semesterId">
                <option value="">Semua semester</option>
                @foreach ($semesters as $semester)
                    <option value="{{ $semester->id_semester }}">
                        {{ ucfirst($semester->nama) }} {{ $semester->tahun }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-1 d-grid">
            <button
                type="button"
                class="btn btn-soft-secondary"
                wire:click="resetFilters"
                title="Reset pencarian dan filter"
                @disabled($search === '' && $prodiId === '' && $semesterId === '')
            >
                <i class="ri-refresh-line"></i>
                <span class="d-lg-none ms-1">Reset</span>
            </button>
        </div>
    </div>

    <div class="position-relative">
        <div
            class="position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 align-items-center justify-content-center"
            style="z-index: 2;"
            wire:loading.flex
            wire:target="search,prodiId,semesterId,resetFilters,gotoPage,previousPage,nextPage"
        >
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Memuat...</span>
            </div>
        </div>

        @if ($bloks->isEmpty())
            <div class="text-center border rounded py-5 px-3">
                <i class="ri-inbox-2-line display-5 text-muted"></i>
                <h5 class="mt-3 mb-1">Blok tidak ditemukan</h5>
                <p class="text-muted mb-3">Ubah kata pencarian atau filter yang digunakan.</p>
                @if ($search !== '' || $prodiId !== '' || $semesterId !== '')
                    <button type="button" class="btn btn-soft-primary btn-sm" wire:click="resetFilters">
                        Reset pencarian dan filter
                    </button>
                @endif
            </div>
        @else
            <div class="row g-3">
                @foreach ($bloks as $blok)
                    <div class="col-12 col-xl-6" wire:key="blok-operasional-{{ $blok->id }}">
                        <article class="card blok-card h-100 shadow-none mb-0">
                            <div class="blok-card__header p-3">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="blok-card__icon" aria-hidden="true">
                                        <i class="ri-settings-3-line"></i>
                                    </span>
                                    <div class="min-w-0 flex-grow-1">
                                        <h5 class="card-title text-white mb-1">{{ $blok->nama }}</h5>
                                        <div class="small text-white">
                                            {{ $blok->prodi?->nama ?: '-' }}
                                            <span class="mx-1">&middot;</span>
                                            {{ $blok->semester ? ucfirst($blok->semester->nama).' '.$blok->semester->tahun : '-' }}
                                        </div>
                                    </div>
                                    <a
                                        href="{{ route('blok-operasional.detail', ['id' => Crypt::encrypt($blok->id)]) }}"
                                        wire:navigate
                                        class="btn btn-light text-primary btn-sm flex-shrink-0"
                                        aria-label="Kelola {{ $blok->nama }}"
                                    >
                                        <i class="ri-settings-3-line me-1"></i>Kelola
                                    </a>
                                </div>
                            </div>

                            <div class="card-body d-flex flex-column">
                                <div class="mb-3">
                                    <div class="text-muted small mb-1">Mata kuliah</div>
                                    <div class="d-flex flex-wrap gap-1">
                                        @forelse ($blok->mata_kuliah as $mataKuliah)
                                            <span class="badge bg-light text-body border">{{ $mataKuliah->kode }}</span>
                                        @empty
                                            <span class="text-muted">-</span>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="text-muted small mb-3">
                                    <i class="ri-calendar-line me-1"></i>
                                    {{ $blok->tanggal_mulai?->format('d/m/Y') ?: '-' }}
                                    <span class="mx-1">—</span>
                                    {{ $blok->tanggal_selesai?->format('d/m/Y') ?: '-' }}
                                </div>

                                <div class="text-muted small mb-3">
                                    <i class="ri-team-line me-1"></i>
                                    Pengelola: {{ $blok->pengelola_blok->pluck('dosen.nama')->filter()->join(', ') ?: '-' }}
                                </div>

                                <div class="row g-2 mt-auto text-center">
                                    <div class="col-4">
                                        <div class="blok-card__stat rounded py-2 px-1">
                                            <div class="fw-semibold">
                                                <i class="ri-user-3-line me-1"></i>{{ $blok->peserta_blok_count }}
                                            </div>
                                            <div class="small">Peserta</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="blok-card__stat rounded py-2 px-1">
                                            <div class="fw-semibold">
                                                <i class="ri-group-line me-1"></i>{{ $blok->kelompok_blok_count }}
                                            </div>
                                            <div class="small">Kelompok</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="blok-card__stat rounded py-2 px-1">
                                            <div class="fw-semibold">
                                                <i class="ri-calendar-check-line me-1"></i>{{ $blok->pertemuan_blok_count }}
                                            </div>
                                            <div class="small">Pertemuan</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mt-4">
                <div class="text-muted small">
                    Menampilkan {{ $bloks->firstItem() }}–{{ $bloks->lastItem() }} dari {{ $bloks->total() }} blok
                </div>
                {{ $bloks->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</div>