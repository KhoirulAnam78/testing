<div class="border-top pt-3 mt-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
        <div>
            <span class="fw-semibold">{{ $detailKegiatan['nama'] }}</span>
            <span class="badge {{ $detailKegiatan['jenis'] === 'cbt' ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary' }}">
                {{ $detailKegiatan['jenis'] === 'cbt' ? 'CBT eksternal' : 'Manual' }}
            </span>
        </div>
        <span class="fw-semibold">{{ $detailKegiatan['nilai'] === null ? 'Belum Lengkap' : number_format((float) $detailKegiatan['nilai'], 2, ',', '.') }}</span>
    </div>

    @if ($detailKegiatan['jenis'] === 'cbt')
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Ujian Pertama</th><th>Remedial</th><th>Bobot</th><th>Sinkronisasi</th></tr></thead>
                <tbody><tr>
                    <td>{{ $detailKegiatan['ujian_pertama'] === null ? '-' : number_format((float) $detailKegiatan['ujian_pertama'], 2, ',', '.') }}</td>
                    <td>{{ $detailKegiatan['mengikuti_remedial'] ? ($detailKegiatan['nilai_remedial'] === null ? 'Belum tersedia' : number_format((float) $detailKegiatan['nilai_remedial'], 2, ',', '.')) : 'Tidak mengikuti' }}</td>
                    <td>{{ number_format((float) $detailKegiatan['bobot_ujian_pertama'], 2, ',', '.') }}% + {{ number_format((float) $detailKegiatan['bobot_remedial'], 2, ',', '.') }}%</td>
                    <td>
                        <div>{{ $detailKegiatan['referensi_eksternal'] ?: '-' }}</div>
                        <div class="text-muted small">{{ $detailKegiatan['disinkronkan_pada'] ? \Illuminate\Support\Carbon::parse($detailKegiatan['disinkronkan_pada'])->format('d/m/Y H:i') : 'Belum disinkronkan' }}</div>
                    </td>
                </tr></tbody>
            </table>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Pertemuan</th><th>Materi</th><th>Kelompok</th><th class="text-end">Nilai / 100</th></tr></thead>
                <tbody>
                    @forelse ($detailKegiatan['pertemuan'] ?? [] as $sesi)
                        <tr>
                            <td>{{ $sesi['pertemuan_ke'] ? 'Pertemuan '.$sesi['pertemuan_ke'] : '-' }}</td>
                            <td>{{ $sesi['materi'] ?: '-' }}</td>
                            <td>{{ $sesi['kelompok'] ?: '-' }}</td>
                            <td class="text-end">{{ $sesi['nilai'] === null ? 'Belum Lengkap' : number_format((float) $sesi['nilai'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Belum ada pertemuan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
