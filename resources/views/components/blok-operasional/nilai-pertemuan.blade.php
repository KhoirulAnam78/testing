<?php

use App\Models\KomponenPenilaianBlok;
use App\Models\NilaiPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\PesertaBlok;
use App\Models\RekapNilaiPertemuanBlok;
use App\Support\AksesPertemuanBlok;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

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
 */
new class extends Component
{
    public int $pertemuan_blok_id;

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

        if ($komponen->isEmpty()) {
            $this->dispatch('notify', message: [
                'status' => 'failed',
                'message' => 'Rubrik penilaian kegiatan ini belum disusun.',
            ]);

            return;
        }

        if ($anggota->isEmpty()) {
            $this->dispatch('notify', message: [
                'status' => 'failed',
                'message' => 'Kelompok pertemuan ini belum punya anggota aktif.',
            ]);

            return;
        }

        [$rules, $messages] = $this->aturanValidasi($anggota, $komponen);

        $this->validate($rules, $messages);

        // Iterasi atas daftar dari database, bukan atas kunci $this->nilai, supaya peserta
        // atau komponen dari blok lain tidak bisa disusupkan dari klien.
        DB::transaction(function () use ($anggota, $komponen) {
            $nilaiMaks = (float) $komponen->sum(fn ($item) => (float) $item->nilai_maks);

            foreach ($anggota as $peserta) {
                $total = 0;

                foreach ($komponen as $item) {
                    $kunci = [
                        'pertemuan_blok_id' => $this->pertemuan_blok_id,
                        'peserta_blok_id' => $peserta->id_peserta_blok,
                        'komponen_penilaian_blok_id' => $item->id,
                    ];

                    $isian = trim((string) ($this->nilai[$peserta->id_peserta_blok][$item->id] ?? ''));

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
                    'peserta_blok_id' => $peserta->id_peserta_blok,
                ], [
                    'total' => $total,
                    'nilai_akhir' => RekapNilaiPertemuanBlok::hitungNilaiAkhir($total, $nilaiMaks),
                ]);
            }
        });

        $this->muatNilai();

        $this->dispatch('nilai-pertemuan-tersimpan');
        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Nilai berhasil disimpan.',
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

<div>
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
        </div>

        <form wire:submit="simpan">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th style="min-width: 180px;">Mahasiswa</th>
                            @foreach ($komponen as $item)
                                <th style="min-width: 120px;">
                                    <div>{{ $item->komponen_penilaian?->nama ?: $item->komponen_penilaian?->kode }}</div>
                                    <div class="text-muted fw-normal small">{{ $item->nilai_min }} - {{ $item->nilai_maks }}</div>
                                </th>
                            @endforeach
                            <th style="width: 100px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anggota as $index => $peserta)
                            @php($pesertaId = $peserta->id_peserta_blok)
                            @php($total = $this->totalPeserta($pesertaId))
                            @php($nilaiAkhir = $this->nilaiAkhirPeserta($pesertaId))
                            <tr wire:key="nilai-{{ $pesertaId }}">
                                <td class="text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <div class="small fw-semibold">{{ $peserta->mahasiswa?->nama }}</div>
                                    <div class="text-muted small">{{ $peserta->mahasiswa?->nim }}</div>
                                </td>
                                @foreach ($komponen as $item)
                                    <td>
                                        @if ($bolehIsi)
                                            <input type="number" step="0.01"
                                                min="{{ $item->nilai_min }}" max="{{ $item->nilai_maks }}"
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
                                <td>
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
            </div>

            @if ($bolehIsi)
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="simpan">
                        <i class="ri-save-line"></i> SIMPAN NILAI
                    </button>
                </div>
            @endif
        </form>
    @endif
</div>
