<?php

use App\Exports\NilaiPertemuanTemplateExport;
use App\Imports\NilaiPertemuanImport;
use App\Models\KomponenPenilaianBlok;
use App\Models\NilaiPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\PesertaBlok;
use App\Models\RekapNilaiPertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pengisian nilai satu pertemuan: matriks mahasiswa x komponen penilaian.
 *
 * Peserta sesi berasal dari `anggota_kelompok_blok` pada kelompok pertemuan tersebut,
 * sama seperti presensi. Komponen berasal dari `komponen_penilaian_blok` milik
 * `aturan_kegiatan_blok` pertemuan itu, sehingga batas nilai terkunci pada blok ini.
 *
 * Berbeda dari presensi dan jurnal, penilaian TIDAK dikunci oleh `divalidasi_pada`;
 * lihat `AksesPertemuanBlok::bolehIsiNilai()`.
 *
 * Baris nilai hanya ada bila terisi. Input yang dikosongkan menghapus barisnya, jadi
 * "ada baris" berarti "sudah dinilai" dan badge kelengkapan bisa dihitung dengan count.
 *
 * Selain diisi manual, nilai bisa diunduh sebagai template lalu diimport kembali. Karena
 * komponen ini dipakai apa adanya oleh halaman Pertemuan Saya (dosen) dan tab Monitoring
 * (pengelola), kedua peran mendapat template dan import yang sama; yang membedakan hanya
 * `AksesPertemuanBlok::bolehIsiNilai()`.
 */
