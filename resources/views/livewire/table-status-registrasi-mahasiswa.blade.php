<div>
    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <label class="form-label" for="filter-semester">Semester</label>
            <select id="filter-semester" class="form-select" wire:model.live="filter_semester_id">
                <option value="">Pilih semester</option>
                @foreach ($semester as $item)
                    <option value="{{ $item->id_semester }}">
                        {{ $item->kode }} - {{ ucfirst($item->nama) }} {{ $item->tahun }}{{ $item->is_aktif ? ' (Aktif)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter-cari">NIM / Nama</label>
            <input id="filter-cari" type="search" class="form-control" wire:model.live.debounce.400ms="filter_pencarian" placeholder="Cari mahasiswa">
        </div>
        <div class="col-md-2">
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
        <div class="col-md-2">
            <label class="form-label" for="filter-status">Status</label>
            <select id="filter-status" class="form-select" wire:model.live="filter_status">
                <option value="">Semua status</option>
                <option value="belum_diatur">Belum diatur</option>
                <option value="aktif">Aktif</option>
                <option value="cuti">Cuti</option>
                <option value="nonaktif">Nonaktif</option>
            </select>
        </div>
    </div>

    @if (! $filter_semester_id)
        <div class="alert alert-info mb-0">Pilih semester untuk melihat dan mengatur status registrasi.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Prodi</th>
                        <th>Angkatan</th>
                        <th>Status Mahasiswa</th>
                        <th>Status Registrasi</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mahasiswa as $item)
                        @php($registrasi = $item->status_registrasi->first())
                        <tr wire:key="status-registrasi-{{ $filter_semester_id }}-{{ $item->id_mahasiswa }}">
                            <td class="fw-semibold">{{ strtoupper($item->nim) }}</td>
                            <td>{{ $item->nama }}</td>
                            <td>{{ $item->prodi?->kode ?? '-' }}</td>
                            <td>{{ $item->angkatan }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($item->status) }}</span></td>
                            <td>
                                @if ($registrasi?->status === 'aktif')
                                    <span class="badge bg-success-subtle text-success">Aktif</span>
                                @elseif ($registrasi?->status === 'cuti')
                                    <span class="badge bg-warning-subtle text-warning">Cuti</span>
                                @elseif ($registrasi?->status === 'nonaktif')
                                    <span class="badge bg-danger-subtle text-danger">Nonaktif</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Belum diatur</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-info btn-sm"
                                    wire:click="bukaPengaturan({{ $item->id_mahasiswa }})"
                                    @disabled($item->status === 'lulus' && $semester->firstWhere('id_semester', (int) $filter_semester_id)?->is_aktif)
                                >
                                    <i class="ri-file-edit-line"></i> Atur
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Mahasiswa tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $mahasiswa->links() }}</div>
    @endif

    @if ($edit_mahasiswa_id && $mahasiswaEdit && $semesterEdit)
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="pengaturan-registrasi-title">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" wire:submit="simpanIndividual">
                    <div class="modal-header">
                        <h5 class="modal-title" id="pengaturan-registrasi-title">Atur Status Registrasi</h5>
                        <button type="button" class="btn-close" wire:click="tutupPengaturan" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="fw-semibold">{{ $mahasiswaEdit->nama }}</div>
                            <div class="text-muted">{{ strtoupper($mahasiswaEdit->nim) }} · {{ $mahasiswaEdit->prodi?->nama }} · Angkatan {{ $mahasiswaEdit->angkatan }}</div>
                            <div class="text-muted">Semester {{ $semesterEdit->kode }} - {{ ucfirst($semesterEdit->nama) }} {{ $semesterEdit->tahun }}</div>
                        </div>

                        <label class="form-label" for="edit-status">Status Registrasi</label>
                        <select id="edit-status" class="form-select" wire:model="edit_status">
                            <option value="aktif">Aktif</option>
                            <option value="cuti">Cuti</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                        @error('edit_status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
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