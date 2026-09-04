<?php

use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\Dosen;
use App\Models\KelompokBlok;
use App\Models\LampiranMateriBlok;
use App\Models\MateriBlok;
use App\Models\MateriRinciBlok;
use App\Models\PertemuanBlok;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public int $blok_id;
    public $aturan_kegiatan_blok_id;

    public $materi_rinci_blok_id;
    public ?string $mapping_kelompok_id = null;
    public ?string $copy_sumber_id = null;
    public ?string $copy_tujuan_id = null;
    public string $materi_judul = '';
    public ?int $materi_jumlah_sesi = null;
    public ?int $materi_durasi_menit = null;

    public array $mapping_tanggal = [];
    public array $mapping_jam_mulai = [];
    public array $mapping_jam_selesai = [];
    public array $mapping_ruangan = [];
    public array $mapping_catatan = [];
    public array $mapping_dosen_ids = [];

    public string $dosen_search = '';

    public ?int $modul_materi_rinci_blok_id = null;
    public ?int $modul_pertemuan_blok_id = null;
    public string $modul_materi_judul = '';
    public string $modul_kelompok_nama = '';

    public function mount($blok_id): void
    {
        $this->blok_id = (int) $blok_id;

        $blok = Blok::select('id')->findOrFail($this->blok_id);

        abort_unless($blok->dapatDikelolaOleh(auth()->user()), 403);

        $this->aturan_kegiatan_blok_id = $this->aturanList()->first()?->id;
    }

    public function pilihKegiatan(string $id): void
    {
        $aturan = $this->aturanList()->firstWhere('id', (int) $id);

        if (! $aturan) {
            return;
        }

        $this->aturan_kegiatan_blok_id = $aturan->id;
        $this->resetMapping();
    }

    public function aturanList()
    {
        return AturanKegiatanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->with('jenis_kegiatan:id,kode,nama')
            ->withCount(['kelompok_blok', 'pertemuan_blok', 'materi_rinci_blok'])
            ->orderBy('urutan')
            ->get(['id', 'blok_id', 'jenis_kegiatan_id', 'durasi_menit', 'urutan']);
    }

    public function resetMapping(): void
    {
        $this->reset([
            'materi_rinci_blok_id',
            'mapping_kelompok_id',
            'copy_sumber_id',
            'copy_tujuan_id',
            'materi_judul',
            'materi_jumlah_sesi',
            'materi_durasi_menit',
            'mapping_tanggal',
            'mapping_jam_mulai',
            'mapping_jam_selesai',
            'mapping_ruangan',
            'mapping_catatan',
            'mapping_dosen_ids',
            'dosen_search',
        ]);
        $this->resetErrorBag();
    }

    private function blok(): Blok
    {
        return Blok::select(['id', 'tanggal_mulai', 'tanggal_selesai'])->findOrFail($this->blok_id);
    }

    /**
     * Kelompok aktif pada jenis kegiatan terpilih. Pertemuan selalu milik kelompok,
     * jadi mapping tidak bisa dibuka sebelum kelompok ada.
     */
    public function semuaKelompokOptions()
    {
        if (! $this->aturan_kegiatan_blok_id) {
            return collect();
        }

        return KelompokBlok::query()
            ->where('blok_id', $this->blok_id)
            ->where('aturan_kegiatan_blok_id', $this->aturan_kegiatan_blok_id)
            ->where('status', 'aktif')
            ->withCount('anggota_kelompok_blok')
            ->orderBy('kode')
            ->get(['id_kelompok_blok', 'kode', 'nama', 'kelas_id']);
    }

    public function kelompokOptions()
    {
        return $this->semuaKelompokOptions()
            ->when(
                $this->materi_rinci_blok_id,
                fn ($kelompok) => $kelompok->filter(
                    fn ($item) => (string) $item->id_kelompok_blok === (string) ($this->mapping_kelompok_id ?? '')
                )
            )
            ->values();
    }

    /**
     * Hasil pencarian dosen dibatasi 20 baris, tetapi dosen yang sudah dipilih selalu
     * ikut ditampilkan lewat query kedua yang terbatas. Tanpa ini, dosen terpilih yang
     * berada di luar 20 nama teratas akan hilang dari tampilan dan tidak bisa dilepas.
     */
    public function dosenOptions()
    {
        $kolom = ['id_dosen', 'nama', 'nidn', 'nip'];

        $terpilih = collect($this->mapping_dosen_ids)
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $hasil = $this->dosen_search === ''
            ? collect()
            : Dosen::query()
                ->where('status', 'aktif')
                ->where(function ($query) {
                    $search = '%'.$this->dosen_search.'%';

                    $query->where('nama', 'like', $search)
                        ->orWhere('nidn', 'like', $search)
                        ->orWhere('nip', 'like', $search);
                })
                ->orderBy('nama')
                ->limit(20)
                ->get($kolom);

        $belumTampil = $terpilih->diff($hasil->pluck('id_dosen'));

        if ($belumTampil->isNotEmpty()) {
            $hasil = $hasil->merge(
                Dosen::whereIn('id_dosen', $belumTampil->all())->orderBy('nama')->get($kolom)
            );
        }

        return $hasil->sortBy('nama')->values();
    }

    public function kelolaMateri(string $id, string $kelompokId = ''): void
    {
        $aturan = $this->aturanTerpilih();

        $rinci = MateriRinciBlok::query()
            ->whereHas('materi_blok', fn ($query) => $query->where('aturan_kegiatan_blok_id', $aturan->id))
            ->findOrFail($id);

        $this->resetMapping();
        $this->materi_rinci_blok_id = $rinci->id_materi_rinci_blok;
        $this->mapping_kelompok_id = $kelompokId !== '' ? $kelompokId : null;

        $kelompok = $this->kelompokOptions();

        if ($kelompok->isEmpty()) {
            $this->resetMapping();
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Kelompok belajar tidak valid atau tidak aktif untuk jenis kegiatan tersebut.',
            ]);

            return;
        }

        $this->materi_judul = $rinci->judul;
        $this->materi_jumlah_sesi = $rinci->jumlah_sesi ?: 1;
        $this->materi_durasi_menit = $rinci->durasi_menit_per_sesi ?: $aturan->durasi_menit;

        $pertemuanPerKelompok = PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->where('materi_rinci_blok_id', $rinci->id_materi_rinci_blok)
            ->with('dosen_pertemuan_blok:id_dosen_pertemuan_blok,pertemuan_blok_id,dosen_id')
            ->get()
            ->keyBy('kelompok_blok_id');

        foreach ($kelompok as $item) {
            $id = $item->id_kelompok_blok;
            $pertemuan = $pertemuanPerKelompok->get($id);

            // Belum pernah dipetakan: pakai rencana dari template materi sebagai default.
            $this->mapping_tanggal[$id] = $pertemuan
                ? $pertemuan->tanggal?->toDateString()
                : $rinci->tanggal_rencana?->toDateString();
            $this->mapping_jam_mulai[$id] = $this->formatJam($pertemuan ? $pertemuan->jam_mulai : $rinci->jam_mulai_rencana);
            $this->mapping_jam_selesai[$id] = $this->formatJam($pertemuan ? $pertemuan->jam_selesai : $rinci->jam_selesai_rencana);
            $this->mapping_ruangan[$id] = $pertemuan?->ruangan;
            $this->mapping_catatan[$id] = $pertemuan?->catatan;
            $this->mapping_dosen_ids[$id] = $pertemuan
                ? $pertemuan->dosen_pertemuan_blok->pluck('dosen_id')->map(fn ($dosenId) => (string) $dosenId)->all()
                : [];
        }

        $this->dispatch('show-mapping-pertemuan-modal');
    }

    public function toggleDosen(string $kelompokId, string $dosenId): void
    {
        $current = array_map('strval', $this->mapping_dosen_ids[$kelompokId] ?? []);

        $this->mapping_dosen_ids[$kelompokId] = in_array($dosenId, $current, true)
            ? array_values(array_diff($current, [$dosenId]))
            : array_values(array_unique([...$current, $dosenId]));
    }

    public function salinKelompok(): void
    {
        $validIds = $this->semuaKelompokOptions()
            ->pluck('id_kelompok_blok')
            ->map(fn ($id) => (string) $id);

        if (
            ! $this->copy_sumber_id
            || ! $this->copy_tujuan_id
            || $this->copy_sumber_id === $this->copy_tujuan_id
            || ! $validIds->contains($this->copy_sumber_id)
            || ! $validIds->contains($this->copy_tujuan_id)
        ) {
            $this->addError('copy_tujuan_id', 'Pilih kelompok sumber dan tujuan yang valid dan berbeda.');

            return;
        }

        $pertemuanSumber = PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->where('aturan_kegiatan_blok_id', $this->aturan_kegiatan_blok_id)
            ->where('kelompok_blok_id', $this->copy_sumber_id)
            ->with([
                'dosen_pertemuan_blok:id_dosen_pertemuan_blok,pertemuan_blok_id,dosen_id,peran',
                'lampiran_materi_blok',
            ])
            ->get();

        if ($pertemuanSumber->isEmpty()) {
            $this->addError('copy_sumber_id', 'Kelompok sumber belum memiliki pengaturan materi.');

            return;
        }

        DB::transaction(function () use ($pertemuanSumber) {
            foreach ($pertemuanSumber as $sumber) {
                $tujuan = PertemuanBlok::withTrashed()->firstOrNew([
                    'blok_id' => $this->blok_id,
                    'materi_rinci_blok_id' => $sumber->materi_rinci_blok_id,
                    'kelompok_blok_id' => $this->copy_tujuan_id,
                ]);

                $tujuan->fill([
                    'aturan_kegiatan_blok_id' => $sumber->aturan_kegiatan_blok_id,
                    'tanggal' => $sumber->tanggal,
                    'jam_mulai' => $sumber->jam_mulai,
                    'jam_selesai' => $sumber->jam_selesai,
                    'ruangan' => $sumber->ruangan,
                    'topik' => $sumber->topik,
                    'jumlah_sesi' => $sumber->jumlah_sesi,
                    'durasi_menit_per_sesi' => $sumber->durasi_menit_per_sesi,
                    'status' => $sumber->status,
                    'catatan' => $sumber->catatan,
                ]);

                if ($tujuan->trashed()) {
                    $tujuan->restore();
                }

                $tujuan->save();
                $tujuan->dosen_pertemuan_blok()->delete();

                foreach ($sumber->dosen_pertemuan_blok as $dosen) {
                    $tujuan->dosen_pertemuan_blok()->create([
                        'dosen_id' => $dosen->dosen_id,
                        'peran' => $dosen->peran,
                    ]);
                }

                $tujuan->lampiran_materi_blok()->delete();

                foreach ($sumber->lampiran_materi_blok as $lampiran) {
                    $tujuan->lampiran_materi_blok()->create([
                        'blok_id' => $this->blok_id,
                        'materi_rinci_blok_id' => $sumber->materi_rinci_blok_id,
                        'jenis' => $lampiran->jenis,
                        'judul' => $lampiran->judul,
                        'url' => $lampiran->url,
                        'deskripsi' => $lampiran->deskripsi,
                        'urutan' => $lampiran->urutan,
                        'status' => $lampiran->status,
                        'dibuat_oleh_user_id' => auth()->id(),
                    ]);
                }
            }
        });

        $this->reset(['copy_sumber_id', 'copy_tujuan_id']);
        $this->resetErrorBag();

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => $pertemuanSumber->count().' pengaturan materi berhasil disalin ke kelompok tujuan.',
        ]);
    }

    public function savePertemuan(): void
    {
        $payload = $this->validate([
            'aturan_kegiatan_blok_id' => ['required', Rule::exists('aturan_kegiatan_blok', 'id')->where('blok_id', $this->blok_id)],
            'materi_rinci_blok_id' => ['required', 'exists:materi_rinci_blok,id_materi_rinci_blok'],
            'mapping_tanggal' => ['array'],
            'mapping_tanggal.*' => ['nullable', 'date_format:Y-m-d'],
            'mapping_jam_mulai' => ['array'],
            'mapping_jam_mulai.*' => ['nullable', 'date_format:H:i'],
            'mapping_jam_selesai' => ['array'],
            'mapping_jam_selesai.*' => ['nullable', 'date_format:H:i'],
            'mapping_ruangan' => ['array'],
            'mapping_ruangan.*' => ['nullable', 'string', 'max:255'],
            'mapping_catatan' => ['array'],
            'mapping_catatan.*' => ['nullable', 'string', 'max:1000'],
            'mapping_dosen_ids' => ['array'],
            'mapping_dosen_ids.*' => ['array'],
            'mapping_dosen_ids.*.*' => ['integer'],
        ], [
            'materi_rinci_blok_id.required' => 'Materi wajib dipilih.',
            'mapping_tanggal.*.date_format' => 'Format tanggal tidak valid.',
            'mapping_jam_mulai.*.date_format' => 'Format jam mulai tidak valid.',
            'mapping_jam_selesai.*.date_format' => 'Format jam selesai tidak valid.',
        ]);

        $aturan = $this->aturanTerpilih();

        $materi = MateriRinciBlok::query()
            ->whereHas('materi_blok', fn ($query) => $query->where('aturan_kegiatan_blok_id', $aturan->id))
            ->find($payload['materi_rinci_blok_id']);

        if (! $materi) {
            $this->addError('materi_rinci_blok_id', 'Materi harus berasal dari jenis kegiatan yang dipilih.');

            return;
        }

        $kelompokIds = $this->kelompokOptions()->pluck('id_kelompok_blok')->map(fn ($id) => (int) $id);

        if ($kelompokIds->isEmpty()) {
            $this->addError('mapping_dosen_ids', 'Kelompok belajar belum tersedia untuk jenis kegiatan ini.');

            return;
        }

        $blok = $this->blok();
        $semuaDosenIds = collect($payload['mapping_dosen_ids'] ?? [])
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($semuaDosenIds->isNotEmpty()) {
            $dosenAktif = Dosen::where('status', 'aktif')->whereIn('id_dosen', $semuaDosenIds)->count();

            if ($dosenAktif !== $semuaDosenIds->count()) {
                $this->addError('mapping_dosen_ids', 'Dosen pengampu harus dosen aktif yang valid.');

                return;
            }
        }

        foreach ($kelompokIds as $kelompokId) {
            $dosenIds = collect($payload['mapping_dosen_ids'][$kelompokId] ?? [])->filter()->values();

            if ($dosenIds->isEmpty()) {
                $this->addError("mapping_dosen_ids.$kelompokId", 'Dosen pengampu wajib dipilih.');

                return;
            }

            $tanggal = $payload['mapping_tanggal'][$kelompokId] ?? null;
            $jamMulai = $payload['mapping_jam_mulai'][$kelompokId] ?? null;
            $jamSelesai = $payload['mapping_jam_selesai'][$kelompokId] ?? null;

            if ($jamSelesai && ! $jamMulai) {
                $this->addError("mapping_jam_mulai.$kelompokId", 'Jam mulai wajib diisi jika jam selesai diisi.');

                return;
            }

            if ($jamMulai && $jamSelesai && $jamSelesai <= $jamMulai) {
                $this->addError("mapping_jam_selesai.$kelompokId", 'Jam selesai harus lebih besar dari jam mulai.');

                return;
            }

            if ($tanggal && $blok->tanggal_mulai && $tanggal < $blok->tanggal_mulai->toDateString()) {
                $this->addError("mapping_tanggal.$kelompokId", 'Tanggal tidak boleh sebelum tanggal mulai blok.');

                return;
            }

            if ($tanggal && $blok->tanggal_selesai && $tanggal > $blok->tanggal_selesai->toDateString()) {
                $this->addError("mapping_tanggal.$kelompokId", 'Tanggal tidak boleh setelah tanggal selesai blok.');

                return;
            }
        }

        $jumlahSesi = $materi->jumlah_sesi ?: 1;
        $durasiMenit = $materi->durasi_menit_per_sesi ?: $aturan->durasi_menit;

        DB::transaction(function () use ($payload, $materi, $kelompokIds, $jumlahSesi, $durasiMenit) {
            foreach ($kelompokIds as $kelompokId) {
                $tanggal = $payload['mapping_tanggal'][$kelompokId] ?? null;

                // Baris yang pernah dihapus lembut tetap menempati unique index
                // (blok, materi rinci, kelompok), jadi dipulihkan alih-alih dibuat ulang.
                $pertemuan = PertemuanBlok::withTrashed()->firstOrNew([
                    'blok_id' => $this->blok_id,
                    'materi_rinci_blok_id' => $materi->id_materi_rinci_blok,
                    'kelompok_blok_id' => $kelompokId,
                ]);

                $pertemuan->fill([
                    'blok_id' => $this->blok_id,
                    'materi_rinci_blok_id' => $materi->id_materi_rinci_blok,
                    'kelompok_blok_id' => $kelompokId,
                    'aturan_kegiatan_blok_id' => $payload['aturan_kegiatan_blok_id'],
                    'tanggal' => $tanggal ?: null,
                    'jam_mulai' => ($payload['mapping_jam_mulai'][$kelompokId] ?? null) ?: null,
                    'jam_selesai' => ($payload['mapping_jam_selesai'][$kelompokId] ?? null) ?: null,
                    'ruangan' => ($payload['mapping_ruangan'][$kelompokId] ?? null) ?: null,
                    'topik' => $materi->judul,
                    'jumlah_sesi' => $jumlahSesi,
                    'durasi_menit_per_sesi' => $durasiMenit ?: null,
                    'status' => $tanggal ? 'terjadwal' : 'draft',
                    'catatan' => ($payload['mapping_catatan'][$kelompokId] ?? null) ?: null,
                ]);

                if ($pertemuan->trashed()) {
                    $pertemuan->restore();
                }

                $pertemuan->save();

                $dosenIds = collect($payload['mapping_dosen_ids'][$kelompokId] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $pertemuan->dosen_pertemuan_blok()
                    ->whereNotIn('dosen_id', $dosenIds->all())
                    ->delete();

                foreach ($dosenIds as $dosenId) {
                    $pertemuan->dosen_pertemuan_blok()->updateOrCreate(
                        ['dosen_id' => $dosenId],
                        ['peran' => 'pengampu']
                    );
                }
            }
        });

        $this->resetMapping();
        $this->dispatch('hide-mapping-pertemuan-modal');

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Dosen pengampu dan jadwal pertemuan berhasil disimpan.',
        ]);
    }

    public function deleteMapping(string $id): void
    {
        PertemuanBlok::where('blok_id', $this->blok_id)
            ->where('materi_rinci_blok_id', $id)
            ->get()
            ->each(fn (PertemuanBlok $pertemuan) => $pertemuan->delete());

        $this->dispatch('notify', message: [
            'status' => 'success',
            'message' => 'Mapping pertemuan materi ini berhasil dihapus.',
        ]);
    }

    /**
     * Membuka tautan modul/video untuk satu pertemuan pada satu kelompok.
     */
    public function kelolaModul(string $id, string $kelompokId): void
    {
        $aturan = $this->aturanTerpilih();

        $rinci = MateriRinciBlok::query()
            ->whereHas('materi_blok', fn ($query) => $query->where('aturan_kegiatan_blok_id', $aturan->id))
            ->findOrFail($id);

        $pertemuan = PertemuanBlok::query()
            ->where('blok_id', $this->blok_id)
            ->where('aturan_kegiatan_blok_id', $aturan->id)
            ->where('materi_rinci_blok_id', $rinci->id_materi_rinci_blok)
            ->where('kelompok_blok_id', $kelompokId)
            ->with('kelompok_blok:id_kelompok_blok,kode,nama')
            ->first();

        if (! $pertemuan) {
            $this->dispatch('notify', message: [
                'status' => 'error',
                'message' => 'Atur dosen pengampu dan jadwal pertemuan kelas ini terlebih dahulu.',
            ]);

            return;
        }

        $this->modul_materi_rinci_blok_id = $rinci->id_materi_rinci_blok;
        $this->modul_pertemuan_blok_id = $pertemuan->id_pertemuan_blok;
        $this->modul_materi_judul = $rinci->judul;
        $this->modul_kelompok_nama = trim($pertemuan->kelompok_blok?->kode.' - '.$pertemuan->kelompok_blok?->nama, ' -');

        $this->dispatch('show-modul-materi-modal');
    }

    public function tutupModul(): void
    {
        $this->reset([
            'modul_materi_rinci_blok_id',
            'modul_pertemuan_blok_id',
            'modul_materi_judul',
            'modul_kelompok_nama',
        ]);
    }

    /**
     * Badge jumlah tautan dihitung di render, jadi komponen ini perlu ikut segar
     * setiap kali komponen lampiran menyimpan sesuatu.
     */
    #[On('lampiran-materi-tersimpan')]
    public function refreshLampiran(): void
    {
        //
    }

    private function aturanTerpilih(): AturanKegiatanBlok
    {
        return AturanKegiatanBlok::where('blok_id', $this->blok_id)->findOrFail($this->aturan_kegiatan_blok_id);
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }

    public function render()
    {
        $aturanList = $this->aturanList();

        $materiList = $this->aturan_kegiatan_blok_id
            ? MateriBlok::query()
                ->where('aturan_kegiatan_blok_id', $this->aturan_kegiatan_blok_id)
                ->with(['materi_rinci_blok' => fn ($query) => $query->orderBy('urutan')])
                ->orderBy('urutan')
                ->get()
            : collect();

        $pertemuanPerMateri = $this->aturan_kegiatan_blok_id
            ? PertemuanBlok::query()
                ->where('blok_id', $this->blok_id)
                ->where('aturan_kegiatan_blok_id', $this->aturan_kegiatan_blok_id)
                ->with([
                    'kelompok_blok:id_kelompok_blok,kode,nama',
                    'dosen_pertemuan_blok.dosen:id_dosen,nama',
                ])
                ->orderBy('tanggal')
                ->get()
                ->groupBy('materi_rinci_blok_id')
            : collect();

        $materiRinciIds = $materiList
            ->flatMap(fn (MateriBlok $materi) => $materi->materi_rinci_blok->pluck('id_materi_rinci_blok'))
            ->all();

        $lampiranPerPertemuan = $materiRinciIds
            ? LampiranMateriBlok::query()
                ->whereIn('materi_rinci_blok_id', $materiRinciIds)
                ->whereNotNull('pertemuan_blok_id')
                ->selectRaw('pertemuan_blok_id, count(*) as jumlah')
                ->groupBy('pertemuan_blok_id')
                ->pluck('jumlah', 'pertemuan_blok_id')
            : collect();

        $semuaKelompokOptions = $this->semuaKelompokOptions();

        return $this->view([
            'aturanList' => $aturanList,
            'aturanAktif' => $aturanList->firstWhere('id', (int) $this->aturan_kegiatan_blok_id),
            'materiList' => $materiList,
            'pertemuanPerMateri' => $pertemuanPerMateri,
            'lampiranPerPertemuan' => $lampiranPerPertemuan,
            'kelompokOptions' => $this->kelompokOptions(),
            'semuaKelompokOptions' => $semuaKelompokOptions,
            'dosenOptions' => $this->materi_rinci_blok_id ? $this->dosenOptions() : collect(),
        ]);
    }
};
?>

