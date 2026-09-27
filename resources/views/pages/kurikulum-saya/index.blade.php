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
        abort_unless(auth()->user()?->can('kurikulum-saya:'), 403);
        $mahasiswa = auth()->user()?->mahasiswa;
        abort_unless($mahasiswa, 403, 'Akun ini belum terhubung ke data mahasiswa.');

        return $mahasiswa;
    }

    public function with(): array
    {
        $mahasiswa = $this->mahasiswa()->load([
            'kurikulum.skala_nilai.detail',
            'kurikulum.mata_kuliah.mata_kuliah' => fn ($query) => $query->withTrashed(),
        ]);
        $kurikulum = $mahasiswa->kurikulum;

        if (! $kurikulum) {
            return compact('mahasiswa', 'kurikulum') + ['mataKuliah' => collect()];
        }

        $nilaiTertinggi = DB::table('snapshot_dpna_peserta as snapshot')
            ->join('finalisasi_dpna_blok as finalisasi', 'finalisasi.id_finalisasi_dpna_blok', '=', 'snapshot.finalisasi_dpna_blok_id')
            ->join('blok', 'blok.id', '=', 'finalisasi.blok_id')
            ->leftJoin('kurikulum_mata_kuliah as kurikulum_blok', 'kurikulum_blok.id_kurikulum_mata_kuliah', '=', 'blok.kurikulum_mata_kuliah_id')
            ->leftJoin('peserta_blok as peserta', 'peserta.id_peserta_blok', '=', 'snapshot.peserta_blok_id')
            ->where('finalisasi.status', 'final')
            ->where(function ($query) use ($mahasiswa) {
                $query->where('peserta.mahasiswa_id', $mahasiswa->id_mahasiswa)
                    ->orWhere('snapshot.nim', $mahasiswa->nim);
            })
            ->whereNotNull(DB::raw('COALESCE(kurikulum_blok.mata_kuliah_id, blok.mata_kuliah_id)'))
            ->groupByRaw('COALESCE(kurikulum_blok.mata_kuliah_id, blok.mata_kuliah_id)')
            ->selectRaw('COALESCE(kurikulum_blok.mata_kuliah_id, blok.mata_kuliah_id) as mata_kuliah_id, MAX(snapshot.nilai_akhir) as nilai_akhir')
            ->pluck('nilai_akhir', 'mata_kuliah_id');

        $detailSkala = $kurikulum->skala_nilai?->detail ?? collect();
        $mataKuliah = $kurikulum->mata_kuliah
            ->sortBy([['semester_urutan', 'asc'], ['mata_kuliah.nama', 'asc']])
            ->values()
            ->map(function ($item) use ($nilaiTertinggi, $detailSkala) {
                $nilai = $nilaiTertinggi->get($item->mata_kuliah_id);
                $nilai = $nilai === null ? null : (float) $nilai;
                $detail = $nilai === null ? null : $detailSkala->first(
                    fn ($detail) => $nilai >= (float) $detail->nilai_angka_min
                        && $nilai <= (float) $detail->nilai_angka_max
                );

                return (object) [
                    'semester_urutan' => $item->semester_urutan,
                    'apakah_wajib' => $item->apakah_wajib,
                    'mata_kuliah' => $item->mata_kuliah,
                    'nilai_akhir' => $nilai,
                    'detail_nilai' => $detail,
                ];
            });

        return compact('mahasiswa', 'kurikulum', 'mataKuliah');
    }
}; ?>

<div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1">Kurikulum Saya</h4>
            <div class="text-muted">{{ $mahasiswa->nim }} · {{ $mahasiswa->nama }}</div>
        </div>
        @if ($kurikulum)
            <span class="badge bg-primary-subtle text-primary fs-6">{{ $kurikulum->kode }} · {{ $kurikulum->nama }}</span>
        @endif
    </div>

    @if (! $kurikulum)
        <div class="alert alert-warning">Kurikulum belum ditetapkan untuk mahasiswa ini.</div>
    @else
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th class="text-center">Semester</th>
                                <th>Kode</th>
                                <th>Mata Kuliah</th>
                                <th class="text-center">SKS</th>
                                <th class="text-center">Sifat</th>
                                <th class="text-center">Nilai Akhir</th>
                                <th class="text-center">Huruf</th>
                                <th class="text-center">Indeks</th>
                                <th class="text-center">Kelulusan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($mataKuliah as $item)
                                <tr wire:key="kurikulum-saya-{{ $item->mata_kuliah->id }}">
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $item->semester_urutan }}</td>
                                    <td>{{ $item->mata_kuliah->kode }}</td>
                                    <td>{{ $item->mata_kuliah->nama }}</td>
                                    <td class="text-center">{{ $item->mata_kuliah->sks }}</td>
                                    <td class="text-center">
                                        <span @class(['badge', 'bg-primary-subtle text-primary' => $item->apakah_wajib, 'bg-secondary-subtle text-secondary' => ! $item->apakah_wajib])>
                                            {{ $item->apakah_wajib ? 'Wajib' : 'Pilihan' }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ $item->nilai_akhir === null ? '-' : number_format($item->nilai_akhir, 2, ',', '.') }}</td>
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
                            @empty
                                <tr><td colspan="10" class="text-center text-muted py-4">Belum ada mata kuliah pada kurikulum ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="text-muted small mt-2">Nilai ditampilkan dari nilai resmi tertinggi pada DPNA yang masih berstatus final.</div>
    @endif
</div>