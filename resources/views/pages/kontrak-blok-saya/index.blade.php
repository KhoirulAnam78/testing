<?php

use App\Models\Blok;
use App\Models\Mahasiswa;
use App\Models\PesertaBlok;
use App\Models\Semester;
use App\Support\KelayakanKontrakBlok;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        $this->mahasiswa();
    }

    public function kontrak(int $blokId): void
    {
        $mahasiswaId = (int) $this->mahasiswa()->id_mahasiswa;

        DB::transaction(function () use ($blokId, $mahasiswaId) {
            $mahasiswa = Mahasiswa::with('kurikulum.skala_nilai.detail')->findOrFail($mahasiswaId);
            $semester = Semester::where('is_aktif', true)->lockForUpdate()->first();

            if (! $semester) {
                throw ValidationException::withMessages(['kontrak' => 'Semester aktif tidak tersedia.']);
            }

            $blok = Blok::with('kurikulum_mata_kuliah')->whereKey($blokId)->firstOrFail();
            $hasil = app(KelayakanKontrakBlok::class)->evaluasi($mahasiswa, $blok, $semester);

            if (! $hasil['layak']) {
                throw ValidationException::withMessages(['kontrak' => implode(' ', $hasil['alasan'])]);
            }

            $peserta = PesertaBlok::withTrashed()
                ->where('blok_id', $blok->id)
                ->where('mahasiswa_id', $mahasiswa->id_mahasiswa)
                ->lockForUpdate()
                ->first();

            if ($peserta && ! $peserta->trashed()) {
                throw ValidationException::withMessages(['kontrak' => 'Blok ini sudah dikontrak.']);
            }

            $peserta ??= new PesertaBlok([
                'blok_id' => $blok->id,
                'mahasiswa_id' => $mahasiswa->id_mahasiswa,
            ]);
            $peserta->fill([
                'kelas_id' => null,
                'status' => $hasil['status'],
                'tanggal_masuk' => now()->toDateString(),
            ]);

            if ($peserta->trashed()) {
                $peserta->restore();
            }

            $peserta->save();
        });

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Kontrak blok berhasil disimpan.',
        ]);
    }

    private function mahasiswa(): Mahasiswa
    {
        abort_unless(auth()->user()?->can('kontrak-blok-saya:'), 403);
        $mahasiswa = auth()->user()?->mahasiswa;
        abort_unless($mahasiswa, 403, 'Akun ini belum terhubung ke data mahasiswa.');

        return $mahasiswa;
    }

    public function with(): array
    {
        $mahasiswa = $this->mahasiswa()->load('kurikulum.skala_nilai.detail');
        $semester = Semester::where('is_aktif', true)->first();
        $blokList = collect();

        if ($semester) {
            $blokList = Blok::query()
                ->with(['mata_kuliah', 'kurikulum_mata_kuliah.aturan', 'kurikulum_mata_kuliah.prasyarat.mata_kuliah_prasyarat.mata_kuliah', 'kurikulum_mata_kuliah.prasyarat.nilai_minimum'])
                ->where('semester_id', $semester->id_semester)
                ->where('prodi_id', $mahasiswa->prodi_id)
                ->where('status', 'aktif')
                ->orderBy('nama')
                ->get();

            $peserta = PesertaBlok::where('mahasiswa_id', $mahasiswa->id_mahasiswa)
                ->whereIn('blok_id', $blokList->pluck('id'))
                ->get()
                ->keyBy('blok_id');
            $service = app(KelayakanKontrakBlok::class);

            $blokList->each(function (Blok $blok) use ($service, $mahasiswa, $semester, $peserta) {
                $blok->setAttribute('hasil_kelayakan', $service->evaluasi($mahasiswa, $blok, $semester));
                $blok->setAttribute('kontrak_tersimpan', $peserta->get($blok->id));
            });
        }

        return compact('mahasiswa', 'semester', 'blokList');
    }
}; ?>

<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-1">Kontrak Blok Saya</h4>
            <div class="text-muted">{{ $mahasiswa->nim }} · {{ $mahasiswa->nama }}</div>
        </div>
        @if ($semester)
            <span class="badge bg-primary-subtle text-primary fs-6">{{ ucfirst($semester->nama) }} {{ $semester->tahun }}</span>
        @endif
    </div>

    @error('kontrak')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    @if (! $semester)
        <div class="alert alert-warning">Semester aktif belum tersedia.</div>
    @else
        <div class="card mb-3">
            <div class="card-body">
                <div class="fw-semibold">Masa Kontrak</div>
                @if ($semester->kontrak_mulai && $semester->kontrak_selesai)
                    <div>{{ $semester->kontrak_mulai->format('d/m/Y H:i') }} sampai {{ $semester->kontrak_selesai->format('d/m/Y H:i') }}</div>
                    <span @class(['badge mt-2', 'bg-success-subtle text-success' => $semester->kontrakSedangDibuka(), 'bg-secondary-subtle text-secondary' => ! $semester->kontrakSedangDibuka()])>
                        {{ $semester->kontrakSedangDibuka() ? 'Dibuka' : 'Ditutup' }}
                    </span>
                @else
                    <div class="text-muted">Jadwal belum diatur.</div>
                @endif
            </div>
        </div>

        <div class="row">
            @forelse ($blokList as $blok)
                @php($hasil = $blok->hasil_kelayakan)
                @php($kontrak = $blok->kontrak_tersimpan)
                <div class="col-lg-6 mb-3" wire:key="blok-kontrak-{{ $blok->id }}">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <h5 class="mb-1">{{ $blok->nama }}</h5>
                                    <div class="text-muted small">{{ $blok->mata_kuliah?->nama ?? 'Mata kuliah belum dipetakan' }} · {{ $blok->sks }} SKS</div>
                                </div>
                                @if ($kontrak)
                                    <span class="badge bg-success-subtle text-success align-self-start">{{ ucfirst($kontrak->status) }}</span>
                                @endif
                            </div>

                            @if (! $kontrak)
                                @if ($hasil['layak'])
                                    <div class="text-success small mt-3">
                                        Memenuhi syarat kontrak sebagai {{ $hasil['status'] === 'mengulang' ? 'pengambilan ulang' : 'pengambilan pertama' }}.
                                    </div>
                                @else
                                    <ul class="text-danger small mt-3 mb-0 ps-3">
                                        @foreach ($hasil['alasan'] as $alasan)
                                            <li>{{ $alasan }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endif

                            <div class="mt-auto pt-3 text-end">
                                <button type="button" class="btn btn-primary" wire:click="kontrak({{ $blok->id }})"
                                    wire:loading.attr="disabled" wire:target="kontrak({{ $blok->id }})"
                                    wire:confirm="Kontrak blok {{ $blok->nama }}?" @disabled($kontrak || ! $hasil['layak'])>
                                    <span wire:loading.remove wire:target="kontrak({{ $blok->id }})"><i class="ri-add-circle-line"></i> Kontrak</span>
                                    <span wire:loading wire:target="kontrak({{ $blok->id }})">Menyimpan...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-info">Belum ada blok aktif untuk program studi Anda pada semester aktif.</div></div>
            @endforelse
        </div>
    @endif
</div>