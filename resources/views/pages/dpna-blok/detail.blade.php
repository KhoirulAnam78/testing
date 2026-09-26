<?php

use App\Models\AnggotaGrupDpnaBlok;
use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\FinalisasiDpnaBlok;
use App\Models\GrupDpnaBlok;
use App\Support\FinalisasiDpnaBlokService;
use App\Support\PerhitunganDpnaBlok;
use App\Support\SinkronisasiNilaiCbtBlok;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Locked]
    public int $blokId;

    public bool $kehadiranAktif = false;

    public string $bobotKehadiran = '0';

    public array $kegiatan = [];

    public array $grup = [];

    public bool $modeGrup = false;

    public ?int $grupDiedit = null;

    public ?int $pesertaTerpilih = null;

    public ?int $riwayatTerpilihId = null;

    public string $alasanBuka = '';

    public array $hasilSinkronisasiCbt = [];

    public function mount(string $id): void
    {
        try {
            $this->blokId = (int) Crypt::decrypt($id);
        } catch (DecryptException) {
            abort(404, 'Enkripsi tidak valid !');
        }

        $this->muatKonfigurasi();
    }

    private function muatKonfigurasi(): void
    {
        $blok = Blok::with('aturan_kegiatan_blok')->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        $this->kehadiranAktif = $blok->kehadiran_masuk_dpna;
        $this->bobotKehadiran = (string) $blok->bobot_kehadiran_dpna;
        $this->kegiatan = [];
        foreach ($blok->aturan_kegiatan_blok as $aturan) {
            $this->kegiatan[$aturan->id] = [
                'aktif' => $aturan->nilai_masuk_dpna,
                'bobot' => (string) $aturan->bobot_nilai_dpna,
            ];
        }

        $this->muatGrup();
        $this->resetValidation();
    }

    private function muatGrup(): void
    {
        $this->grup = GrupDpnaBlok::query()
            ->where('blok_id', $this->blokId)
            ->with(['anggota_grup_dpna_blok' => fn ($query) => $query->where('aktif', true)])
            ->orderBy('urutan')
            ->get()
            ->map(fn (GrupDpnaBlok $item) => [
                'id' => $item->id_grup_dpna_blok,
                'nama' => $item->nama,
                'bobot' => (string) $item->bobot,
                'aktif' => $item->aktif,
                'urutan' => $item->urutan,
                'aturan_ids' => $item->anggota_grup_dpna_blok->pluck('aturan_kegiatan_blok_id')->map(fn ($id) => (string) $id)->all(),
            ])
            ->all();
        $this->modeGrup = $this->grup !== [];
        $this->grupDiedit = null;
    }

    public function tambahGrup(): void
    {
        $this->pastikanDraft();
        $this->resetValidation();
        $this->modeGrup = true;
        $this->grup[] = ['id' => null, 'nama' => '', 'bobot' => '0', 'aktif' => true, 'urutan' => count($this->grup) + 1, 'aturan_ids' => []];
        $this->grupDiedit = array_key_last($this->grup);
    }

    public function editGrup(int $index): void
    {
        $this->pastikanDraft();
        abort_unless(array_key_exists($index, $this->grup), 404);
        $this->resetValidation();
        $this->grupDiedit = $index;
    }

    public function selesaiEditGrup(): void
    {
        $index = $this->grupDiedit;
        abort_if($index === null || ! array_key_exists($index, $this->grup), 404);

        $this->validate([
            "grup.$index.nama" => ['required', 'string', 'regex:/\S/u', 'max:255'],
            "grup.$index.aturan_ids" => ['required', 'array', 'min:1'],
            "grup.$index.aturan_ids.*" => ['integer'],
        ]);
        $this->grupDiedit = null;
    }

    public function batalEditGrup(): void
    {
        $index = $this->grupDiedit;
        abort_if($index === null || ! array_key_exists($index, $this->grup), 404);

        if (empty($this->grup[$index]['id'])) {
            unset($this->grup[$index]);
            $this->grup = array_values($this->grup);
            $this->modeGrup = $this->grup !== [] || GrupDpnaBlok::where('blok_id', $this->blokId)->exists();
        } else {
            $model = GrupDpnaBlok::query()
                ->where('blok_id', $this->blokId)
                ->with(['anggota_grup_dpna_blok' => fn ($query) => $query->where('aktif', true)])
                ->findOrFail($this->grup[$index]['id']);
            $this->grup[$index] = [
                'id' => $model->id_grup_dpna_blok,
                'nama' => $model->nama,
                'bobot' => (string) $model->bobot,
                'aktif' => $model->aktif,
                'urutan' => $model->urutan,
                'aturan_ids' => $model->anggota_grup_dpna_blok->pluck('aturan_kegiatan_blok_id')->map(fn ($id) => (string) $id)->all(),
            ];
        }

        $this->grupDiedit = null;
        $this->resetValidation();
    }

    public function batalPerubahan(): void
    {
        $this->pastikanDraft();
        $this->muatKonfigurasi();
    }

    public function hapusGrup(int $index): void
    {
        $this->pastikanDraft();

        if (! array_key_exists($index, $this->grup)) {
            $this->addError('grup', 'Grup yang akan dihapus tidak ditemukan.');

            return;
        }

        if (count($this->grup) <= 1) {
            if (empty($this->grup[$index]['id'])) {
                $this->grup = [];
                $this->modeGrup = false;
                $this->grupDiedit = null;
                $this->resetValidation();

                return;
            }

            $this->addError('grup', 'Minimal satu grup wajib tersedia setelah mode grup digunakan.');

            return;
        }

        unset($this->grup[$index]);
        $this->grup = array_values($this->grup);
        if ($this->grupDiedit === $index) {
            $this->grupDiedit = null;
        } elseif ($this->grupDiedit !== null && $this->grupDiedit > $index) {
            $this->grupDiedit--;
        }
        $this->resetValidation();
    }

    public function buatGrupDariKonfigurasiLama(): void
    {
        $this->pastikanDraft();
        $blok = Blok::with('aturan_kegiatan_blok.jenis_kegiatan')->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        if ($this->grup !== []) {
            $this->addError('grup', 'Grup DPNA sudah tersedia.');

            return;
        }

        $grup = $blok->aturan_kegiatan_blok
            ->where('nilai_masuk_dpna', true)
            ->values()
            ->map(fn ($aturan, $index) => [
                'id' => null,
                'nama' => $aturan->jenis_kegiatan?->nama ?: 'Sumber '.($index + 1),
                'bobot' => (string) $aturan->bobot_nilai_dpna,
                'aktif' => true,
                'urutan' => $index + 1,
                'aturan_ids' => [(string) $aturan->id],
            ])
            ->all();

        if ($grup === []) {
            $this->addError('grup', 'Konfigurasi lama belum memiliki kegiatan aktif. Buat grup baru.');

            return;
        }

        $this->grup = $grup;
        $this->modeGrup = true;
    }

    public function simpanGrup(): void
    {
        $this->pastikanDraft();
        $blok = Blok::with('aturan_kegiatan_blok:id,blok_id')->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        $payload = $this->validate([
            'kehadiranAktif' => ['boolean'],
            'bobotKehadiran' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'kegiatan' => ['array'],
            'kegiatan.*.aktif' => ['boolean'],
            'kegiatan.*.bobot' => ['required', 'numeric', 'min:0', 'max:100'],
            'grup' => ['required', 'array', 'min:1'],
            'grup.*.id' => ['nullable', 'integer'],
            'grup.*.nama' => ['required', 'string', 'regex:/\S/u', 'max:255'],
            'grup.*.aktif' => ['boolean'],
            'grup.*.bobot' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:100'],
            'grup.*.aturan_ids' => ['required', 'array', 'min:1'],
            'grup.*.aturan_ids.*' => ['integer'],
        ]);

        $aturanValid = $blok->aturan_kegiatan_blok
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        $anggota = collect($payload['grup'])->flatMap(fn ($item) => array_map('intval', $item['aturan_ids']));
        $grupExistingIds = collect($payload['grup'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
        $grupValidIds = GrupDpnaBlok::query()
            ->where('blok_id', $blok->id)
            ->whereIn('id_grup_dpna_blok', $grupExistingIds)
            ->pluck('id_grup_dpna_blok')
            ->map(fn ($id) => (int) $id);

        if ($grupExistingIds->duplicates()->isNotEmpty() || $grupExistingIds->diff($grupValidIds)->isNotEmpty()) {
            $this->addError('grup', 'Data grup tidak valid atau bukan milik blok ini.');

            return;
        }

        if ($anggota->duplicates()->isNotEmpty() || $anggota->diff($aturanValid)->isNotEmpty()) {
            $this->addError('grup', 'Satu kegiatan hanya boleh masuk satu grup dan harus berasal dari blok ini.');

            return;
        }

        $anggotaIds = $anggota->unique();
        $sumberKegiatan = $blok->aturan_kegiatan_blok
            ->reject(fn ($aturan) => $anggotaIds->contains((int) $aturan->id))
            ->map(function ($aturan) {
                $config = $this->kegiatan[$aturan->id] ?? ['aktif' => false, 'bobot' => 0];

                return [
                    'aktif' => (bool) $config['aktif'],
                    'bobot' => (float) $config['bobot'],
                    'field' => "kegiatan.{$aturan->id}.bobot",
                ];
            });

        foreach ($sumberKegiatan as $sumber) {
            if ($sumber['aktif'] && $sumber['bobot'] <= 0) {
                $this->addError($sumber['field'], 'Sumber aktif wajib memiliki bobot lebih dari 0.');
            }
            if (! $sumber['aktif'] && $sumber['bobot'] != 0) {
                $this->addError($sumber['field'], 'Sumber nonaktif wajib berbobot 0.');
            }
        }

        $total = ($this->kehadiranAktif ? (int) round((float) $this->bobotKehadiran * 100) : 0)
            + $sumberKegiatan->where('aktif', true)->sum(fn ($item) => (int) round($item['bobot'] * 100))
            + collect($payload['grup'])->where('aktif', true)->sum(fn ($item) => (int) round((float) $item['bobot'] * 100));
        if ($total !== 10000) {
            $this->addError('totalBobot', 'Total bobot sumber aktif wajib tepat 100%. Saat ini '.number_format($total / 100, 2, ',', '.').'%.');

            return;
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($anggotaIds, $blok, $payload): void {
            $blok->update([
                'kehadiran_masuk_dpna' => $this->kehadiranAktif,
                'bobot_kehadiran_dpna' => $this->kehadiranAktif ? $this->bobotKehadiran : 0,
            ]);

            $grupTersimpan = [];
            $anggotaAktif = [];
            foreach ($payload['grup'] as $index => $baris) {
                $model = ! empty($baris['id'])
                    ? GrupDpnaBlok::where('blok_id', $blok->id)->findOrFail($baris['id'])
                    : new GrupDpnaBlok(['blok_id' => $blok->id]);
                $model->fill([
                    'nama' => trim($baris['nama']),
                    'bobot' => $baris['bobot'],
                    'urutan' => $index + 1,
                    'aktif' => (bool) $baris['aktif'],
                ])->save();
                $grupTersimpan[] = $model->id_grup_dpna_blok;

                foreach ($baris['aturan_ids'] as $aturanId) {
                    $anggotaModel = AnggotaGrupDpnaBlok::updateOrCreate([
                        'grup_dpna_blok_id' => $model->id_grup_dpna_blok,
                        'aturan_kegiatan_blok_id' => (int) $aturanId,
                    ], ['aktif' => true]);
                    $anggotaAktif[] = $anggotaModel->id_anggota_grup_dpna_blok;
                }
            }

            GrupDpnaBlok::where('blok_id', $blok->id)->whereNotIn('id_grup_dpna_blok', $grupTersimpan)->delete();
            AnggotaGrupDpnaBlok::whereHas('grup_dpna_blok', fn ($query) => $query->where('blok_id', $blok->id))
                ->whereNotIn('id_anggota_grup_dpna_blok', $anggotaAktif)
                ->update(['aktif' => false]);

            foreach ($blok->aturan_kegiatan_blok as $aturan) {
                $config = $this->kegiatan[$aturan->id] ?? ['aktif' => false, 'bobot' => 0];
                $digabung = $anggotaIds->contains((int) $aturan->id);
                AturanKegiatanBlok::whereKey($aturan->id)->update([
                    'nilai_masuk_dpna' => ! $digabung && (bool) $config['aktif'],
                    'bobot_nilai_dpna' => ! $digabung && $config['aktif'] ? $config['bobot'] : 0,
                ]);
            }
        });

        $this->muatGrup();
        session()->flash('success', 'Konfigurasi grup DPNA berhasil disimpan.');
    }

    private function pastikanDraft(): void
    {
        $blok = Blok::findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);
        abort_if($this->finalisasiAktif() !== null, 422, 'DPNA final harus dibuka kembali sebelum diubah.');
    }

    public function simpanBobot(): void
    {
        $this->pastikanDraft();
        $blok = Blok::with('aturan_kegiatan_blok:id,blok_id,perlu_penilaian')->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        if ($blok->grup_dpna_blok()->exists()) {
            $this->addError('grup', 'Konfigurasi grup tersedia. Ubah bobot melalui tabel grup DPNA.');

            return;
        }

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
                $config = $this->kegiatan[$aturan->id] ?? ['aktif' => false, 'bobot' => 0];
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

    public function sinkronisasiNilaiCbt(): void
    {
        $blok = Blok::findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);
        abort_if($this->finalisasiAktif() !== null, 422, 'DPNA final harus dibuka kembali sebelum nilai CBT disinkronkan.');

        try {
            $this->hasilSinkronisasiCbt = app(SinkronisasiNilaiCbtBlok::class)->jalankan($blok->id);
        } catch (Throwable $e) {
            report($e);
            $this->hasilSinkronisasiCbt = [
                'blok_diperiksa' => 0,
                'blok_diproses' => 0,
                'nilai_disinkronkan' => 0,
                'peserta_dilewati' => 0,
                'ujian_dilewati' => 0,
                'masalah' => [],
                'error' => ['Sinkronisasi gagal dijalankan. Periksa konfigurasi dan koneksi database CBT.'],
            ];
        }

        if ($this->hasilSinkronisasiCbt['error'] !== []) {
            session()->flash('failed', 'Sinkronisasi nilai CBT selesai dengan error.');

            return;
        }

        session()->flash('success', "{$this->hasilSinkronisasiCbt['nilai_disinkronkan']} nilai CBT berhasil disinkronkan.");
    }

    public function finalisasiAktif(): ?FinalisasiDpnaBlok
    {
        return FinalisasiDpnaBlok::query()
            ->where('blok_id', $this->blokId)
            ->where('status', 'final')
            ->with('snapshot_dpna_peserta')
            ->orderByDesc('versi')
            ->first();
    }

    public function finalisasiDitampilkan(): ?FinalisasiDpnaBlok
    {
        if ($this->riwayatTerpilihId === null) {
            return $this->finalisasiAktif();
        }

        return FinalisasiDpnaBlok::query()
            ->where('blok_id', $this->blokId)
            ->with('snapshot_dpna_peserta')
            ->findOrFail($this->riwayatTerpilihId);
    }

    public function tampilkanRiwayat(int $id): void
    {
        FinalisasiDpnaBlok::query()
            ->where('blok_id', $this->blokId)
            ->findOrFail($id);

        $this->riwayatTerpilihId = $id;
        $this->pesertaTerpilih = null;
    }

    public function tampilkanVersiAktif(): void
    {
        $this->riwayatTerpilihId = null;
        $this->pesertaTerpilih = null;
    }

    public function finalisasiDpna(): void
    {
        $blok = Blok::findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        app(FinalisasiDpnaBlokService::class)->finalisasi($blok, auth()->user());
        $this->riwayatTerpilihId = null;
        session()->flash('success', 'DPNA berhasil difinalisasi.');
    }

    public function bukaKembali(): void
    {
        $blok = Blok::findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);
        $this->validate(['alasanBuka' => ['required', 'string', 'min:5', 'max:1000']]);

        app(FinalisasiDpnaBlokService::class)->bukaKembali($blok, auth()->user(), $this->alasanBuka);
        $this->reset('alasanBuka', 'riwayatTerpilihId');
        session()->flash('success', 'DPNA dibuka kembali sebagai draft.');
    }

    public function riwayatFinalisasi()
    {
        return FinalisasiDpnaBlok::query()
            ->where('blok_id', $this->blokId)
            ->with(['difinalisasi_oleh:id,name', 'dibuka_oleh:id,name'])
            ->orderByDesc('versi')
            ->get();
    }

    public function data(): array
    {
        $blok = Blok::with(['prodi:id_prodi,nama', 'semester:id_semester,nama,tahun'])->findOrFail($this->blokId);
        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        return ['blok' => $blok] + app(PerhitunganDpnaBlok::class)->rekap($blok);
    }
}; ?>