<div>
    <x-full-page-loading message="Memproses operasional blok..." />
    @if ($aturanList->isEmpty())
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            <i class="ri-error-warning-line"></i>
            Blok ini belum punya jenis kegiatan. Susun kegiatan dan materi terlebih dahulu di menu Blok.
        </div>
    @else
        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label">Jenis Kegiatan</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($aturanList as $aturan)
                        <button type="button"
                            class="btn btn-sm {{ (int) $aturan_kegiatan_blok_id === (int) $aturan->id ? 'btn-primary' : 'btn-light' }}"
                            wire:click="pilihKegiatan('{{ $aturan->id }}')">
                            {{ $aturan->jenis_kegiatan?->nama }}
                            <span class="badge bg-light text-dark border ms-1">{{ $aturan->kelompok_blok_count }} kelompok</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($kelompokOptions->isEmpty())
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                <i class="ri-group-line"></i>
                Belum ada kelompok aktif pada jenis kegiatan ini. Buat kelompok di tab <span class="fw-semibold">Kelompok</span> sebelum mengisi dosen pengampu.
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="ri-file-copy-line"></i> Salin Semua Pengaturan Kelompok</h5>
                <div class="text-muted small">Salin dosen pengampu, jadwal, catatan, modul, dan video seluruh pertemuan dari satu kelompok ke kelompok lain.</div>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Kelompok Sumber</label>
                        <select class="form-select" wire:model="copy_sumber_id">
                            <option value="">Pilih kelompok sumber</option>
                            @foreach ($semuaKelompokOptions as $kelompok)
                                <option value="{{ $kelompok->id_kelompok_blok }}">{{ $kelompok->kode }} - {{ $kelompok->nama }}</option>
                            @endforeach
                        </select>
                        @error('copy_sumber_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Kelompok Tujuan</label>
                        <select class="form-select" wire:model="copy_tujuan_id">
                            <option value="">Pilih kelompok tujuan</option>
                            @foreach ($semuaKelompokOptions as $kelompok)
                                <option value="{{ $kelompok->id_kelompok_blok }}">{{ $kelompok->kode }} - {{ $kelompok->nama }}</option>
                            @endforeach
                        </select>
                        @error('copy_tujuan_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100" wire:click="salinKelompok"
                            wire:confirm="Semua pengaturan materi yang sama pada kelompok tujuan akan ditimpa. Lanjutkan?">
                            <i class="ri-file-copy-line"></i> Salin Semua
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h5 class="mb-0">Materi per Kelompok Belajar &middot; {{ $aturanAktif?->jenis_kegiatan?->nama }}</h5>
                        <div class="text-muted small">Setiap panel di bawah mewakili satu kelompok. Semua materi, dosen, dan jadwal di dalam panel hanya berlaku untuk kelompok tersebut.</div>
                    </div>
                    <span class="badge bg-primary">{{ $semuaKelompokOptions->count() }} kelompok aktif</span>
                </div>
            </div>
            <div class="card-body">
                @forelse ($semuaKelompokOptions as $kelompok)
                    <div class="border border-2 border-primary rounded-3 overflow-hidden mb-4" wire:key="kelompok-{{ $kelompok->id_kelompok_blok }}">
                        <div class="bg-primary text-white p-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <div class="small fw-semibold text-uppercase opacity-75">Kelompok {{ $loop->iteration }} dari {{ $loop->count }}</div>
                                    <div class="fs-5 fw-semibold"><i class="ri-group-line"></i> {{ $kelompok->kode }} - {{ $kelompok->nama }}</div>
                                </div>
                                <span class="badge bg-white text-primary">{{ $kelompok->anggota_kelompok_blok_count }} anggota</span>
                            </div>
                        </div>
                        <div class="p-3">
                            @forelse ($materiList as $materi)
                                <div class="mb-3">
                                    <div class="border-start border-3 border-primary bg-primary-subtle rounded-end px-3 py-2 mb-2">
                                        <div class="small fw-semibold text-primary text-uppercase">
                                            <i class="ri-book-open-line"></i> Materi Pokok {{ $materi->urutan }}
                                        </div>
                                        <div class="fw-semibold mt-1">{{ $materi->judul }}</div>
                                    </div>
                                    @forelse ($materi->materi_rinci_blok as $rinci)
                                        @php($pertemuan = ($pertemuanPerMateri->get($rinci->id_materi_rinci_blok) ?? collect())->firstWhere('kelompok_blok_id', $kelompok->id_kelompok_blok))
                                        @php($jumlahLampiran = $pertemuan ? (int) ($lampiranPerPertemuan[$pertemuan->id_pertemuan_blok] ?? 0) : 0)
                                        @php($dosenPengampu = $pertemuan?->dosen_pertemuan_blok->pluck('dosen.nama')->filter() ?? collect())
                                        <div class="border rounded p-3 mb-2 ms-md-3" wire:key="rinci-{{ $kelompok->id_kelompok_blok }}-{{ $rinci->id_materi_rinci_blok }}">
                                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                                <div class="flex-grow-1">
                                                    <div class="small fw-semibold text-info text-uppercase mb-1">
                                                        <i class="ri-calendar-event-line"></i> Rincian Pertemuan
                                                    </div>
                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle">Pertemuan {{ $rinci->pertemuan_ke ?: '-' }}</span>
                                                        <span class="fw-semibold">{{ $rinci->judul }}</span>
                                                    </div>

                                                    <div class="border-top mt-2 pt-2">
                                                        <div class="small fw-semibold text-muted mb-1">
                                                            <i class="ri-user-star-line"></i> Dosen Pengampu
                                                        </div>
                                                        @forelse ($dosenPengampu as $namaDosen)
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle me-1 mb-1">
                                                                <i class="ri-user-line"></i> {{ $namaDosen }}
                                                            </span>
                                                        @empty
                                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                                <i class="ri-error-warning-line"></i> Belum ditentukan
                                                            </span>
                                                        @endforelse

                                                        @if ($pertemuan)
                                                            <div class="small text-muted mt-1">
                                                                <i class="ri-calendar-line"></i>
                                                                @if ($pertemuan->tanggal || $pertemuan->jam_mulai || $pertemuan->ruangan)
                                                                    @if ($pertemuan->tanggal) {{ $pertemuan->tanggal->format('d/m/Y') }} @endif
                                                                    @if ($pertemuan->jam_mulai) &middot; {{ substr((string) $pertemuan->jam_mulai, 0, 5) }}{{ $pertemuan->jam_selesai ? '-'.substr((string) $pertemuan->jam_selesai, 0, 5) : '' }} @endif
                                                                    @if ($pertemuan->ruangan) &middot; {{ $pertemuan->ruangan }} @endif
                                                                @else
                                                                    Jadwal belum ditentukan.
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="small text-warning mt-1">
                                                                <i class="ri-information-line"></i> Pertemuan belum diatur untuk kelompok ini.
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <button type="button" class="btn btn-info btn-sm"
                                                        wire:click="kelolaMateri('{{ $rinci->id_materi_rinci_blok }}', '{{ $kelompok->id_kelompok_blok }}')">
                                                        <i class="ri-user-settings-line"></i> Pengampu & Jadwal
                                                    </button>
                                                    <button type="button" class="btn btn-primary btn-sm"
                                                        wire:click="kelolaModul('{{ $rinci->id_materi_rinci_blok }}', '{{ $kelompok->id_kelompok_blok }}')">
                                                        <i class="ri-links-line"></i> Modul & Video
                                                        @if ($jumlahLampiran) <span class="badge bg-primary">{{ $jumlahLampiran }}</span> @endif
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-muted small">Belum ada rincian materi.</div>
                                    @endforelse
                                </div>
                            @empty
                                <div class="text-muted">Belum ada materi pada jenis kegiatan ini.</div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="text-muted">Belum ada kelompok belajar aktif pada jenis kegiatan ini.</div>
                @endforelse
            </div>
        </div>
    @endif

    <div class="modal fade" id="mappingPertemuanModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form wire:submit="savePertemuan" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dosen Pengampu &amp; Jadwal per Kelompok</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" wire:click="resetMapping"></button>
                </div>
                <div class="modal-body">
                    @if (! $materi_rinci_blok_id)
                        <div class="text-muted">Pilih tombol Kelola pada salah satu rincian materi.</div>
                    @else
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="small fw-semibold text-info text-uppercase">
                                <i class="ri-calendar-event-line"></i> Rincian Pertemuan
                            </div>
                            <div class="fw-semibold mt-1">{{ $materi_judul }}</div>
                            <div class="small text-muted mt-1">
                                {{ $materi_jumlah_sesi ?: 1 }} sesi
                                @if ($materi_durasi_menit)
                                    &middot; {{ $materi_durasi_menit }} menit / sesi
                                @endif
                            </div>
                        </div>

                        @error('mapping_dosen_ids') <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>{{ $message }}</div> @enderror
                        @error('materi_rinci_blok_id') <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>{{ $message }}</div> @enderror

                        <div class="mb-3">
                            <label class="form-label">Cari Dosen Pengampu</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ri-search-line"></i></span>
                                <input type="text" class="form-control" placeholder="Ketik nama, NIDN, atau NIP dosen" wire:model.live.debounce.400ms="dosen_search">
                            </div>
                        </div>

                        @foreach ($kelompokOptions as $kelompok)
                            @php($id = $kelompok->id_kelompok_blok)
                            @php($dosenTerpilih = array_map('strval', $mapping_dosen_ids[$id] ?? []))
                            <div class="border border-primary rounded overflow-hidden mb-3" wire:key="mapping-{{ $id }}">
                                <div class="bg-primary-subtle border-bottom border-primary p-3">
                                    <div class="small fw-semibold text-primary text-uppercase mb-1">Kelompok yang Diatur</div>
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div class="fw-semibold"><i class="ri-group-line"></i> {{ $kelompok->kode }} - {{ $kelompok->nama }}</div>
                                        <span class="badge bg-primary">{{ $kelompok->anggota_kelompok_blok_count }} anggota</span>
                                    </div>
                                </div>

                                <div class="row p-3 pb-0">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Tanggal</label>
                                        <input type="date" class="form-control" wire:model="mapping_tanggal.{{ $id }}">
                                        @error('mapping_tanggal.'.$id) <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Mulai</label>
                                        <input type="time" class="form-control" wire:model="mapping_jam_mulai.{{ $id }}">
                                        @error('mapping_jam_mulai.'.$id) <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Selesai</label>
                                        <input type="time" class="form-control" wire:model="mapping_jam_selesai.{{ $id }}">
                                        @error('mapping_jam_selesai.'.$id) <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Ruangan</label>
                                        <input type="text" class="form-control" wire:model="mapping_ruangan.{{ $id }}">
                                        @error('mapping_ruangan.'.$id) <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Catatan</label>
                                        <input type="text" class="form-control" wire:model="mapping_catatan.{{ $id }}">
                                        @error('mapping_catatan.'.$id) <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="px-3 pb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="ri-user-star-line text-success"></i> Dosen Pengampu
                                        <span class="badge bg-info-subtle text-info ms-1">Bisa lebih dari satu</span>
                                    </label>
                                    <div class="text-muted small mb-2">Klik nama dosen untuk memilih. Klik kembali untuk membatalkan pilihan.</div>
                                    @error('mapping_dosen_ids.'.$id) <div class="text-sm text-danger mb-1">{{ $message }}</div> @enderror
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($dosenOptions as $dosen)
                                            @php($aktif = in_array((string) $dosen->id_dosen, $dosenTerpilih, true))
                                            <button type="button"
                                                class="btn btn-sm {{ $aktif ? 'btn-success' : 'btn-light' }}"
                                                aria-pressed="{{ $aktif ? 'true' : 'false' }}"
                                                wire:click="toggleDosen('{{ $id }}', '{{ $dosen->id_dosen }}')">
                                                @if ($aktif)
                                                    <i class="ri-check-line"></i>
                                                @endif
                                                {{ $dosen->nama }}
                                            </button>
                                        @endforeach
                                        @if ($dosenOptions->isEmpty())
                                            <div class="text-muted small">
                                                {{ $dosen_search === '' ? 'Cari nama dosen terlebih dahulu, lalu pilih hasilnya.' : 'Tidak ada dosen aktif yang cocok.' }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" wire:click="resetMapping">Batal</button>
                    @if ($materi_rinci_blok_id)
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <i class="ri-save-line"></i> SIMPAN
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modulMateriModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tautan Modul &amp; Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup" wire:click="tutupModul"></button>
                </div>
                <div class="modal-body">
                    @if (! $modul_materi_rinci_blok_id)
                        <div class="text-muted">Pilih tombol Modul &amp; Video pada salah satu rincian materi.</div>
                    @else
                         <div class="border rounded p-3 mb-3 bg-light">
                             <div class="text-muted small">Pertemuan & Kelas</div>
                             <div class="fw-semibold">{{ $modul_materi_judul }}</div>
                             <div class="small text-muted">{{ $modul_kelompok_nama }}</div>
                         </div>

                         <div class="text-muted small mb-2">
                             Modul dan video di bawah khusus untuk pertemuan kelas ini. Tautan default materi tetap tampil sebagai referensi.
                         </div>
                         <livewire:blok-operasional.lampiran-materi
                             :materi_rinci_blok_id="$modul_materi_rinci_blok_id"
                             :pertemuan_blok_id="$modul_pertemuan_blok_id"
                             :key="'lampiran-pertemuan-'.$modul_pertemuan_blok_id" />
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" wire:click="tutupModul">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>
