<div>
    <x-full-page-loading
        target="search,prodiId,semesterId,resetFilters,gotoPage,previousPage,nextPage,confirmDeleteBlok,deleteBlok"
        message="Memproses daftar blok..."
    />

    <style>
        .master-blok-card {
            --blok-accent: var(--vz-primary);
            --blok-accent-rgb: var(--vz-primary-rgb);
            border: 2px solid var(--blok-accent) !important;
            overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .master-blok-card:hover {
            box-shadow: 0 .65rem 1.5rem rgba(var(--blok-accent-rgb), .28) !important;
            transform: translateY(-2px);
        }

        .master-blok-card__header {
            background: var(--blok-accent);
            color: #fff;
        }

        .master-blok-card__icon {
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

        .master-blok-card__meta {
            border: 2px solid var(--blok-accent);
        }

        .master-blok-card__stat {
            background: var(--blok-accent);
            color: #fff;
        }

        @media (prefers-reduced-motion: reduce) {
            .master-blok-card { transition: none; }
            .master-blok-card:hover { transform: none; }
        }
    </style>

    <div class="row g-2 mb-4">
        <div class="col-lg-6">
            <label class="visually-hidden" for="master-blok-search">Cari blok</label>
            <div class="input-group">
                <span class="input-group-text"><i class="ri-search-line"></i></span>
                <input
                    id="master-blok-search"
                    type="search"
                    class="form-control"
                    placeholder="Cari nama blok..."
                    wire:model.live.debounce.300ms="search"
                >
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <label class="visually-hidden" for="master-blok-prodi">Prodi</label>
            <select id="master-blok-prodi" class="form-select" wire:model.live="prodiId">
                <option value="">Semua prodi</option>
                @foreach ($prodis as $prodi)
                    <option value="{{ $prodi->id_prodi }}">{{ $prodi->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="visually-hidden" for="master-blok-semester">Semester</label>
            <select id="master-blok-semester" class="form-select" wire:model.live="semesterId">
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
                aria-label="Reset pencarian dan filter"
                @disabled($search === '' && $prodiId === '' && $semesterId === '')
            >
                <i class="ri-refresh-line"></i>
                <span class="d-lg-none ms-1">Reset</span>
            </button>
        </div>
    </div>

    <div class="position-relative" wire:loading.class="opacity-50" wire:target="search,prodiId,semesterId,resetFilters,gotoPage,previousPage,nextPage">
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
                    <div class="col-12 col-xl-6" wire:key="master-blok-{{ $blok->id }}">
                        <article class="card master-blok-card h-100 shadow-none mb-0">
                            <div class="master-blok-card__header p-3">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="master-blok-card__icon" aria-hidden="true">
                                        <i class="ri-layout-grid-line"></i>
                                    </span>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex align-items-start justify-content-between gap-2">
                                            <div class="min-w-0">
                                                <h5 class="card-title text-white mb-1 text-truncate" title="{{ $blok->nama }}">{{ $blok->nama }}</h5>
                                                <div class="small text-white">
                                                    {{ $blok->prodi?->nama ?: '-' }}
                                                    <span class="mx-1">&middot;</span>
                                                    {{ $blok->semester ? ucfirst($blok->semester->nama).' '.$blok->semester->tahun : '-' }}
                                                </div>
                                            </div>
                                            @php
                                                $statusClasses = match ($blok->status) {
                                                    'aktif' => 'bg-success text-white',
                                                    'draft' => 'bg-warning text-dark',
                                                    'selesai' => 'bg-info text-dark',
                                                    default => 'bg-danger text-white',
                                                };
                                            @endphp
                                            <span class="badge {{ $statusClasses }} flex-shrink-0">{{ ucfirst($blok->status) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body d-flex flex-column">
                                <div class="master-blok-card__meta rounded p-3 mb-3">
                                    <div class="row g-2 small">
                                        <div class="col-sm-7 text-muted">
                                            <i class="ri-calendar-line me-1"></i>
                                            {{ $blok->tanggal_mulai?->format('d/m/Y') ?: '-' }}
                                            <span class="mx-1">—</span>
                                            {{ $blok->tanggal_selesai?->format('d/m/Y') ?: '-' }}
                                        </div>
                                        <div class="col-sm-5 text-sm-end text-muted">
                                            <i class="ri-book-open-line me-1"></i>{{ number_format((float) $blok->sks, 1, ',', '.') }} SKS
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2 mb-3 text-center">
                                    <div class="col-4">
                                        <div class="master-blok-card__stat rounded py-2 px-1 h-100">
                                            <div class="fw-semibold">{{ $blok->mata_kuliah_count }}</div>
                                            <div class="small">Mata Kuliah</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="master-blok-card__stat rounded py-2 px-1 h-100">
                                            <div class="fw-semibold">{{ $blok->aturan_kegiatan_blok_count }}</div>
                                            <div class="small">Kegiatan</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="master-blok-card__stat rounded py-2 px-1 h-100">
                                            <div class="fw-semibold">{{ $blok->materi_blok_count }}</div>
                                            <div class="small">Materi</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-auto pt-1 border-top">
                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm mt-3"
                                        wire:click="confirmDeleteBlok('{{ Crypt::encrypt($blok->id) }}')"
                                        aria-label="Hapus {{ $blok->nama }}"
                                    >
                                        <i class="ri-delete-bin-line me-1"></i>Hapus
                                    </button>
                                    <a
                                        href="{{ route('blok.add_edit', ['id' => Crypt::encrypt($blok->id)]) }}"
                                        wire:navigate
                                        class="btn btn-info btn-sm mt-3"
                                        aria-label="Kelola {{ $blok->nama }}"
                                    >
                                        <i class="ri-file-edit-line me-1"></i>Kelola
                                    </a>
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