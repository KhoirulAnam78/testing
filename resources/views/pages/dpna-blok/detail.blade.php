<?php

use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Support\PerhitunganDpnaBlok;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    #[Locked]
    public int $blokId;

    public bool $kehadiranAktif = false;
    public string $bobotKehadiran = '0';
    public array $kegiatan = [];
    public ?int $pesertaTerpilih = null;

    public function mount(string $id): void
    {
        try {
            $this->blokId = (int) Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404, 'Enkripsi tidak valid !');
        }

        $blok = Blok::with('aturan_kegiatan_blok')->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        $this->kehadiranAktif = $blok->kehadiran_masuk_dpna;
        $this->bobotKehadiran = (string) $blok->bobot_kehadiran_dpna;
        foreach ($blok->aturan_kegiatan_blok as $aturan) {
            $this->kegiatan[$aturan->id] = [
                'aktif' => $aturan->nilai_masuk_dpna,
                'bobot' => (string) $aturan->bobot_nilai_dpna,
            ];
        }
    }

    public function simpanBobot(): void
    {
        $blok = Blok::with('aturan_kegiatan_blok:id,blok_id')->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        $this->validate([
            'kehadiranAktif' => ['boolean'],
            'bobotKehadiran' => ['required', 'numeric', 'min:0', 'max:100'],
            'kegiatan' => ['array'],
            'kegiatan.*.aktif' => ['boolean'],
            'kegiatan.*.bobot' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $sumber = [[
            'aktif' => $this->kehadiranAktif,
            'bobot' => (float) $this->bobotKehadiran,
            'field' => 'bobotKehadiran',
        ]];
        foreach ($blok->aturan_kegiatan_blok as $aturan) {
            $config = $this->kegiatan[$aturan->id] ?? ['aktif' => false, 'bobot' => 0];
            $sumber[] = [
                'aktif' => (bool) $config['aktif'],
                'bobot' => (float) $config['bobot'],
                'field' => "kegiatan.{$aturan->id}.bobot",
            ];
        }

        foreach ($sumber as $item) {
            if ($item['aktif'] && $item['bobot'] <= 0) {
                $this->addError($item['field'], 'Sumber aktif wajib memiliki bobot lebih dari 0.');
            }
            if (! $item['aktif'] && $item['bobot'] != 0) {
                $this->addError($item['field'], 'Sumber nonaktif wajib berbobot 0.');
            }
        }

        $total = collect($sumber)->where('aktif', true)->sum('bobot');
        if (abs($total - 100) > .001) {
            $this->addError('totalBobot', 'Total bobot sumber aktif wajib tepat 100%. Saat ini '.number_format($total, 2, ',', '.').'%.');
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($blok): void {
            $blok->update([
                'kehadiran_masuk_dpna' => $this->kehadiranAktif,
                'bobot_kehadiran_dpna' => $this->kehadiranAktif ? $this->bobotKehadiran : 0,
            ]);
            foreach ($blok->aturan_kegiatan_blok as $aturan) {
                $config = $this->kegiatan[$aturan->id];
                AturanKegiatanBlok::whereKey($aturan->id)->update([
                    'nilai_masuk_dpna' => (bool) $config['aktif'],
                    'bobot_nilai_dpna' => $config['aktif'] ? $config['bobot'] : 0,
                ]);
            }
        });

        session()->flash('success', 'Konfigurasi bobot DPNA berhasil disimpan.');
    }

    public function updatedKehadiranAktif(bool $aktif): void
    {
        if (! $aktif) {
            $this->bobotKehadiran = '0';
        }
    }

    public function updatedKegiatan(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.aktif') && ! $value) {
            data_set($this->kegiatan, str($key)->beforeLast('.')->append('.bobot')->value(), '0');
        }
    }

    public function pilihPeserta(int $id): void
    {
        $this->pesertaTerpilih = $this->pesertaTerpilih === $id ? null : $id;
    }

    public function data(): array
    {
        $blok = Blok::with(['prodi:id_prodi,nama', 'semester:id_semester,nama,tahun'])->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        return ['blok' => $blok] + app(PerhitunganDpnaBlok::class)->rekap($blok);
    }
}; ?>