new class extends Component
{
    use WithFileUploads;

    public int $pertemuan_blok_id;

    /**
     * Berkas template nilai yang diunggah. Tidak pernah dipakai sebagai sumber daftar
     * peserta atau komponen — keduanya selalu dibaca ulang dari database.
     */
    public $importFile;

    /**
     * Nilai per peserta per komponen: $nilai[peserta_blok_id][komponen_penilaian_blok_id].
     *
     * @var array<int|string, array<int|string, string>>
     */
    public array $nilai = [];

    private ?Collection $anggotaCache = null;

    private ?Collection $komponenCache = null;

    private ?PertemuanBlok $pertemuanCache = null;

    public function mount(int $pertemuan_blok_id): void
    {
        $this->pertemuan_blok_id = $pertemuan_blok_id;

        abort_unless(
            AksesPertemuanBlok::bolehKelolaPertemuan(auth()->user(), $this->pertemuan_blok_id),
            403
        );

        $this->muatNilai();
    }

    /**
     * Prefill dalam satu query, lalu dipetakan ke state. Baris yang belum ada dibiarkan
     * string kosong supaya "belum dinilai" bisa dibedakan dari nilai 0.
     */
    private function muatNilai(): void
    {
        $tersimpan = NilaiPertemuanBlok::query()
            ->where('pertemuan_blok_id', $this->pertemuan_blok_id)
            ->get(['peserta_blok_id', 'komponen_penilaian_blok_id', 'nilai']);

        $peta = [];

        foreach ($tersimpan as $baris) {
            $peta[$baris->peserta_blok_id][$baris->komponen_penilaian_blok_id] = (string) $baris->nilai;
        }

        foreach ($this->anggota() as $peserta) {
            $pesertaId = $peserta->id_peserta_blok;

            foreach ($this->komponen() as $komponen) {
                $this->nilai[$pesertaId][$komponen->id] = $peta[$pesertaId][$komponen->id] ?? '';
            }
        }
    }

    /**
     * Di-cache per request karena dipakai `anggota()`, `komponen()`, dan `render()`.
     */
    private function pertemuan(): PertemuanBlok
    {
        if ($this->pertemuanCache !== null) {
            return $this->pertemuanCache;
        }

        return $this->pertemuanCache = PertemuanBlok::query()
            ->with('aturan_kegiatan_blok:id,perlu_penilaian')
            ->findOrFail($this->pertemuan_blok_id);
    }

    /**
     * Daftar anggota kelompok pertemuan ini. Selalu dibaca dari database, tidak dari
     * state komponen, karena kunci array `nilai` bisa diubah dari sisi klien.
     */
    public function anggota(): Collection
    {
        if ($this->anggotaCache !== null) {
            return $this->anggotaCache;
        }

        $pertemuan = $this->pertemuan();

        return $this->anggotaCache = PesertaBlok::query()
            ->select('peserta_blok.*')
            ->join('anggota_kelompok_blok', 'anggota_kelompok_blok.peserta_blok_id', '=', 'peserta_blok.id_peserta_blok')
            ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
            ->where('anggota_kelompok_blok.kelompok_blok_id', $pertemuan->kelompok_blok_id)
            ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
            ->with('mahasiswa:id_mahasiswa,nim,nama')
            ->orderBy('mahasiswa.nama')
            ->get();
    }

    /**
     * Rubrik milik kegiatan pertemuan ini. Dibaca dari database pada setiap request,
     * sama alasannya dengan `anggota()`.
     */
    public function komponen(): Collection
    {
        if ($this->komponenCache !== null) {
            return $this->komponenCache;
        }

        $pertemuan = $this->pertemuan();

        return $this->komponenCache = KomponenPenilaianBlok::query()
            ->where('aturan_kegiatan_blok_id', $pertemuan->aturan_kegiatan_blok_id)
            ->with('komponen_penilaian:id,kode,nama')
            ->orderBy('urutan')
            ->get();
    }

    public function bolehIsi(): bool
    {
        return AksesPertemuanBlok::bolehIsiNilai(auth()->user(), $this->pertemuan_blok_id);
    }

    public function simpan(): void
    {
        abort_unless($this->bolehIsi(), 403);

        $anggota = $this->anggota();
        $komponen = $this->komponen();

        if (! $this->siapDinilai($anggota, $komponen)) {
            return;
        }

        [$rules, $messages] = $this->aturanValidasi($anggota, $komponen);

        $this->validate($rules, $messages);

        $this->tulisNilai($anggota, $komponen);

        $this->muatNilai();

        $this->dispatch('nilai-pertemuan-tersimpan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Nilai berhasil disimpan.',
        ]);
    }

    /**
     * Penjaga bersama `simpan()`, `unduhTemplate()`, dan `importNilai()`. Tanpa rubrik atau
     * tanpa anggota aktif tidak ada yang bisa dinilai maupun ditemplatekan.
     */
    private function siapDinilai(Collection $anggota, Collection $komponen): bool
    {
        if ($komponen->isEmpty()) {
            $this->dispatch('notify', message: [
                'status' => 'failed',
                'message' => 'Rubrik penilaian kegiatan ini belum disusun.',
            ]);

            return false;
        }

        if ($anggota->isEmpty()) {
            $this->dispatch('notify', message: [
                'status' => 'failed',
                'message' => 'Kelompok pertemuan ini belum punya anggota aktif.',
            ]);

            return false;
        }

        return true;
    }

    /**
     * Satu-satunya jalur tulis nilai, dipakai `simpan()` dan `importNilai()`. Disatukan supaya
     * perhitungan total dan `rekap_nilai_pertemuan_blok` tidak punya dua implementasi yang
     * bisa melenceng.
     *
     * Iterasi atas daftar dari database, bukan atas kunci `$this->nilai`, supaya peserta atau
     * komponen dari blok lain tidak bisa disusupkan dari klien.
     *
     * `$hanyaPeserta` membatasi penulisan ke sebagian peserta dan dipakai import, supaya
     * peserta yang barisnya tidak ada di berkas tidak tersentuh. Null berarti seluruh anggota,
     * seperti simpan manual yang selalu mengirim seluruh matriks.
     *
     * @param  array<int, int>|null  $hanyaPeserta  daftar peserta_blok_id
     */
    private function tulisNilai(Collection $anggota, Collection $komponen, ?array $hanyaPeserta = null): void
    {
        DB::transaction(function () use ($anggota, $komponen, $hanyaPeserta) {
            $nilaiMaks = (float) $komponen->sum(fn ($item) => (float) $item->nilai_maks);

            foreach ($anggota as $peserta) {
                $pesertaId = (int) $peserta->id_peserta_blok;

                if ($hanyaPeserta !== null && ! in_array($pesertaId, $hanyaPeserta, true)) {
                    continue;
                }

                $total = 0;

                foreach ($komponen as $item) {
                    $kunci = [
                        'pertemuan_blok_id' => $this->pertemuan_blok_id,
                        'peserta_blok_id' => $pesertaId,
                        'komponen_penilaian_blok_id' => $item->id,
                    ];

                    $isian = trim((string) ($this->nilai[$pesertaId][$item->id] ?? ''));

                    if ($isian === '') {
                        NilaiPertemuanBlok::where($kunci)->delete();

                        continue;
                    }

                    $nilai = (float) $isian;
                    $total += $nilai;

                    NilaiPertemuanBlok::updateOrCreate($kunci, [
                        'nilai' => $nilai,
                        'dinilai_oleh_user_id' => auth()->id(),
                    ]);
                }

                RekapNilaiPertemuanBlok::updateOrCreate([
                    'pertemuan_blok_id' => $this->pertemuan_blok_id,
                    'peserta_blok_id' => $pesertaId,
                ], [
                    'total' => $total,
                    'nilai_akhir' => RekapNilaiPertemuanBlok::hitungNilaiAkhir($total, $nilaiMaks),
                ]);
            }
        });
    }

    /**
     * Template berisi seluruh anggota kelompok beserta nilai yang sudah tersimpan, jadi berkas
     * yang sama dipakai untuk pengisian pertama maupun koreksi.
     */
    public function unduhTemplate()
    {
        abort_unless($this->bolehIsi(), 403);

        $anggota = $this->anggota();
        $komponen = $this->komponen();

        if (! $this->siapDinilai($anggota, $komponen)) {
            return null;
        }

        $pertemuan = $this->pertemuan();

        $nama = Str::limit(Str::slug(implode('-', array_filter([
            'template-nilai',
            $pertemuan->kelompok_blok?->kode,
            $pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik,
        ]))), 80, '');

        return Excel::download(
            new NilaiPertemuanTemplateExport($anggota, $komponen, $this->nilai),
            ($nama !== '' ? $nama : 'template-nilai-pertemuan').'.xlsx',
        );
    }

    /**
     * Import hanya menimpa peserta yang barisnya ada di berkas; nilai peserta lain dibiarkan,
     * supaya berkas yang dipangkas tidak menghapus pekerjaan yang sudah ada. Sebaliknya sel
     * yang dikosongkan tetap menghapus nilai komponen itu, sama seperti isian di layar.
     */
    public function importNilai(): void
    {
        abort_unless($this->bolehIsi(), 403);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'importFile.required' => 'File import wajib dipilih.',
            'importFile.file' => 'Berkas import tidak valid.',
            'importFile.mimes' => 'File import harus berformat xlsx, xls, atau csv.',
            'importFile.max' => 'Ukuran file import maksimal 5 MB.',
        ]);

        $anggota = $this->anggota();
        $komponen = $this->komponen();

        if (! $this->siapDinilai($anggota, $komponen)) {
            return;
        }

        $import = new NilaiPertemuanImport($anggota, $komponen);

        Excel::import($import, $this->importFile);

        $terbaca = $import->nilai();

        // Isian hasil pembacaan sudah divalidasi terhadap batas di `komponen_penilaian_blok`
        // oleh kelas import, jadi tidak divalidasi ulang lewat `aturanValidasi()`: aturan itu
        // mencakup seluruh matriks, sehingga sel peserta lain yang belum terisi ikut diperiksa.
        foreach ($terbaca as $pesertaId => $perKomponen) {
            foreach ($perKomponen as $komponenId => $isian) {
                $this->nilai[$pesertaId][$komponenId] = $isian;
            }
        }

        $this->tulisNilai($anggota, $komponen, array_keys($terbaca));

        $this->reset('importFile');
        $this->muatNilai();

        $this->dispatch('import-nilai-berhasil', modalId: 'modal-import-nilai-'.$this->pertemuan_blok_id);
        $this->dispatch('nilai-pertemuan-tersimpan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Nilai '.count($terbaca).' mahasiswa berhasil diimport.',
        ]);
    }

    /**
     * Batas nilai berbeda per komponen, jadi rule dibangun per sel matriks. Batas selalu
     * dibaca dari `komponen_penilaian_blok`, bukan dari atribut input di HTML.
     *
     * @return array{0: array<string, array<int, string>>, 1: array<string, string>}
     */
    private function aturanValidasi(Collection $anggota, Collection $komponen): array
    {
        $rules = [];
        $messages = [];

        foreach ($anggota as $peserta) {
            foreach ($komponen as $item) {
                $kunci = 'nilai.'.$peserta->id_peserta_blok.'.'.$item->id;
                $nama = $item->komponen_penilaian?->nama ?: 'komponen';

                $rules[$kunci] = [
                    'nullable',
                    'numeric',
                    'min:'.$item->nilai_min,
                    'max:'.$item->nilai_maks,
                ];

                $messages[$kunci.'.numeric'] = 'Nilai '.$nama.' harus berupa angka.';
                $messages[$kunci.'.min'] = 'Nilai '.$nama.' minimal '.$item->nilai_min.'.';
                $messages[$kunci.'.max'] = 'Nilai '.$nama.' maksimal '.$item->nilai_maks.'.';
            }
        }

        return [$rules, $messages];
    }

    /**
     * Ringkasan kelengkapan dihitung dari state yang sudah dimuat, tanpa query tambahan.
     *
     * @return array<string, int|float|null>
     */
    public function rekap(): array
    {
        $anggota = $this->anggota();
        $komponen = $this->komponen();

        $selTotal = $anggota->count() * $komponen->count();
        $selTerisi = 0;

        foreach ($anggota as $peserta) {
            foreach ($komponen as $item) {
                if (trim((string) ($this->nilai[$peserta->id_peserta_blok][$item->id] ?? '')) !== '') {
                    $selTerisi++;
                }
            }
        }

        return [
            'sel_total' => $selTotal,
            'sel_terisi' => $selTerisi,
            'nilai_maks_total' => (float) $komponen->sum(fn ($item) => (float) $item->nilai_maks),
        ];
    }

    /**
     * Total nilai satu mahasiswa pada pertemuan ini. Null bila belum ada isian sama sekali.
     */
    public function totalPeserta(int $pesertaId): ?float
    {
        $total = null;

        foreach ($this->komponen() as $item) {
            $isian = trim((string) ($this->nilai[$pesertaId][$item->id] ?? ''));

            if ($isian === '') {
                continue;
            }

            $total = ($total ?? 0) + (float) $isian;
        }

        return $total;
    }

    public function nilaiAkhirPeserta(int $pesertaId): ?float
    {
        $total = $this->totalPeserta($pesertaId);

        return $total === null
            ? null
            : RekapNilaiPertemuanBlok::hitungNilaiAkhir($total, $this->rekap()['nilai_maks_total']);
    }

    public function render()
    {
        $pertemuan = $this->pertemuan();

        return $this->view([
            'anggota' => $this->anggota(),
            'komponen' => $this->komponen(),
            'rekap' => $this->rekap(),
            'bolehIsi' => $this->bolehIsi(),
            'perluPenilaian' => (bool) ($pertemuan->aturan_kegiatan_blok?->perlu_penilaian ?? false),
        ]);
    }
};
?>

