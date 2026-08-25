<div>
    <div class="row g-2 mb-3">
        <div class="col-lg-6">
            <label class="visually-hidden" for="dpna-search">Cari blok</label>
            <input id="dpna-search" type="search" class="form-control" placeholder="Cari kode atau nama blok..." wire:model.live.debounce.300ms="search">
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="visually-hidden" for="dpna-prodi">Prodi</label>
            <select id="dpna-prodi" class="form-select" wire:model.live="prodiId">
                <option value="">Semua prodi</option>
                @foreach ($prodis as $prodi)<option value="{{ $prodi->id_prodi }}">{{ $prodi->nama }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="visually-hidden" for="dpna-semester">Semester</label>
            <select id="dpna-semester" class="form-select" wire:model.live="semesterId">
                <option value="">Semua semester</option>
                @foreach ($semesters as $semester)<option value="{{ $semester->id_semester }}">{{ ucfirst($semester->nama) }} {{ $semester->tahun }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4 col-lg-1 d-grid">
            <button type="button" class="btn btn-soft-secondary" wire:click="resetFilters" aria-label="Reset filter"><i class="ri-refresh-line"></i></button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Blok</th><th>Prodi / Semester</th><th class="text-center">Peserta</th><th class="text-center">Pertemuan</th><th>Status Bobot</th><th></th></tr></thead>
            <tbody>
            @forelse ($bloks as $blok)
                @php($totalBobot = ($blok->kehadiran_masuk_dpna ? (float) $blok->bobot_kehadiran_dpna : 0) + (float) $blok->total_bobot_kegiatan_dpna)
                <tr wire:key="dpna-{{ $blok->id }}">
                    <td><div class="fw-semibold">{{ $blok->kode }}</div><div class="text-muted small">{{ $blok->nama }}</div></td>
                    <td>{{ $blok->prodi->nama }}<div class="text-muted small">{{ ucfirst($blok->semester->nama) }} {{ $blok->semester->tahun }}</div></td>
                    <td class="text-center">{{ $blok->peserta_blok_count }}</td>
                    <td class="text-center">{{ $blok->pertemuan_blok_count }}</td>
                    <td><span class="badge {{ abs($totalBobot - 100) < .001 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ number_format($totalBobot, 2, ',', '.') }}%</span></td>
                    <td class="text-end"><a class="btn btn-primary btn-sm" wire:navigate href="{{ route('dpna-blok.detail', Crypt::encrypt($blok->id)) }}">Buka DPNA</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Blok tidak ditemukan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $bloks->links() }}
</div>