<div>
    @php
        $data = $this->data();
        $blok = $data['blok'];
        $finalisasiAktif = $this->finalisasiAktif();
        $finalisasi = $this->finalisasiDitampilkan();
        $riwayatFinalisasi = $this->riwayatFinalisasi();
        $barisMatriks = $finalisasi
            ? $finalisasi->snapshot_dpna_peserta->map(fn ($snapshot) => [
                'peserta_id' => $snapshot->peserta_blok_id ?? -$snapshot->id_snapshot_dpna_peserta,
                'nim' => $snapshot->nim,
                'nama' => $snapshot->nama_mahasiswa,
                'nilai_akhir' => (float) $snapshot->nilai_akhir,
                'sumber' => $snapshot->sumber_json,
            ])
            : $data['baris']->map(fn ($row) => [
                'peserta_id' => $row['peserta']->id_peserta_blok,
                'nim' => $row['peserta']->mahasiswa->nim,
                'nama' => $row['peserta']->mahasiswa->nama,
                'nilai_akhir' => $row['nilai_akhir'],
                'sumber' => $row['sumber_detail'],
            ]);
        $barisTerpilih = $barisMatriks->firstWhere('peserta_id', $pesertaTerpilih);
        $sumberTerpilih = $barisTerpilih['sumber'] ?? null;
        $kolomMatriks = $finalisasi
            ? collect(data_get($finalisasi->konfigurasi_json, 'kegiatan', []))
                ->where('aktif', true)
                ->map(fn ($item) => $item + ['jenis' => 'kegiatan'])
                ->concat(collect(data_get($finalisasi->konfigurasi_json, 'grup', []))->map(fn ($item) => $item + ['jenis' => 'grup']))
                ->values()
            : $data['kegiatan']->where('nilai_masuk_dpna', true)->whereNotIn('id', $data['anggota_grup_ids'])
                ->map(fn ($item) => ['id' => $item->id, 'nama' => $item->jenis_kegiatan?->nama, 'jenis' => 'kegiatan'])
                ->concat($data['grup']->map(fn ($item) => ['id' => $item->id_grup_dpna_blok, 'nama' => $item->nama, 'jenis' => 'grup']))
                ->values();
        $kehadiranMatriksAktif = $finalisasi
            ? (bool) data_get($finalisasi->konfigurasi_json, 'kehadiran.aktif', false)
            : (bool) $blok->kehadiran_masuk_dpna;
    @endphp

    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0">DPNA Blok</h4>
        <ol class="breadcrumb m-0"><li class="breadcrumb-item"><a wire:navigate href="{{ route('dpna-blok.index') }}">DPNA Blok</a></li><li class="breadcrumb-item active">{{ $blok->nama }}</li></ol>
    </div>
    <livewire:alert />

    <div class="card">
        <div class="card-body d-flex flex-wrap justify-content-between gap-3">
            <div><div class="text-muted small">Blok</div><div class="fw-semibold">{{ $blok->nama }}</div></div>
            <div><div class="text-muted small">Prodi</div><div>{{ $blok->prodi->nama }}</div></div>
            <div><div class="text-muted small">Semester</div><div>{{ ucfirst($blok->semester->nama) }} {{ $blok->semester->tahun }}</div></div>
            <div><div class="text-muted small">Peserta</div><div>{{ $barisMatriks->count() }} mahasiswa</div></div>
            <div>
                <div class="text-muted small">Status DPNA</div>
                @if ($finalisasiAktif)
                    <span class="badge bg-success-subtle text-success">Final v{{ $finalisasiAktif->versi }}</span>
                @else
                    <span class="badge bg-warning-subtle text-warning">Draft</span>
                @endif
            </div>
        </div>
    </div>

    @if ($finalisasi)
        <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-start gap-3" role="alert">
            <div>
                <div class="fw-semibold"><i class="ri-lock-line"></i> Snapshot DPNA v{{ $finalisasi->versi }}</div>
                <div class="small">Difinalisasi {{ $finalisasi->difinalisasi_pada?->format('d/m/Y H:i') }}. Matriks memakai snapshot dan tidak berubah mengikuti koreksi data operasional.</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                @if ($riwayatTerpilihId !== null)
                    <button type="button" class="btn btn-light btn-sm" wire:click="tampilkanVersiAktif">
                        <i class="ri-arrow-go-back-line"></i> Kembali ke {{ $finalisasiAktif ? 'Versi Aktif' : 'Draft' }}
                    </button>
                @endif
                @if ($finalisasiAktif && $finalisasi->id_finalisasi_dpna_blok === $finalisasiAktif->id_finalisasi_dpna_blok)
                    @can('dpna-blok:finalisasi')
                        <form wire:submit="bukaKembali" class="d-flex flex-wrap gap-2 align-items-start">
                            <div>
                                <input type="text" class="form-control form-control-sm" wire:model="alasanBuka" placeholder="Alasan buka kembali" required minlength="5">
                                @error('alasanBuka')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm" wire:confirm="Buka kembali DPNA? Snapshot final lama tetap disimpan.">
                                <i class="ri-lock-unlock-line"></i> Buka Kembali
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    @else
        @error('finalisasi')<div class="alert alert-danger">{{ $message }}</div>@enderror
        @can('dpna-blok:finalisasi')
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn btn-success" wire:click="finalisasiDpna"
                    wire:confirm="Finalisasi DPNA? Nilai dan seluruh sumber akan disimpan sebagai snapshot.">
                    <i class="ri-shield-check-line"></i> Finalisasi DPNA
                </button>
            </div>
        @endcan
    @endif

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><h5 class="mb-1">Konfigurasi Sumber DPNA</h5><div class="text-muted small">Sumber aktif wajib berbobot lebih dari 0 dan totalnya tepat 100%.</div></div>
            @if (! $finalisasi)
                <button type="button" class="btn btn-soft-primary btn-sm" wire:click="tambahGrup" @disabled($grupDiedit !== null)>
                    <i class="ri-add-box-fill"></i> Buat Grup Gabungan
                </button>
            @endif
        </div>
        <div class="card-body">
            @error('totalBobot')<div class="alert alert-danger">{{ $message }}</div>@enderror
            @error('grup')<div class="alert alert-danger">{{ $message }}</div>@enderror

            @php
                $semuaKegiatan = $data['kegiatan'];
                $aturanTergabung = collect($grup)->flatMap(fn ($item) => $item['aturan_ids'] ?? [])->map(fn ($id) => (int) $id);
            @endphp
            <form wire:submit="{{ $modeGrup ? 'simpanGrup' : 'simpanBobot' }}">
                @if ($grupDiedit !== null && isset($grup[$grupDiedit]))
                    @php
                        $itemDiedit = $grup[$grupDiedit];
                        $aturanGrupLain = collect($grup)->except($grupDiedit)->flatMap(fn ($item) => $item['aturan_ids'] ?? [])->map(fn ($id) => (string) $id);
                    @endphp
                    <div class="border rounded p-3 mb-3" wire:key="editor-grup-{{ $itemDiedit['id'] ?? 'baru-'.$grupDiedit }}">
                        <h6 class="mb-1">Grup Bobot</h6>
                        <div class="text-muted small mb-3">Isi nama dan bobot, lalu pilih komponen yang digabung.</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label" for="nama-grup-{{ $grupDiedit }}">Nama Grup Bobot</label>
                                <input id="nama-grup-{{ $grupDiedit }}" type="text" class="form-control" wire:model="grup.{{ $grupDiedit }}.nama" placeholder="Contoh: Nilai Tutorial dan Praktikum">
                                @error("grup.$grupDiedit.nama")<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="bobot-grup-{{ $grupDiedit }}">Bobot (%)</label>
                                <input id="bobot-grup-{{ $grupDiedit }}" type="number" min="0.01" max="100" step="0.01" class="form-control" wire:model="grup.{{ $grupDiedit }}.bobot">
                                @error("grup.$grupDiedit.bobot")<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <fieldset>
                            <legend class="form-label mb-2">Komponen yang Digabung</legend>
                            <div class="row g-2">
                                @forelse ($semuaKegiatan as $aturan)
                                    @php
                                        $dipakaiGrupLain = $aturanGrupLain->contains((string) $aturan->id);
                                    @endphp
                                    <div class="col-md-6 col-xl-4">
                                        <label class="form-check border rounded p-2 h-100 {{ $dipakaiGrupLain ? 'bg-light text-muted' : '' }}">
                                            <input class="form-check-input ms-0 me-2" type="checkbox" value="{{ $aturan->id }}" wire:model.live="grup.{{ $grupDiedit }}.aturan_ids" @disabled($dipakaiGrupLain)>
                                            <span class="form-check-label">{{ $aturan->jenis_kegiatan?->nama }} @if ($aturan->jenis_kegiatan?->sumber_nilai === 'cbt')<span class="badge bg-info-subtle text-info">CBT</span>@endif</span>
                                        </label>
                                    </div>
                                @empty
                                    <div class="col-12 text-muted">Belum ada kegiatan.</div>
                                @endforelse
                            </div>
                            @error("grup.$grupDiedit.aturan_ids")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </fieldset>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="selesaiEditGrup">Selesai</button>
                            <button type="button" class="btn btn-soft-secondary btn-sm" wire:click="batalEditGrup">Batal</button>
                        </div>
                    </div>
                @endif

                    <div class="table-responsive">
                        <table class="table align-middle mb-3">
                            <thead><tr><th>Sumber</th><th class="text-center">Masuk DPNA</th><th style="width: 180px">Bobot (%)</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td><span class="fw-semibold">Kehadiran</span><div class="text-muted small">Persentase hadir dari seluruh pertemuan wajib presensi.</div></td>
                                    <td class="text-center"><input class="form-check-input" type="checkbox" wire:model.live="kehadiranAktif" aria-label="Aktifkan kehadiran" @disabled($finalisasi)></td>
                                    <td><input class="form-control" type="number" min="0" max="100" step="0.01" wire:model="bobotKehadiran" @disabled(!$kehadiranAktif || $finalisasi)>@error('bobotKehadiran')<div class="text-danger small">{{ $message }}</div>@enderror</td>
                                </tr>
                                @foreach ($semuaKegiatan as $aturan)
                                    @php
                                        $digabung = $aturanTergabung->contains((int) $aturan->id);
                                    @endphp
                                    <tr wire:key="bobot-{{ $aturan->id }}" class="{{ $digabung ? 'table-light text-muted' : '' }}">
                                        <td><span class="fw-semibold">{{ $aturan->jenis_kegiatan?->nama }}</span>@if ($digabung)<span class="badge bg-secondary-subtle text-secondary ms-1">Sudah digabung</span>@endif<div class="text-muted small">{{ $aturan->jenis_kegiatan?->sumber_nilai === 'cbt' ? 'Nilai kegiatan dari aplikasi CBT.' : 'Rata-rata nilai '.$aturan->materi_rinci_blok_count.' pertemuan per mahasiswa.' }}</div></td>
                                        <td class="text-center">
                                            @if ($digabung)
                                                <input class="form-check-input" type="checkbox" aria-label="{{ $aturan->jenis_kegiatan->nama }} sudah digabung" disabled>
                                            @else
                                                <input class="form-check-input" type="checkbox" wire:model.live="kegiatan.{{ $aturan->id }}.aktif" aria-label="Aktifkan {{ $aturan->jenis_kegiatan->nama }}" @disabled($finalisasi)>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($digabung)
                                                <input class="form-control" type="number" value="0" disabled>
                                            @else
                                                <input class="form-control" type="number" min="0" max="100" step="0.01" wire:model="kegiatan.{{ $aturan->id }}.bobot" @disabled(!($kegiatan[$aturan->id]['aktif'] ?? false) || $finalisasi)>
                                            @endif
                                            @error("kegiatan.{$aturan->id}.bobot")<div class="text-danger small">{{ $message }}</div>@enderror
                                        </td>
                                    </tr>
                                @endforeach
                                @foreach ($grup as $index => $item)
                                    @php
                                        $anggotaGrup = $data['kegiatan']->whereIn('id', array_map('intval', $item['aturan_ids'] ?? []));
                                    @endphp
                                    <tr wire:key="sumber-grup-{{ $item['id'] ?? 'baru-'.$index }}">
                                        <td>
                                            <span class="fw-semibold">{{ $item['nama'] ?: 'Grup Bobot Baru' }}</span>
                                            <div class="text-muted small">Gabungan: @forelse ($anggotaGrup as $aturan){{ $aturan->jenis_kegiatan?->nama }}{{ ! $loop->last ? ', ' : '' }}@empty belum ada komponen @endforelse</div>
                                            @if (! $finalisasi)<button type="button" class="btn btn-link btn-sm p-0 me-2" wire:click="editGrup({{ $index }})">Atur</button><button type="button" class="btn btn-link btn-sm text-danger p-0" wire:click="hapusGrup({{ $index }})">Hapus</button>@endif
                                        </td>
                                        <td class="text-center"><input class="form-check-input" type="checkbox" wire:model.live="grup.{{ $index }}.aktif" aria-label="Aktifkan {{ $item['nama'] ?: 'grup bobot' }}" @disabled($finalisasi)></td>
                                        <td><input class="form-control" type="number" min="0.01" max="100" step="0.01" wire:model="grup.{{ $index }}.bobot" @disabled(!($item['aktif'] ?? false) || $finalisasi)>@error("grup.$index.bobot")<div class="text-danger small">{{ $message }}</div>@enderror</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (! $finalisasi)
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i>Simpan Bobot</button>
                            <button type="button" class="btn btn-soft-secondary" wire:click="batalPerubahan">Batal Perubahan</button>
                        </div>
                    @endif
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-1">Matriks DPNA</h5>
                <div class="text-muted small">Klik mahasiswa untuk melihat nilai sumber. {{ $finalisasi ? 'Nilai berasal dari snapshot final.' : 'Nilai akhir hanya tampil jika semua sumber aktif lengkap.' }}</div>
            </div>
            @if (! $finalisasi && $blok->status === 'aktif')
                <button type="button" class="btn btn-soft-primary btn-sm" wire:click="sinkronisasiNilaiCbt"
                    wire:confirm="Sinkronkan nilai CBT untuk blok ini? Nilai snapshot lokal yang valid akan diperbarui."
                    wire:loading.attr="disabled" wire:target="sinkronisasiNilaiCbt">
                    <span wire:loading.remove wire:target="sinkronisasiNilaiCbt"><i class="ri-refresh-line"></i> Sinkronisasi Nilai CBT</span>
                    <span wire:loading wire:target="sinkronisasiNilaiCbt"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Menyinkronkan...</span>
                </button>
            @endif
        </div>
        <div class="card-body p-0">
            @if ($hasilSinkronisasiCbt !== [])
                <div class="alert {{ $hasilSinkronisasiCbt['error'] === [] ? 'alert-info' : 'alert-danger' }} rounded-0 border-start-0 border-end-0 mb-0" role="alert">
                    <div class="fw-semibold">Hasil Sinkronisasi CBT</div>
                    <div>
                        {{ $hasilSinkronisasiCbt['nilai_disinkronkan'] }} nilai disinkronkan,
                        {{ $hasilSinkronisasiCbt['peserta_dilewati'] }} peserta dilewati,
                        dan {{ $hasilSinkronisasiCbt['ujian_dilewati'] }} ujian dilewati.
                    </div>
                    @if ($hasilSinkronisasiCbt['masalah'] !== [] || $hasilSinkronisasiCbt['error'] !== [])
                        <ul class="mb-0 mt-2">
                            @foreach ([...$hasilSinkronisasiCbt['masalah'], ...$hasilSinkronisasiCbt['error']] as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">No</th><th>NIM</th><th>Mahasiswa</th>
                            @if ($kehadiranMatriksAktif)<th class="text-center">Kehadiran</th>@endif
                            @foreach ($kolomMatriks as $kolom)<th class="text-center">{{ $kolom['nama'] }}</th>@endforeach
                            <th class="text-center">Nilai Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($barisMatriks as $index => $row)
                        @php
                            $sumberBaris = $row['sumber'];
                        @endphp
                        <tr role="button" wire:click="pilihPeserta({{ $row['peserta_id'] }})" wire:key="peserta-{{ $row['peserta_id'] }}">
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $row['nim'] }}</td>
                            <td class="fw-semibold">{{ $row['nama'] }}</td>
                            @if ($kehadiranMatriksAktif)
                                <td class="text-center">
                                    @php
                                        $nilaiKehadiran = data_get($sumberBaris, 'kehadiran.nilai');
                                    @endphp
                                    {{ $nilaiKehadiran === null ? 'Belum Lengkap' : number_format((float) $nilaiKehadiran, 2, ',', '.') }}
                                </td>
                            @endif
                            @foreach ($kolomMatriks as $kolom)
                                @php
                                    $sumberKolom = collect($sumberBaris[$kolom['jenis']] ?? [])->firstWhere('id', $kolom['id']);
                                    $nilaiKolom = $sumberKolom['nilai'] ?? null;
                                @endphp
                                <td class="text-center">{{ $nilaiKolom === null ? 'Belum Lengkap' : number_format((float) $nilaiKolom, 2, ',', '.') }}</td>
                            @endforeach
                            <td class="text-center fw-bold">{{ $row['nilai_akhir'] === null ? 'Belum Lengkap' : number_format((float) $row['nilai_akhir'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-4" colspan="{{ 4 + ($kehadiranMatriksAktif ? 1 : 0) + $kolomMatriks->count() }}">Belum ada peserta aktif.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($barisTerpilih && $sumberTerpilih)
        @php
            $nilaiAkhirTerpilih = $barisTerpilih['nilai_akhir'];
        @endphp
        <div class="card border-primary" id="detail-mahasiswa">
            <div class="card-header d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h5 class="mb-0">Detail {{ $barisTerpilih['nama'] }}</h5>
                    <span class="text-muted">{{ $barisTerpilih['nim'] }}</span>
                </div>
                <button class="btn-close" type="button" wire:click="pilihPeserta({{ $pesertaTerpilih }})" aria-label="Tutup"></button>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Kehadiran</div>
                            <h5>{{ data_get($sumberTerpilih, 'kehadiran.nilai') === null ? 'Belum Lengkap' : number_format((float) data_get($sumberTerpilih, 'kehadiran.nilai'), 2, ',', '.').'%' }}</h5>
                            <div class="small">{{ data_get($sumberTerpilih, 'kehadiran.hadir', 0) }} hadir dari {{ data_get($sumberTerpilih, 'kehadiran.wajib', 0) }} pertemuan wajib</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border border-primary rounded p-3 h-100">
                            <div class="text-muted small">Nilai Akhir Blok</div>
                            <h4 class="text-primary mb-0">{{ $nilaiAkhirTerpilih === null ? 'Belum Lengkap' : number_format((float) $nilaiAkhirTerpilih, 2, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>

                @php
                    $kegiatanMandiriIds = $kolomMatriks->where('jenis', 'kegiatan')->pluck('id');
                    $kegiatanMandiri = collect($sumberTerpilih['kegiatan'] ?? [])->whereIn('id', $kegiatanMandiriIds);
                @endphp
                @foreach ($kegiatanMandiri as $detailKegiatan)
                    @include('pages.dpna-blok.partials.nilai-sumber', ['detailKegiatan' => $detailKegiatan])
                @endforeach
                @foreach ($sumberTerpilih['grup'] ?? [] as $detailGrup)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                <div><h6 class="mb-0">{{ $detailGrup['nama'] }}</h6><div class="text-muted small">Rata-rata sama {{ count($detailGrup['anggota'] ?? []) }} kegiatan</div></div>
                                <div class="text-end"><span class="badge bg-light text-dark border">Bobot {{ number_format((float) $detailGrup['bobot'], 2, ',', '.') }}%</span><div class="fw-semibold text-primary mt-1">{{ $detailGrup['nilai'] === null ? 'Belum Lengkap' : number_format((float) $detailGrup['nilai'], 2, ',', '.') }}</div></div>
                            </div>
                            @foreach ($detailGrup['anggota'] ?? [] as $detailKegiatan)
                                @include('pages.dpna-blok.partials.nilai-sumber', ['detailKegiatan' => $detailKegiatan])
                            @endforeach
                        </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($riwayatFinalisasi->isNotEmpty())
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Riwayat Finalisasi</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th>Versi</th><th>Status</th><th>Difinalisasi</th><th>Dibuka Kembali</th><th>Alasan</th></tr></thead>
                        <tbody>
                            @foreach ($riwayatFinalisasi as $riwayat)
                                <tr>
                                    <td><button type="button" class="btn btn-link btn-sm p-0" wire:click="tampilkanRiwayat({{ $riwayat->id_finalisasi_dpna_blok }})">v{{ $riwayat->versi }}</button></td>
                                    <td><span class="badge {{ $riwayat->status === 'final' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ $riwayat->status === 'final' ? 'Final' : 'Dibuka kembali' }}</span></td>
                                    <td>{{ $riwayat->difinalisasi_pada?->format('d/m/Y H:i') }}<div class="text-muted small">{{ $riwayat->difinalisasi_oleh?->name }}</div></td>
                                    <td>{{ $riwayat->dibuka_pada?->format('d/m/Y H:i') ?: '-' }}@if($riwayat->dibuka_oleh)<div class="text-muted small">{{ $riwayat->dibuka_oleh->name }}</div>@endif</td>
                                    <td>{{ $riwayat->alasan_buka ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>