<div class="nilai-pertemuan">
    <style>
        .nilai-pertemuan-matrix table {
            table-layout: fixed;
        }

        .nilai-pertemuan-matrix .nilai-no {
            width: 2.5rem;
        }

        .nilai-pertemuan-matrix .nilai-mahasiswa {
            width: 11.25rem;
        }

        .nilai-pertemuan-matrix .nilai-total {
            width: 7rem;
        }

        .nilai-pertemuan-matrix .nilai-komponen,
        .nilai-pertemuan-matrix .nilai-komponen-cell {
            overflow-wrap: anywhere;
        }

        .nilai-pertemuan-matrix thead .nilai-komponen {
            white-space: normal;
            line-height: 1.35;
        }

        .nilai-pertemuan-matrix .form-control {
            min-width: 0;
        }

        .nilai-pertemuan-label {
            display: none;
        }

        @media (max-width: 1199.98px) {
            .nilai-pertemuan-matrix {
                overflow: visible;
            }

            .nilai-pertemuan-matrix table,
            .nilai-pertemuan-matrix tbody {
                display: block;
            }

            .nilai-pertemuan-matrix thead {
                display: none;
            }

            .nilai-pertemuan-matrix tbody {
                display: grid;
                gap: .75rem;
            }

            .nilai-pertemuan-matrix tbody tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .75rem;
                padding: 1rem;
                border: 1px solid var(--line, #dde7e2);
                border-radius: .5rem;
                background: var(--surface, #fff);
            }

            .nilai-pertemuan-matrix tbody td {
                display: block;
                width: auto !important;
                padding: 0;
                border: 0;
            }

            .nilai-pertemuan-matrix tbody .nilai-no {
                display: none;
            }

            .nilai-pertemuan-matrix tbody .nilai-mahasiswa {
                grid-column: 1 / -1;
                padding-bottom: .75rem;
                border-bottom: 1px solid var(--line, #dde7e2);
            }

            .nilai-pertemuan-matrix tbody .nilai-total {
                grid-column: 1 / -1;
                padding-top: .75rem;
                border-top: 1px solid var(--line, #dde7e2);
            }

            .nilai-pertemuan-label {
                display: block;
                margin-bottom: .35rem;
                color: var(--muted, #6c757d);
                font-size: .75rem;
                font-weight: 600;
            }
        }

        @media (max-width: 575.98px) {
            .nilai-pertemuan-matrix tbody tr {
                grid-template-columns: minmax(0, 1fr);
                padding: .875rem;
            }

            .nilai-pertemuan .nilai-pertemuan-action,
            .nilai-pertemuan .nilai-pertemuan-submit,
            .nilai-pertemuan .nilai-pertemuan-submit .btn {
                width: 100%;
            }
        }
    </style>

    <x-full-page-loading message="Memproses operasional blok..." />
    @if (! $perluPenilaian)
        <div class="alert alert-warning py-2 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-information-line"></i>
            Jenis kegiatan ini ditandai <span class="fw-semibold">tidak perlu penilaian</span> pada susunan blok.
            Nilai yang tersimpan tetap ditampilkan, tapi sebaiknya nyalakan penanda penilaian bila memang dinilai.
        </div>
    @endif

    @if ($komponen->isEmpty())
        <div class="alert alert-warning py-2 mb-0 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-graduation-cap-line"></i>
            Rubrik penilaian kegiatan ini belum disusun. Pengelola perlu mengisi komponen penilaian pada
            tab <span class="fw-semibold">Penilaian</span> di form Blok terlebih dahulu.
        </div>
    @elseif ($anggota->isEmpty())
        <div class="alert alert-warning py-2 mb-0 alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-group-line"></i>
            Kelompok pertemuan ini belum punya anggota aktif. Isi anggota kelompok terlebih dahulu.
        </div>
    @else
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="d-flex flex-wrap gap-1">
                @if ($rekap['sel_terisi'] === 0)
                    <span class="badge bg-warning-subtle text-warning">Belum dinilai</span>
                @elseif ($rekap['sel_terisi'] < $rekap['sel_total'])
                    <span class="badge bg-warning-subtle text-warning">
                        Terisi {{ $rekap['sel_terisi'] }} dari {{ $rekap['sel_total'] }} isian
                    </span>
                @else
                    <span class="badge bg-success-subtle text-success">Lengkap</span>
                @endif
                <span class="badge bg-light text-dark border">{{ $anggota->count() }} mahasiswa</span>
                <span class="badge bg-light text-dark border">{{ $komponen->count() }} komponen</span>
                <span class="badge bg-info-subtle text-info">Nilai maksimum {{ $rekap['nilai_maks_total'] }}</span>
            </div>

            {{-- Template dan import ikut aturan yang sama dengan pengisian manual, yaitu
                 `bolehIsi`, sehingga dosen pengampu dan pengelola mendapat perlakuan sama. --}}
            @if ($bolehIsi)
                <button type="button" class="btn btn-primary btn-sm text-nowrap nilai-pertemuan-action"
                    x-on:click="$dispatch('buka-import-nilai', { modalId: 'modal-import-nilai-{{ $pertemuan_blok_id }}' })">
                    <i class="ri-upload-2-line"></i> Import
                </button>
            @endif
        </div>

        <form wire:submit="simpan">
            <div class="table-responsive nilai-pertemuan-matrix">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="nilai-no">#</th>
                            <th class="nilai-mahasiswa text-center">Mahasiswa</th>
                            @foreach ($komponen as $item)
                                <th class="nilai-komponen text-center">
                                    <div>{{ $item->komponen_penilaian?->nama ?: $item->komponen_penilaian?->kode }}</div>
                                    <div class="text-muted fw-normal small">{{ $item->nilai_min }} - {{ $item->nilai_maks }}</div>
                                </th>
                            @endforeach
                            <th class="nilai-total text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anggota as $index => $peserta)
                            @php($pesertaId = $peserta->id_peserta_blok)
                            @php($total = $this->totalPeserta($pesertaId))
                            @php($nilaiAkhir = $this->nilaiAkhirPeserta($pesertaId))
                            <tr wire:key="nilai-{{ $pesertaId }}">
                                <td class="text-muted nilai-no">{{ $index + 1 }}</td>
                                <td class="nilai-mahasiswa">
                                    <div class="small fw-semibold">{{ $peserta->mahasiswa?->nama }}</div>
                                    <div class="text-muted small">{{ $peserta->mahasiswa?->nim }}</div>
                                </td>
                                @foreach ($komponen as $item)
                                    @php($namaKomponen = $item->komponen_penilaian?->nama ?: $item->komponen_penilaian?->kode)
                                    <td class="nilai-komponen-cell">
                                        <label class="nilai-pertemuan-label"
                                            for="nilai-{{ $pertemuan_blok_id }}-{{ $pesertaId }}-{{ $item->id }}">
                                            {{ $namaKomponen }} ({{ $item->nilai_min }}–{{ $item->nilai_maks }})
                                        </label>
                                        @if ($bolehIsi)
                                            <input id="nilai-{{ $pertemuan_blok_id }}-{{ $pesertaId }}-{{ $item->id }}"
                                                type="number" step="0.01"
                                                min="{{ $item->nilai_min }}" max="{{ $item->nilai_maks }}"
                                                aria-label="Nilai {{ $namaKomponen }} untuk {{ $peserta->mahasiswa?->nama }}"
                                                class="form-control form-control-sm"
                                                placeholder="-"
                                                wire:model.blur="nilai.{{ $pesertaId }}.{{ $item->id }}">
                                            @error('nilai.'.$pesertaId.'.'.$item->id)
                                                <div class="small text-danger mt-1">{{ $message }}</div>
                                            @enderror
                                        @else
                                            @php($isian = trim((string) ($nilai[$pesertaId][$item->id] ?? '')))
                                            <span class="{{ $isian === '' ? 'text-muted' : 'fw-semibold' }}">
                                                {{ $isian === '' ? '-' : $isian }}
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="nilai-total">
                                    <span class="nilai-pertemuan-label">Total</span>
                                    @if ($total === null)
                                        <span class="text-muted small">-</span>
                                    @else
                                        <div>
                                            <span class="badge bg-primary-subtle text-primary">
                                                {{ $total }} / {{ $rekap['nilai_maks_total'] }}
                                            </span>
                                        </div>
                                        <div class="small fw-semibold mt-1">
                                            {{ number_format($nilaiAkhir, 2, ',', '.') }} / 100
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-muted small mt-2">
                Kosongkan isian untuk membatalkan penilaian komponen tersebut. Nilai boleh diperbaiki kapan saja,
                termasuk setelah pertemuan divalidasi.
                @if ($bolehIsi)
                    <br>
                    Lewat <span class="fw-semibold">Template Import</span>, berkas yang diunduh sudah memuat seluruh mahasiswa
                    beserta nilai yang tersimpan, jadi bisa dipakai untuk mengisi maupun mengoreksi. Saat diimport, sel
                    yang dikosongkan <span class="fw-semibold">menghapus</span> nilai komponen itu, sedangkan baris NIM
                    yang dihapus dari berkas tidak tersentuh. Bila ada satu baris yang ditolak, seluruh berkas dibatalkan.
                @endif
            </div>

            @if ($bolehIsi)
                <div class="mt-3 nilai-pertemuan-submit">
                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="simpan">
                        <i class="ri-save-line"></i> SIMPAN NILAI
                    </button>
                </div>
            @endif
        </form>

        @if ($bolehIsi)
            @teleport('body')
                {{-- ponytail: Bootstrap tidak mendukung modal bertumpuk. Simpan body style modal induk;
                     ganti modal import dengan panel non-modal bila kelak perlu focus trap lintas dua dialog. --}}
                <div wire:ignore.self class="modal fade" id="modal-import-nilai-{{ $pertemuan_blok_id }}" tabindex="-1"
                    aria-labelledby="modal-import-nilai-{{ $pertemuan_blok_id }}-label" aria-hidden="true"
                    style="z-index: 1060;"
                    x-data="{ bodyStyle: null }"
                    x-on:buka-import-nilai.window="if ($event.detail.modalId === $el.id) { bodyStyle = document.body.getAttribute('style'); bootstrap.Modal.getOrCreateInstance($el, { backdrop: false }).show(); }"
                    x-init="$el.addEventListener('hidden.bs.modal', () => { const parent = document.getElementById('pelaksanaanModal'); if (parent?.classList.contains('show')) { bodyStyle === null ? document.body.removeAttribute('style') : document.body.setAttribute('style', bodyStyle); document.body.classList.add('modal-open'); parent.focus(); bodyStyle = null; } })"
                    x-on:import-nilai-berhasil.window="if ($event.detail.modalId === $el.id) bootstrap.Modal.getInstance($el)?.hide()">
                    <div class="modal-dialog modal-dialog-centered">
                        <form wire:submit="importNilai" class="modal-content" x-data="{ uploadError: '' }">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modal-import-nilai-{{ $pertemuan_blok_id }}-label">Template Import</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body">
                                <button type="button" wire:click="unduhTemplate"
                                    wire:loading.attr="disabled" wire:target="unduhTemplate,importNilai"
                                    class="btn btn-secondary btn-sm mb-3 d-block">
                                    <i class="ri-file-excel-2-line"></i> Template Import
                                </button>
                                <label for="import-file-nilai-{{ $pertemuan_blok_id }}" class="form-label">File Import Nilai Pertemuan</label>
                                <input id="import-file-nilai-{{ $pertemuan_blok_id }}" type="file" class="form-control"
                                    wire:model="importFile" wire:loading.attr="disabled" wire:target="importNilai"
                                    x-on:livewire-upload-start="uploadError = ''"
                                    x-on:livewire-upload-error="uploadError = 'File gagal diunggah. Coba lagi atau hubungi pengelola sistem.'"
                                    accept=".xlsx,.xls,.csv">
                                <div x-show="uploadError" x-text="uploadError" class="small text-danger mt-1"
                                    role="alert" style="display: none;"></div>
                                @error('importFile') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                                @error('import_nilai') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary"
                                    wire:loading.attr="disabled" wire:target="unduhTemplate,importNilai">
                                    <span wire:loading.remove wire:target="importNilai"><i class="ri-upload-2-line"></i> Import</span>
                                    <span wire:loading wire:target="importNilai">Memproses...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endteleport
        @endif
    @endif
</div>