<div>
    @php($data = $this->data())
    @php($blok = $data['blok'])
    @php($barisTerpilih = $data['baris']->first(fn ($row) => $row['peserta']->id_peserta_blok === $pesertaTerpilih))

    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">DPNA Blok</h4>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item"><a wire:navigate href="{{ route('dpna-blok.index') }}">DPNA Blok</a></li><li class="breadcrumb-item active">{{ $blok->kode }}</li></ol>
    </div>
    <livewire:alert />

    <div class="card">
        <div class="card-body d-flex flex-wrap justify-content-between gap-3">
            <div><div class="text-muted small">Blok</div><div class="fw-semibold">{{ $blok->kode }} - {{ $blok->nama }}</div></div>
            <div><div class="text-muted small">Prodi</div><div>{{ $blok->prodi->nama }}</div></div>
            <div><div class="text-muted small">Semester</div><div>{{ ucfirst($blok->semester->nama) }} {{ $blok->semester->tahun }}</div></div>
            <div><div class="text-muted small">Peserta</div><div>{{ $data['peserta']->count() }} mahasiswa</div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-1">Konfigurasi Sumber DPNA</h5><div class="text-muted small">Sumber aktif wajib berbobot lebih dari 0 dan totalnya tepat 100%.</div></div>
        <div class="card-body">
            <form wire:submit="simpanBobot">
                @error('totalBobot')<div class="alert alert-danger">{{ $message }}</div>@enderror
                <div class="table-responsive">
                    <table class="table align-middle mb-3">
                        <thead><tr><th>Sumber</th><th class="text-center">Masuk DPNA</th><th style="width: 180px">Bobot (%)</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><span class="fw-semibold">Kehadiran</span><div class="text-muted small">Persentase hadir dari seluruh pertemuan wajib presensi.</div></td>
                                <td class="text-center"><input class="form-check-input" type="checkbox" wire:model.live="kehadiranAktif" aria-label="Aktifkan kehadiran"></td>
                                <td><input class="form-control" type="number" min="0" max="100" step="0.01" wire:model="bobotKehadiran" @disabled(!$kehadiranAktif)>@error('bobotKehadiran')<div class="text-danger small">{{ $message }}</div>@enderror</td>
                            </tr>
                            @foreach ($data['kegiatan'] as $aturan)
                                <tr wire:key="bobot-{{ $aturan->id }}">
                                    <td><span class="fw-semibold">{{ $aturan->jenis_kegiatan->nama }}</span><div class="text-muted small">Rata-rata nilai {{ $aturan->pertemuan_blok_count }} pertemuan.</div></td>
                                    <td class="text-center"><input class="form-check-input" type="checkbox" wire:model.live="kegiatan.{{ $aturan->id }}.aktif" aria-label="Aktifkan {{ $aturan->jenis_kegiatan->nama }}"></td>
                                    <td><input class="form-control" type="number" min="0" max="100" step="0.01" wire:model="kegiatan.{{ $aturan->id }}.bobot" @disabled(!($kegiatan[$aturan->id]['aktif'] ?? false))>@error("kegiatan.{$aturan->id}.bobot")<div class="text-danger small">{{ $message }}</div>@enderror</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i>Simpan Bobot</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-1">Matriks DPNA</h5><div class="text-muted small">Klik mahasiswa untuk melihat rincian. Nilai akhir hanya tampil jika semua sumber aktif lengkap.</div></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead><tr><th class="text-center">No</th><th>NIM</th><th>Mahasiswa</th><th class="text-center">Kehadiran</th>@foreach ($data['kegiatan'] as $aturan)<th class="text-center">{{ $aturan->jenis_kegiatan->nama }}</th>@endforeach<th class="text-center">Nilai Akhir</th></tr></thead>
                    <tbody>
                    @forelse ($data['baris'] as $index => $row)
                        <tr role="button" wire:click="pilihPeserta({{ $row['peserta']->id_peserta_blok }})" wire:key="peserta-{{ $row['peserta']->id_peserta_blok }}">
                            <td class="text-center">{{ $index + 1 }}</td><td>{{ $row['peserta']->mahasiswa->nim }}</td><td class="fw-semibold">{{ $row['peserta']->mahasiswa->nama }}</td>
                            <td class="text-center">{{ $row['kehadiran'] === null ? 'Belum Lengkap' : number_format($row['kehadiran'], 2, ',', '.') }}</td>
                            @foreach ($data['kegiatan'] as $aturan)<td class="text-center">{{ $row['nilai_kegiatan'][$aturan->id] === null ? 'Belum Lengkap' : number_format($row['nilai_kegiatan'][$aturan->id], 2, ',', '.') }}</td>@endforeach
                            <td class="text-center fw-bold">{{ $row['nilai_akhir'] === null ? 'Belum Lengkap' : number_format($row['nilai_akhir'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-4" colspan="{{ 5 + $data['kegiatan']->count() }}">Belum ada peserta aktif.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($barisTerpilih)
        <div class="card border-primary" id="detail-mahasiswa">
            <div class="card-header d-flex justify-content-between"><div><h5 class="mb-0">Detail {{ $barisTerpilih['peserta']->mahasiswa->nama }}</h5><span class="text-muted">{{ $barisTerpilih['peserta']->mahasiswa->nim }}</span></div><button class="btn-close" type="button" wire:click="pilihPeserta({{ $pesertaTerpilih }})" aria-label="Tutup"></button></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small">Kehadiran</div><h5>{{ $barisTerpilih['kehadiran'] === null ? 'Belum Lengkap' : number_format($barisTerpilih['kehadiran'], 2, ',', '.').'%' }}</h5><div class="small">{{ $barisTerpilih['kehadiran_detail']['terisi'] }} dari {{ $barisTerpilih['kehadiran_detail']['wajib'] }} presensi terisi</div></div></div>
                    @foreach ($data['kegiatan'] as $aturan)<div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small">{{ $aturan->jenis_kegiatan->nama }}</div><h5>{{ $barisTerpilih['nilai_kegiatan'][$aturan->id] === null ? 'Belum Lengkap' : number_format($barisTerpilih['nilai_kegiatan'][$aturan->id], 2, ',', '.') }}</h5><div class="small">Bobot {{ number_format((float) $aturan->bobot_nilai_dpna, 2, ',', '.') }}%</div></div></div>@endforeach
                    <div class="col-md-4"><div class="border border-primary rounded p-3 h-100"><div class="text-muted small">Nilai Akhir Blok</div><h4 class="text-primary mb-0">{{ $barisTerpilih['nilai_akhir'] === null ? 'Belum Lengkap' : number_format($barisTerpilih['nilai_akhir'], 2, ',', '.') }}</h4></div></div>
                </div>
            </div>
        </div>
    @endif
</div>