<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Hadir Mahasiswa</title>
    <style>
        @page { margin: 28px 32px; }
        body { color: #1f2937; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
        .subtitle { color: #4b5563; margin-bottom: 18px; text-align: center; }
        .info { border-collapse: collapse; margin-bottom: 16px; width: 100%; }
        .info td { padding: 2px 4px; vertical-align: top; }
        .info .label { color: #4b5563; width: 105px; }
        .info .separator { width: 8px; }
        .attendance { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .attendance th, .attendance td { border: 1px solid #9ca3af; padding: 5px 6px; }
        .attendance th { background: #e5e7eb; font-weight: bold; text-align: center; }
        .attendance .number { text-align: center; width: 28px; }
        .attendance .nim { width: 90px; }
        .attendance .status { text-align: center; width: 72px; }
        .attendance .note { width: 120px; }
        .empty { color: #6b7280; padding: 14px !important; text-align: center; }
        .summary { margin-top: 12px; }
        .summary span { margin-right: 14px; }
        .footer { color: #6b7280; font-size: 8px; margin-top: 18px; text-align: right; }
    </style>
</head>
<body>
    @php
        $statusMeta = \App\Models\PresensiPertemuanBlok::STATUS;
        $statusLabel = collect($statusMeta)
            ->map(fn ($meta) => $meta['kode'].' - '.$meta['label'])
            ->put('belum_diisi', 'Belum diisi');
        $dosen = $pertemuan->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->join(', ');
        $jam = collect([$pertemuan->jam_mulai, $pertemuan->jam_selesai])
            ->filter()
            ->map(fn ($nilai) => substr((string) $nilai, 0, 5))
            ->join(' - ');
    @endphp

    <h1>DAFTAR HADIR MAHASISWA</h1>
    <div class="subtitle">Pertemuan Blok</div>

    <table class="info">
        <tr>
            <td class="label">Blok</td><td class="separator">:</td>
            <td>{{ $pertemuan->blok?->nama }}</td>
            <td class="label">Semester</td><td class="separator">:</td>
            <td>
                @if ($pertemuan->blok?->semester)
                    {{ ucfirst($pertemuan->blok->semester->nama) }} {{ $pertemuan->blok->semester->tahun }}
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Kegiatan</td><td class="separator">:</td>
            <td>{{ $pertemuan->aturan_kegiatan_blok?->jenis_kegiatan?->nama ?: '-' }}</td>
            <td class="label">Kelompok</td><td class="separator">:</td>
            <td>
                {{ $pertemuan->kelompok_blok?->kode ?: '-' }}
                @if ($pertemuan->kelompok_blok?->nama)
                    - {{ $pertemuan->kelompok_blok->nama }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Pertemuan</td><td class="separator">:</td>
            <td>
                @if ($pertemuan->materi_rinci_blok?->pertemuan_ke)
                    {{ $pertemuan->materi_rinci_blok->pertemuan_ke }} -
                @endif
                {{ $pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik ?: '-' }}
            </td>
            <td class="label">Tanggal</td><td class="separator">:</td>
            <td>{{ $pertemuan->tanggal?->format('d/m/Y') ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Dosen Pengampu</td><td class="separator">:</td>
            <td>{{ $dosen ?: '-' }}</td>
            <td class="label">Waktu / Ruang</td><td class="separator">:</td>
            <td>{{ $jam ?: '-' }}{{ $pertemuan->ruangan ? ' / '.$pertemuan->ruangan : '' }}</td>
        </tr>
    </table>

    <table class="attendance">
        <thead>
            <tr>
                <th class="number">No.</th>
                <th class="nim">NIM</th>
                <th>Nama Mahasiswa</th>
                <th class="status">Status</th>
                <th class="note">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($peserta as $index => $item)
                @php($presensi = $item->presensi_pertemuan_blok->first())
                <tr>
                    <td class="number">{{ $index + 1 }}</td>
                    <td class="nim">{{ $item->mahasiswa?->nim }}</td>
                    <td>{{ $item->mahasiswa?->nama }}</td>
                    <td class="status">{{ $statusLabel[$presensi?->status ?? 'belum_diisi'] }}</td>
                    <td>{{ $presensi?->keterangan ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">Kelompok pertemuan belum memiliki anggota aktif.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">
        <span><strong>Total:</strong> {{ $peserta->count() }}</span>
        @foreach ($statusMeta as $status => $meta)
            <span><strong>{{ $meta['kode'] }} - {{ $meta['label'] }}:</strong> {{ $rekap[$status] }}</span>
        @endforeach
        <span><strong>Belum diisi:</strong> {{ $rekap['belum_diisi'] }}</span>
    </div>

    <div class="footer">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>