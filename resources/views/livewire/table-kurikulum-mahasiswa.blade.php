<div>
    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <label class="form-label" for="filter-cari">NIM / Nama</label>
            <input id="filter-cari" type="search" class="form-control" wire:model.live.debounce.400ms="filter_pencarian" placeholder="Cari mahasiswa">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-prodi">Program Studi</label>
            <select id="filter-prodi" class="form-select" wire:model.live="filter_prodi_id">
                <option value="">Semua prodi</option>
                @foreach ($prodi as $item)
                    <option value="{{ $item->id_prodi }}">{{ $item->kode }} - {{ $item->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="filter-angkatan">Angkatan</label>
            <select id="filter-angkatan" class="form-select" wire:model.live="filter_angkatan">
                <option value="">Semua angkatan</option>
                @foreach ($angkatan as $tahun)
                    <option value="{{ $tahun }}">{{ $tahun }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="filter-kurikulum">Kurikulum</label>
            <select id="filter-kurikulum" class="form-select" wire:model.live="filter_kurikulum_id">
                <option value="">Semua kurikulum</option>
                <option value="kosong">Belum diatur</option>
                @foreach ($kurikulumFilter as $item)
                    <option value="{{ $item->id_kurikulum }}">{{ $item->prodi?->kode }} - {{ $item->kode }} - {{ $item->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>NIM</th>
                    <th>Nama</th>
                    <th>Prodi</th>
                    <th>Angkatan</th>
                    <th>Kurikulum</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mahasiswa as $item)
                    <tr wire:key="mahasiswa-kurikulum-{{ $item->id_mahasiswa }}">
                        <td class="fw-semibold">{{ strtoupper($item->nim) }}</td>
                        <td>{{ $item->nama }}</td>
                        <td>{{ $item->prodi?->kode ?? '-' }}</td>
                        <td>{{ $item->angkatan }}</td>
                        <td>
                            @if ($item->kurikulum)
                                <div>{{ $item->kurikulum->kode }} - {{ $item->kurikulum->nama }}</div>
                                @if ($item->kurikulum->trashed())
                                    <span class="badge bg-danger-subtle text-danger">Dihapus</span>
                                @elseif ($item->kurikulum->status !== 'aktif')
                                    <span class="badge bg-warning-subtle text-warning">{{ ucfirst($item->kurikulum->status) }}</span>
                                @else
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                @endif
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Belum diatur</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-info" wire:click="bukaPengaturan({{ $item->id_mahasiswa }})">
                                <i class="ri-settings-3-line"></i> Atur
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Mahasiswa tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $mahasiswa->links() }}</div>

    @if ($edit_mahasiswa_id && $mahasiswaEdit)
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="pengaturan-mahasiswa-title">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" wire:submit="simpanIndividual">
                    <div class="modal-header">
                        <h5 class="modal-title" id="pengaturan-mahasiswa-title">Atur Kurikulum Mahasiswa</h5>
                        <button type="button" class="btn-close" wire:click="tutupPengaturan" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="fw-semibold">{{ $mahasiswaEdit->nama }}</div>
                            <div class="text-muted">{{ strtoupper($mahasiswaEdit->nim) }} · {{ $mahasiswaEdit->prodi?->nama }} · Angkatan {{ $mahasiswaEdit->angkatan }}</div>
                        </div>

                        @if ($mahasiswaEdit->kurikulum && ($mahasiswaEdit->kurikulum->status !== 'aktif' || $mahasiswaEdit->kurikulum->trashed()))
                            <div class="alert alert-warning">
                                Kurikulum saat ini: <strong>{{ $mahasiswaEdit->kurikulum->kode }} - {{ $mahasiswaEdit->kurikulum->nama }}</strong>
                                ({{ $mahasiswaEdit->kurikulum->trashed() ? 'dihapus' : $mahasiswaEdit->kurikulum->status }}).
                                Pilih kurikulum aktif atau kosongkan assignment.
                            </div>
                        @endif

                        <label class="form-label" for="edit-kurikulum">Kurikulum</label>
                        <select id="edit-kurikulum" class="form-select" wire:model="edit_kurikulum_id">
                            <option value="">Belum diatur / kosongkan</option>
                            @if ($mahasiswaEdit->kurikulum && ($mahasiswaEdit->kurikulum->status !== 'aktif' || $mahasiswaEdit->kurikulum->trashed()))
                                <option value="{{ $mahasiswaEdit->kurikulum->id_kurikulum }}" disabled>
                                    {{ $mahasiswaEdit->kurikulum->kode }} - {{ $mahasiswaEdit->kurikulum->nama }} (tidak aktif)
                                </option>
                            @endif
                            @foreach ($kurikulumEdit as $item)
                                <option value="{{ $item->id_kurikulum }}">{{ $item->kode }} - {{ $item->nama }} ({{ $item->tahun_berlaku }})</option>
                            @endforeach
                        </select>
                        @error('edit_kurikulum_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" wire:click="tutupPengaturan">Batal</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpanIndividual">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>