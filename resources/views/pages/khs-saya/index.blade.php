<?php

use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        $this->mahasiswa();
    }

    private function mahasiswa(): Mahasiswa
    {
        abort_unless(auth()->user()?->can('khs-saya:'), 403);
        $mahasiswa = auth()->user()?->mahasiswa;
        abort_unless($mahasiswa, 403, 'Akun ini belum terhubung ke data mahasiswa.');

        return $mahasiswa;
    }

    public function with(): array
    {
        $mahasiswa = $this->mahasiswa()->load('kurikulum.skala_nilai.detail');
        $kurikulum = $mahasiswa->kurikulum;

        if (! $kurikulum) {
            return compact('mahasiswa', 'kurikulum') + ['khs' => collect()];
        }

        $finalisasiTerbaru = DB::table('finalisasi_dpna_blok')
            ->selectRaw('blok_id, MAX(versi) as versi')
            ->groupBy('blok_id');

        $detailSkala = $kurikulum->skala_nilai?->detail ?? collect();
        $nilai = DB::table('snapshot_dpna_peserta as snapshot')
            ->join('finalisasi_dpna_blok as finalisasi', 'finalisasi.id_finalisasi_dpna_blok', '=', 'snapshot.finalisasi_dpna_blok_id')
            ->joinSub($finalisasiTerbaru, 'finalisasi_terbaru', function ($join) {
                $join->on('finalisasi_terbaru.blok_id', '=', 'finalisasi.blok_id')
                    ->on('finalisasi_terbaru.versi', '=', 'finalisasi.versi');
            })
            ->join('blok', 'blok.id', '=', 'finalisasi.blok_id')
            ->join('semester', 'semester.id_semester', '=', 'blok.semester_id')
            ->leftJoin('kurikulum_mata_kuliah as kurikulum_blok', 'kurikulum_blok.id_kurikulum_mata_kuliah', '=', 'blok.kurikulum_mata_kuliah_id')
            ->leftJoin('mata_kuliah as mata_kuliah_kurikulum', 'mata_kuliah_kurikulum.id', '=', 'kurikulum_blok.mata_kuliah_id')
            ->leftJoin('mata_kuliah as mata_kuliah_blok', 'mata_kuliah_blok.id', '=', 'blok.mata_kuliah_id')
            ->where('snapshot.nim', $mahasiswa->nim)
            ->where('finalisasi.status', 'final')
            ->selectRaw('snapshot.id_snapshot_dpna_peserta, snapshot.nilai_akhir, semester.id_semester as semester_id, semester.kode as semester_kode, semester.nama as semester_nama, semester.tahun as semester_tahun, COALESCE(mata_kuliah_kurikulum.kode, mata_kuliah_blok.kode) as mata_kuliah_kode, COALESCE(mata_kuliah_kurikulum.nama, mata_kuliah_blok.nama, blok.nama) as mata_kuliah_nama, COALESCE(mata_kuliah_kurikulum.sks, mata_kuliah_blok.sks, blok.sks) as sks')
            ->orderByDesc('semester.kode')
            ->orderBy('mata_kuliah_nama')
            ->get()
            ->map(function ($item) use ($detailSkala) {
                $nilaiAkhir = (float) $item->nilai_akhir;
                $item->nilai_akhir = $nilaiAkhir;
                $item->sks = (float) $item->sks;
                $item->detail_nilai = $detailSkala->first(
                    fn ($detail) => $nilaiAkhir >= (float) $detail->nilai_angka_min
                        && $nilaiAkhir <= (float) $detail->nilai_angka_max
                );

                return $item;
            });

        $khs = $nilai->groupBy('semester_id')->map(function ($mataKuliah) {
            $semester = $mataKuliah->first();
            $totalSks = $mataKuliah->sum('sks');
            $totalMutu = $mataKuliah->sum(
                fn ($item) => $item->detail_nilai
                    ? $item->sks * (float) $item->detail_nilai->nilai_indeks
                    : 0
            );

            return (object) [
                'id' => $semester->semester_id,
                'kode' => $semester->semester_kode,
                'nama' => $semester->semester_nama,
                'tahun' => $semester->semester_tahun,
                'mata_kuliah' => $mataKuliah,
                'total_sks' => $totalSks,
                'ips' => $totalSks > 0 && $mataKuliah->every(fn ($item) => $item->detail_nilai !== null)
                    ? $totalMutu / $totalSks
                    : null,
            ];
        })->values();

        return compact('mahasiswa', 'kurikulum', 'khs');
    }
}; ?>

<div>
    <div class="mb-3">
        <h4 class="mb-1">Kartu Hasil Studi</h4>
        <div class="text-muted">{{ $mahasiswa->nim }} · {{ $mahasiswa->nama }}</div>
    </div>

    @if (! $kurikulum)
        <div class="alert alert-warning">Kurikulum mahasiswa belum ditetapkan.</div>
    @elseif ($khs->isEmpty())
        <div class="alert alert-info">Belum ada nilai DPNA final.</div>
    @else
        @foreach ($khs as $semester)
            <div class="card mb-3" wire:key="khs-semester-{{ $semester->id }}">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-0">Semester {{ ucfirst($semester->nama) }} {{ $semester->tahun }}</h5>
                        <small class="text-muted">{{ $semester->kode }}</small>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary-subtle text-primary fs-6">Total SKS: {{ number_format($semester->total_sks, 1, ',', '.') }}</span>
                        <span class="badge bg-success-subtle text-success fs-6">IPS: {{ $semester->ips === null ? '-' : number_format($semester->ips, 2, ',', '.') }}</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center">No</th>
                                    <th>Kode</th>
                                    <th>Mata Kuliah</th>
                                    <th class="text-center">SKS</th>
                                    <th class="text-center">Nilai Akhir</th>
                                    <th class="text-center">Huruf</th>
                                    <th class="text-center">Indeks</th>
                                    <th class="text-center">Kelulusan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($semester->mata_kuliah as $item)
                                    <tr wire:key="khs-nilai-{{ $item->id_snapshot_dpna_peserta }}">
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>{{ $item->mata_kuliah_kode ?? '-' }}</td>
                                        <td>{{ $item->mata_kuliah_nama }}</td>
                                        <td class="text-center">{{ number_format($item->sks, 1, ',', '.') }}</td>
                                        <td class="text-center">{{ number_format($item->nilai_akhir, 2, ',', '.') }}</td>
                                        <td class="text-center">{{ $item->detail_nilai?->nilai_huruf ?? '-' }}</td>
                                        <td class="text-center">{{ $item->detail_nilai ? number_format((float) $item->detail_nilai->nilai_indeks, 2, ',', '.') : '-' }}</td>
                                        <td class="text-center">
                                            @if ($item->detail_nilai)
                                                <span @class(['badge', 'bg-success-subtle text-success' => $item->detail_nilai->lulus, 'bg-danger-subtle text-danger' => ! $item->detail_nilai->lulus])>
                                                    {{ $item->detail_nilai->lulus ? 'Lulus' : 'Belum Lulus' }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="text-muted small">KHS hanya menampilkan nilai dari versi DPNA terbaru yang berstatus final.</div>
    @endif
</div>