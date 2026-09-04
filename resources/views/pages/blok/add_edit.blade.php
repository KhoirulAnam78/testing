<?php

use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\KomponenPenilaian;
use App\Models\KomponenPenilaianBlok;
use App\Models\MataKuliah;
use App\Models\MateriBlok;
use App\Models\MateriRinciBlok;
use App\Models\PengelolaBlok;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $edit_id;

    public $prodi_id;

    public $semester_id;

    public $koordinator_id;

    public $asisten_koordinator_id;

    public array $selected_kontributor_ids = [];

    public $nama;

    public $sks = 1;

    public $tanggal_mulai;

    public $tanggal_selesai;

    public $deskripsi;

    public $aturan = [];

    public $selected_mata_kuliah_ids = [];

    public $prodi = [];

    public $semester = [];

    public $dosen = [];

    public $jenis_kegiatan = [];

    public $komponen_penilaian = [];

    public $blok_copy_options = [];

    public $copy_blok_id = '';

    public string $active_tab = 'informasi';

    public int $active_aturan_index = 0;

    public bool $save_attempted = false;

    public ?string $copy_success_message = null;

    public ?string $save_success_message = null;

    public function mount($id): void
    {
        $this->prodi = Prodi::where('status', 'aktif')->orderBy('nama')->get(['id_prodi', 'nama', 'kode']);
        $this->semester = Semester::orderByDesc('tahun')->orderBy('nama')->get(['id_semester', 'nama', 'tahun', 'kode']);
        $this->dosen = Dosen::where('status', 'aktif')->orderBy('nama')->get(['id_dosen', 'nidn', 'nama', 'gelar_depan', 'gelar_belakang']);
        $this->jenis_kegiatan = JenisKegiatan::where('status', 'aktif')->orderBy('nama')->get(['id', 'kode', 'nama', 'durasi_menit_default']);
        $this->blok_copy_options = Blok::query()
            ->with(['prodi', 'semester'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Blok $blok) => [
                'id' => (string) $blok->id,
                'label' => $blok->nama.' | '.$blok->prodi?->nama.' | '.($blok->semester ? ucfirst($blok->semester->nama).' '.$blok->semester->tahun : '-'),
            ])
            ->all();

        if ($id && $id !== 'add') {
            try {
                $this->edit_id = Crypt::decrypt($id);
            } catch (DecryptException $e) {
                abort(404, 'Enkripsi tidak valid !');
            }

            $blok = Blok::with([
                'pengelola_blok',
                'aturan_kegiatan_blok.materi_blok.materi_rinci_blok',
                'aturan_kegiatan_blok.komponen_penilaian_blok',
            ])->findOrFail($this->edit_id);
            $this->prodi_id = $blok->prodi_id;
            $this->semester_id = $blok->semester_id;
            $this->koordinator_id = $blok->pengelola_blok->firstWhere('jabatan', 'koordinator')?->dosen_id;
            $this->asisten_koordinator_id = $blok->pengelola_blok->firstWhere('jabatan', 'asisten_koordinator')?->dosen_id;
            $this->selected_kontributor_ids = $blok->pengelola_blok
                ->where('jabatan', 'kontributor')
                ->pluck('dosen_id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();
            $this->nama = $blok->nama;
            $this->sks = $blok->sks;
            $this->tanggal_mulai = $blok->tanggal_mulai?->format('Y-m-d');
            $this->tanggal_selesai = $blok->tanggal_selesai?->format('Y-m-d');
            $this->deskripsi = $blok->deskripsi;
            $this->selected_mata_kuliah_ids = $blok->mata_kuliah()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
            $this->aturan = $blok->aturan_kegiatan_blok
                ->sortBy('urutan')
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'jenis_kegiatan_id' => $item->jenis_kegiatan_id,
                    'durasi_menit' => $item->durasi_menit,
                    'jumlah_mahasiswa_per_kelompok' => $item->jumlah_mahasiswa_per_kelompok,
                    'perlu_kelompok' => true,
                    'perlu_presensi' => (bool) $item->perlu_presensi,
                    'perlu_logbook' => (bool) $item->perlu_logbook,
                    'perlu_penilaian' => (bool) $item->perlu_penilaian,
                    'urutan' => $item->urutan,
                    'komponen' => $item->komponen_penilaian_blok
                        ->sortBy('urutan')
                        ->map(fn (KomponenPenilaianBlok $komponen) => [
                            'id' => $komponen->id,
                            'komponen_penilaian_id' => (string) $komponen->komponen_penilaian_id,
                            'nilai_min' => $komponen->nilai_min,
                            'nilai_maks' => $komponen->nilai_maks,
                            'urutan' => $komponen->urutan,
                        ])
                        ->values()
                        ->toArray(),
                    'materi' => $item->materi_blok
                        ->sortBy('urutan')
                        ->map(fn ($materi) => [
                            'id' => $materi->id_materi_blok,
                            'judul' => $materi->judul,
                            'deskripsi' => $materi->deskripsi,
                            'capaian_pembelajaran' => $materi->capaian_pembelajaran,
                            'urutan' => $materi->urutan,
                            'status' => $materi->status,
                            'rinci' => $materi->materi_rinci_blok
                                ->sortBy('urutan')
                                ->map(fn ($rinci) => [
                                    'id' => $rinci->id_materi_rinci_blok,
                                    'judul' => $rinci->judul,
                                    'deskripsi' => $rinci->deskripsi,
                                    'capaian_pembelajaran' => $rinci->capaian_pembelajaran,
                                    'referensi' => $rinci->referensi,
                                    'pertemuan_ke' => $rinci->pertemuan_ke,
                                    'tanggal_rencana' => $rinci->tanggal_rencana?->format('Y-m-d'),
                                    'jam_mulai_rencana' => $this->formatJam($rinci->jam_mulai_rencana),
                                    'jam_selesai_rencana' => $this->formatJam($rinci->jam_selesai_rencana),
                                    'jumlah_sesi' => $rinci->jumlah_sesi ?: 1,
                                    'durasi_menit_per_sesi' => $rinci->durasi_menit_per_sesi ?: $item->durasi_menit,
                                    'urutan' => $rinci->urutan,
                                    'status' => $rinci->status,
                                ])
                                ->values()
                                ->toArray(),
                        ])
                        ->values()
                        ->toArray(),
                ])
                ->values()
                ->toArray();
        }

        if (empty($this->aturan)) {
            $this->addAturan();
        }

        foreach ($this->aturan as $index => $aturan) {
            $this->aturan[$index]['perlu_kelompok'] = true;

            if (empty($aturan['materi'])) {
                $this->aturan[$index]['materi'] = [$this->emptyMateri()];
            }

            if (! isset($aturan['komponen']) || ! is_array($aturan['komponen'])) {
                $this->aturan[$index]['komponen'] = [];
            }

        }

        $this->muatKomponenPenilaian();
    }

    /**
     * Komponen nonaktif tetap dimuat bila sudah dipakai rubrik blok ini agar nama rubrik lama
     * tetap tampil dan tidak hilang saat disimpan ulang.
     */
    private function muatKomponenPenilaian(): void
    {
        $terpakai = collect($this->aturan)
            ->flatMap(fn ($aturan) => collect($aturan['komponen'] ?? [])->pluck('komponen_penilaian_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $this->komponen_penilaian = KomponenPenilaian::query()
            ->where(fn ($query) => $query
                ->where('status', 'aktif')
                ->when($terpakai !== [], fn ($inner) => $inner->orWhereIn('id', $terpakai)))
            ->orderBy('nama')
            ->get(['id', 'nama']);
    }

    public function updatedAturan($value, $key): void
    {
        [$index] = explode('.', $key);

        if (! isset($this->aturan[$index])) {
            return;
        }

        $this->aturan[$index]['perlu_kelompok'] = true;

        if (str_ends_with($key, 'jenis_kegiatan_id')) {
            $duplikat = collect($this->aturan)
                ->except((int) $index)
                ->pluck('jenis_kegiatan_id')
                ->filter()
                ->contains(fn ($id) => (int) $id === (int) $value);

            if ($duplikat) {
                $this->aturan[$index]['jenis_kegiatan_id'] = '';
                $this->addError("aturan.$index.jenis_kegiatan_id", 'Jenis kegiatan sudah dipilih pada kegiatan lain.');

                return;
            }

            $this->resetValidation("aturan.$index.jenis_kegiatan_id");
            $jenis = $this->jenis_kegiatan->firstWhere('id', (int) $value);

            if (! $jenis) {
                return;
            }

            $this->aturan[$index]['durasi_menit'] = $jenis->durasi_menit_default;

            // Rubrik hanya diambil otomatis saat masih kosong, supaya penyesuaian yang sudah
            // dibuat pengelola tidak tertimpa ketika jenis kegiatan diganti bolak-balik.
            if (empty($this->aturan[$index]['komponen'])) {
                $this->ambilStandarPenilaian((int) $index);
            }

            return;
        }

        if (str_ends_with($key, 'perlu_penilaian')) {
            // Isi standar hanya saat pertama kali penilaian dinyalakan. Rubrik yang pernah
            // disusun tetap dipakai dan tidak ditambah atau ditimpa oleh standar terbaru.
            if ((bool) $value && empty($this->aturan[$index]['komponen'])) {
                $this->ambilStandarPenilaian((int) $index);
            }

            return;
        }

        if (str_ends_with($key, 'durasi_menit')) {
            return;
        }

        if (
            str_ends_with($key, 'durasi_menit_per_sesi')
            || str_ends_with($key, 'jumlah_sesi')
            || str_ends_with($key, 'jam_mulai_rencana')
        ) {
            $segments = explode('.', $key);

            if (count($segments) === 6) {
                $this->syncJamSelesai((int) $index, (int) $segments[2], (int) $segments[4]);
            }
        }
    }

    /**
     * Menyalin standar komponen penilaian jenis kegiatan ke rubrik blok ini. Komponen yang
     * sudah ada dipertahankan agar penyesuaian per blok tidak hilang.
     */
    public function ambilStandarPenilaian(int $aturanIndex): void
    {
        if (! isset($this->aturan[$aturanIndex])) {
            return;
        }

        $jenisId = (int) ($this->aturan[$aturanIndex]['jenis_kegiatan_id'] ?? 0);

        if (! $jenisId) {
            $this->addError("aturan.$aturanIndex.komponen", 'Pilih jenis kegiatan terlebih dahulu.');

            return;
        }

        $standar = KomponenPenilaian::query()
            ->where('jenis_kegiatan_id', $jenisId)
            ->aktif()
            ->orderBy('urutan')
            ->get(['id', 'nilai_min_default', 'nilai_maks_default', 'urutan']);

        if ($standar->isEmpty()) {
            $this->addError(
                "aturan.$aturanIndex.komponen",
                'Jenis kegiatan ini belum punya standar komponen penilaian. Susun dulu di menu Jenis Kegiatan.'
            );

            return;
        }

        $komponen = collect($this->aturan[$aturanIndex]['komponen'] ?? []);
        $sudahAda = $komponen->pluck('komponen_penilaian_id')->map(fn ($id) => (int) $id)->all();

        foreach ($standar as $baris) {
            if (in_array((int) $baris->id, $sudahAda, true)) {
                continue;
            }

            $komponen->push([
                'id' => null,
                'komponen_penilaian_id' => (string) $baris->id,
                'nilai_min' => $baris->nilai_min_default,
                'nilai_maks' => $baris->nilai_maks_default,
                'urutan' => $komponen->count() + 1,
            ]);
        }

        $this->aturan[$aturanIndex]['komponen'] = $komponen->values()->toArray();
        $this->aturan[$aturanIndex]['perlu_penilaian'] = true;

        $this->muatKomponenPenilaian();
    }

    public function removeKomponen(int $aturanIndex, int $komponenIndex): void
    {
        unset($this->aturan[$aturanIndex]['komponen'][$komponenIndex]);
        $this->aturan[$aturanIndex]['komponen'] = array_values($this->aturan[$aturanIndex]['komponen'] ?? []);
    }

    public function updatedProdiId(): void
    {
        $this->selected_mata_kuliah_ids = [];
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['informasi', 'kegiatan', 'materi', 'penilaian', 'review'], true)) {
            $this->active_tab = $tab;
        }
    }

    public function setActiveAturan(int $index): void
    {
        if (isset($this->aturan[$index])) {
            $this->active_aturan_index = $index;
            $this->active_tab = 'materi';
        }
    }

    public function setActivePenilaian(int $index): void
    {
        if (isset($this->aturan[$index])) {
            $this->active_aturan_index = $index;
            $this->active_tab = 'penilaian';
        }
    }

    #[On('select-value')]
    public function selectValue($selected): void
    {
        $model = $selected['model'] ?? null;

        if (in_array($model, ['koordinator_id', 'asisten_koordinator_id'], true)) {
            $this->{$model} = $selected['value'] ?: null;
        }
    }

    #[On('multi-select-value')]
    public function multiSelectValue($selected): void
    {
        $model = $selected['model'] ?? null;

        if (! is_string($model) || ! property_exists($this, $model)) {
            return;
        }

        $this->$model = collect($selected['value'] ?? [])
            ->map(fn ($value) => (string) $value)
            ->values()
            ->toArray();
    }

    public function addAturan(): void
    {
        $this->aturan[] = [
            'id' => null,
            'jenis_kegiatan_id' => '',
            'durasi_menit' => 100,
            'jumlah_mahasiswa_per_kelompok' => null,
            'perlu_kelompok' => true,
            'perlu_presensi' => true,
            'perlu_logbook' => false,
            'perlu_penilaian' => false,
            'urutan' => count($this->aturan) + 1,
            'komponen' => [],
            'materi' => [$this->emptyMateri()],
        ];

        $this->active_aturan_index = count($this->aturan) - 1;
    }

    public function removeAturan($index): void
    {
        unset($this->aturan[$index]);
        $this->aturan = array_values($this->aturan);
        $this->active_aturan_index = min($this->active_aturan_index, max(count($this->aturan) - 1, 0));
    }

    public function addMateri($aturanIndex): void
    {
        $this->aturan[$aturanIndex]['materi'][] = $this->emptyMateri(count($this->aturan[$aturanIndex]['materi'] ?? []) + 1);
    }

    public function removeMateri($aturanIndex, $materiIndex): void
    {
        unset($this->aturan[$aturanIndex]['materi'][$materiIndex]);
        $this->aturan[$aturanIndex]['materi'] = array_values($this->aturan[$aturanIndex]['materi']);
    }

    public function addRinci($aturanIndex, $materiIndex): void
    {
        $rinci = $this->aturan[$aturanIndex]['materi'][$materiIndex]['rinci'] ?? [];
        $pertemuanKe = collect($rinci)->max(fn ($item) => (int) ($item['pertemuan_ke'] ?? 0)) + 1;

        $this->aturan[$aturanIndex]['materi'][$materiIndex]['rinci'][] = $this->emptyRinci(
            $aturanIndex,
            count($rinci) + 1,
            $pertemuanKe
        );
    }

    public function removeRinci($aturanIndex, $materiIndex, $rinciIndex): void
    {
        unset($this->aturan[$aturanIndex]['materi'][$materiIndex]['rinci'][$rinciIndex]);
        $this->aturan[$aturanIndex]['materi'][$materiIndex]['rinci'] = array_values($this->aturan[$aturanIndex]['materi'][$materiIndex]['rinci']);
    }

    public function applyTemplateStandar(): void
    {
        $existingJenisIds = collect($this->aturan)->pluck('jenis_kegiatan_id')->filter()->map(fn ($id) => (int) $id);
        $hasOnlyEmptyInitial = count($this->aturan) === 1 && empty($this->aturan[0]['jenis_kegiatan_id']);

        if ($hasOnlyEmptyInitial) {
            $this->aturan = [];
            $existingJenisIds = collect();
        }

        // Seluruh standar penilaian diambil sekali lalu dikelompokkan, bukan satu query per
        // jenis kegiatan di dalam loop.
        $standarPerJenis = KomponenPenilaian::query()
            ->whereIn('jenis_kegiatan_id', $this->jenis_kegiatan->pluck('id'))
            ->aktif()
            ->orderBy('urutan')
            ->get(['id', 'jenis_kegiatan_id', 'nilai_min_default', 'nilai_maks_default', 'urutan'])
            ->groupBy('jenis_kegiatan_id');

        foreach ($this->jenis_kegiatan as $jenis) {
            if ($existingJenisIds->contains((int) $jenis->id)) {
                continue;
            }

            $standar = $standarPerJenis->get($jenis->id, collect());

            $this->aturan[] = [
                'id' => null,
                'jenis_kegiatan_id' => $jenis->id,
                'durasi_menit' => $jenis->durasi_menit_default,
                'jumlah_mahasiswa_per_kelompok' => null,
                'perlu_kelompok' => true,
                'perlu_presensi' => true,
                'perlu_logbook' => false,
                'perlu_penilaian' => $standar->isNotEmpty(),
                'urutan' => count($this->aturan) + 1,
                'komponen' => $standar
                    ->values()
                    ->map(fn (KomponenPenilaian $baris, int $urutan) => [
                        'id' => null,
                        'komponen_penilaian_id' => (string) $baris->id,
                        'nilai_min' => $baris->nilai_min_default,
                        'nilai_maks' => $baris->nilai_maks_default,
                        'urutan' => $urutan + 1,
                    ])
                    ->all(),
                'materi' => [$this->emptyMateri()],
            ];
        }

        $this->active_aturan_index = 0;
        $this->active_tab = 'kegiatan';
        $this->muatKomponenPenilaian();
    }

    public function copyFromBlok(): void
    {
        $this->copy_success_message = null;
        $this->resetErrorBag('copy_blok_id');

        if ($this->edit_id || empty($this->copy_blok_id)) {
            $this->addError('copy_blok_id', 'Pilih blok sumber terlebih dahulu.');

            return;
        }

        $source = Blok::with([
            'aturan_kegiatan_blok.materi_blok.materi_rinci_blok',
            'aturan_kegiatan_blok.komponen_penilaian_blok' => fn ($query) => $query
                ->whereHas('komponen_penilaian', fn ($master) => $master->aktif()),
        ])->find($this->copy_blok_id);

        if (! $source) {
            $this->addError('copy_blok_id', 'Blok sumber tidak ditemukan.');

            return;
        }

        $this->sks = $source->sks;
        $this->deskripsi = $this->deskripsi ?: $source->deskripsi;
        $this->aturan = $source->aturan_kegiatan_blok
            ->sortBy('urutan')
            ->map(fn (AturanKegiatanBlok $item) => [
                'id' => null,
                'jenis_kegiatan_id' => $item->jenis_kegiatan_id,
                'durasi_menit' => $item->durasi_menit,
                'jumlah_mahasiswa_per_kelompok' => $item->jumlah_mahasiswa_per_kelompok,
                'perlu_kelompok' => true,
                'perlu_presensi' => (bool) $item->perlu_presensi,
                'perlu_logbook' => (bool) $item->perlu_logbook,
                'perlu_penilaian' => (bool) $item->perlu_penilaian,
                'urutan' => $item->urutan,
                // Rubrik penilaian ikut disalin: komponen dan batas nilainya adalah bagian
                // dari susunan blok, bukan data semester seperti tanggal rencana.
                'komponen' => $item->komponen_penilaian_blok
                    ->sortBy('urutan')
                    ->map(fn (KomponenPenilaianBlok $komponen) => [
                        'id' => null,
                        'komponen_penilaian_id' => (string) $komponen->komponen_penilaian_id,
                        'nilai_min' => $komponen->nilai_min,
                        'nilai_maks' => $komponen->nilai_maks,
                        'urutan' => $komponen->urutan,
                    ])
                    ->values()
                    ->toArray(),
                'materi' => $item->materi_blok
                    ->sortBy('urutan')
                    ->map(fn (MateriBlok $materi) => [
                        'id' => null,
                        'judul' => $materi->judul,
                        'deskripsi' => $materi->deskripsi,
                        'capaian_pembelajaran' => $materi->capaian_pembelajaran,
                        'urutan' => $materi->urutan,
                        'status' => $materi->status,
                        'rinci' => $materi->materi_rinci_blok
                            ->sortBy('urutan')
                            ->map(fn (MateriRinciBlok $rinci) => [
                                'id' => null,
                                'judul' => $rinci->judul,
                                'deskripsi' => $rinci->deskripsi,
                                'capaian_pembelajaran' => $rinci->capaian_pembelajaran,
                                'referensi' => $rinci->referensi,
                                'pertemuan_ke' => $rinci->pertemuan_ke,
                                // Tanggal rencana sengaja tidak disalin: nilainya spesifik per
                                // semester, menyalinnya menghasilkan jadwal salah secara diam-diam.
                                'tanggal_rencana' => null,
                                'jam_mulai_rencana' => $this->formatJam($rinci->jam_mulai_rencana),
                                'jam_selesai_rencana' => $this->formatJam($rinci->jam_selesai_rencana),
                                'jumlah_sesi' => $rinci->jumlah_sesi ?: 1,
                                'durasi_menit_per_sesi' => $rinci->durasi_menit_per_sesi ?: $item->durasi_menit,
                                'urutan' => $rinci->urutan,
                                'status' => $rinci->status,
                            ])
                            ->values()
                            ->toArray(),
                    ])
                    ->values()
                    ->toArray(),
            ])
            ->values()
            ->toArray();

        if (empty($this->aturan)) {
            $this->addAturan();
        }

        foreach ($this->aturan as $index => $aturan) {
            if (empty($aturan['materi'])) {
                $this->aturan[$index]['materi'] = [$this->emptyMateri()];
            }

            $this->aturan[$index]['perlu_kelompok'] = true;
        }

        $this->active_aturan_index = 0;
        $this->active_tab = 'review';
        $this->save_attempted = false;
        $this->resetErrorBag();
        $this->muatKomponenPenilaian();
        $this->copy_success_message = 'Struktur blok berhasil disalin. Silakan lengkapi identitas blok dan cek kembali tab Review.';
    }

    private function emptyMateri(int $urutan = 1): array
    {
        return [
            'id' => null,
            'judul' => '',
            'deskripsi' => '',
            'capaian_pembelajaran' => '',
            'urutan' => $urutan,
            'status' => 'aktif',
            'rinci' => [],
        ];
    }

    private function emptyRinci(int $aturanIndex, int $urutan = 1, int|string $pertemuanKe = ''): array
    {
        return [
            'id' => null,
            'judul' => '',
            'deskripsi' => '',
            'capaian_pembelajaran' => '',
            'referensi' => '',
            'pertemuan_ke' => $pertemuanKe,
            'tanggal_rencana' => null,
            'jam_mulai_rencana' => null,
            'jam_selesai_rencana' => null,
            'jumlah_sesi' => 1,
            'durasi_menit_per_sesi' => $this->aturanDurasi($aturanIndex),
            'urutan' => $urutan,
            'status' => 'aktif',
        ];
    }

    /**
     * Potong detik dari kolom time agar cocok dengan input type="time".
     */
    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }

    private function aturanDurasi($aturanIndex): ?int
    {
        $durasi = $this->aturan[$aturanIndex]['durasi_menit'] ?? null;

        return $durasi ? (int) $durasi : null;
    }

    private function syncJamSelesai(int $aturanIndex, int $materiIndex, int $rinciIndex): void
    {
        $rinci = $this->aturan[$aturanIndex]['materi'][$materiIndex]['rinci'][$rinciIndex] ?? null;

        if (! is_array($rinci)) {
            return;
        }

        $jamMulai = $rinci['jam_mulai_rencana'] ?? null;
        $jumlahSesi = (int) ($rinci['jumlah_sesi'] ?? 0);
        $durasiPerSesi = (int) ($rinci['durasi_menit_per_sesi'] ?? 0);
        $durasi = $jumlahSesi * $durasiPerSesi;

        $this->aturan[$aturanIndex]['materi'][$materiIndex]['rinci'][$rinciIndex]['jam_selesai_rencana'] =
            is_string($jamMulai)
                && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $jamMulai)
                && $durasi > 0
                ? Carbon::createFromFormat('H:i', $jamMulai)->addMinutes($durasi)->format('H:i')
                : null;
    }

    private function normalizeAturanBeforeSave(): void
    {
        foreach ($this->aturan as $aturanIndex => $aturan) {
            $this->aturan[$aturanIndex]['perlu_kelompok'] = true;
            $this->aturan[$aturanIndex]['jumlah_mahasiswa_per_kelompok'] = null;

            if (! isset($aturan['komponen']) || ! is_array($aturan['komponen'])) {
                $this->aturan[$aturanIndex]['komponen'] = [];
            }

        }
    }

    private function failOnMateriTab(int $aturanIndex, string $key, string $message): void
    {
        $this->active_tab = 'materi';
        $this->active_aturan_index = $aturanIndex;
        $this->addError($key, $message);
    }

    private function failOnPenilaianTab(int $aturanIndex, string $key, string $message): void
    {
        $this->active_tab = 'penilaian';
        $this->active_aturan_index = $aturanIndex;
        $this->addError($key, $message);
    }

    /**
     * Menolak penyimpanan bila komponen yang hendak dibuang dari rubrik sudah punya nilai.
     *
     * `nilai_pertemuan_blok` memakai cascade ke `komponen_penilaian_blok` supaya hapus
     * permanen blok dan `migrate:fresh` tetap jalan, jadi pengaman terhadap kehilangan
     * nilai secara diam-diam ada di sini, bukan di foreign key.
     *
     * @param  array<int, array<string, mixed>>  $aturanPayload
     */
    private function lolosPengecekanNilaiTersimpan(array $aturanPayload): bool
    {
        if (! $this->edit_id) {
            return true;
        }

        $aturanIds = AturanKegiatanBlok::where('blok_id', $this->edit_id)->pluck('id');

        if ($aturanIds->isEmpty()) {
            return true;
        }

        $bernilai = KomponenPenilaianBlok::query()
            ->whereIn('aturan_kegiatan_blok_id', $aturanIds)
            ->has('nilai_pertemuan_blok')
            ->with('komponen_penilaian:id,nama')
            ->get(['id', 'aturan_kegiatan_blok_id', 'komponen_penilaian_id']);

        if ($bernilai->isEmpty()) {
            return true;
        }

        // Komponen yang akan tetap ada, dikelompokkan per aturan yang masih dikirim form.
        $dipertahankan = [];

        foreach ($aturanPayload as $aturanIndex => $aturan) {
            if (empty($aturan['id'])) {
                continue;
            }

            $dipertahankan[(int) $aturan['id']] = [
                'index' => (int) $aturanIndex,
                'komponen' => collect($aturan['komponen'] ?? [])
                    ->pluck('komponen_penilaian_id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            ];
        }

        foreach ($bernilai as $baris) {
            $nama = $baris->komponen_penilaian?->nama ?: 'Komponen';
            $aturan = $dipertahankan[$baris->aturan_kegiatan_blok_id] ?? null;

            if (! $aturan) {
                $this->active_tab = 'penilaian';
                $this->addError('aturan', 'Kegiatan yang memuat komponen "'.$nama.'" tidak dapat dihapus karena nilainya sudah terisi.');

                return false;
            }

            if (! in_array((int) $baris->komponen_penilaian_id, $aturan['komponen'], true)) {
                $this->failOnPenilaianTab(
                    $aturan['index'],
                    'aturan.'.$aturan['index'].'.komponen',
                    'Komponen "'.$nama.'" tidak dapat dibuang karena nilainya sudah terisi. Hapus nilainya lebih dulu bila memang harus diubah.'
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Sinkronisasi rubrik penilaian satu kegiatan.
     *
     * `komponen_penilaian_blok` memakai soft delete DAN unique key, jadi baris disimpan
     * lewat `withTrashed()->firstOrNew()` lalu `restore()`. `updateOrCreate` akan menabrak
     * unique index bila komponen yang sama pernah dibuang, karena baris soft-deleted tetap
     * menempati index itu.
     *
     * Komponen yang tidak lagi dikirim form di-soft-delete, bukan dihapus permanen, supaya
     * nilai yang sudah ada tidak ikut ter-cascade. `lolosPengecekanNilaiTersimpan()` sudah
     * menolak lebih dulu bila komponen yang dibuang memang punya nilai.
     *
     * @param  array<int, array<string, mixed>>  $komponen
     */
    private function simpanKomponenPenilaian(AturanKegiatanBlok $aturan, array $komponen): void
    {
        $savedIds = [];

        foreach ($komponen as $index => $baris) {
            $model = KomponenPenilaianBlok::withTrashed()->firstOrNew([
                'aturan_kegiatan_blok_id' => $aturan->id,
                'komponen_penilaian_id' => (int) $baris['komponen_penilaian_id'],
            ]);

            $model->fill([
                'nilai_min' => $baris['nilai_min'],
                'nilai_maks' => $baris['nilai_maks'],
                'urutan' => $baris['urutan'] ?: $index + 1,
            ]);

            if ($model->trashed()) {
                $model->restore();
            }

            $model->save();

            $savedIds[] = $model->id;
        }

        KomponenPenilaianBlok::where('aturan_kegiatan_blok_id', $aturan->id)
            ->when($savedIds !== [], fn ($query) => $query->whereNotIn('id', $savedIds))
            ->delete();
    }

    private function tabForValidationErrors(array $keys): string
    {
        foreach ($keys as $key) {
            if (str_starts_with($key, 'aturan.')) {
                $segments = explode('.', $key);

                if (str_contains($key, '.materi.')) {
                    $this->active_aturan_index = isset($segments[1]) ? (int) $segments[1] : $this->active_aturan_index;

                    return 'materi';
                }

                if (str_contains($key, '.komponen')) {
                    $this->active_aturan_index = isset($segments[1]) ? (int) $segments[1] : $this->active_aturan_index;

                    return 'penilaian';
                }

                return 'kegiatan';
            }
        }

        return 'informasi';
    }

    public function saveCurrentTab()
    {
        if ($this->active_tab === 'review') {
            return $this->save();
        }

        $this->saveActiveTab();
        $this->save_success_message = 'Data pada tab ini berhasil disimpan.';
    }

    public function saveAndContinue()
    {
        if ($this->active_tab === 'review') {
            return $this->save();
        }

        $this->saveActiveTab();

        $tabs = ['informasi', 'kegiatan', 'materi', 'penilaian', 'review'];
        $this->active_tab = $tabs[array_search($this->active_tab, $tabs, true) + 1];
        $this->save_success_message = 'Data berhasil disimpan. Silakan lanjutkan ke tab berikutnya.';
    }

    public function saveAndNextKegiatan(): void
    {
        if (! in_array($this->active_tab, ['materi', 'penilaian'], true) || $this->aturan === []) {
            return;
        }

        $savedTab = $this->active_tab;
        $this->saveActiveTab(true);

        $currentIndex = min(max($this->active_aturan_index, 0), count($this->aturan) - 1);
        $this->active_aturan_index = ($currentIndex + 1) % count($this->aturan);
        $this->save_success_message = ($savedTab === 'materi' ? 'Materi' : 'Penilaian').' berhasil disimpan. Silakan lanjutkan mengisi kegiatan berikutnya.';
    }

    private function saveActiveTab(bool $onlyCurrentKegiatan = false): void
    {
        $this->save_attempted = true;
        $this->save_success_message = null;
        $this->normalizeAturanBeforeSave();
        $originalEditId = $this->edit_id;
        $originalAturan = $this->aturan;

        try {
            DB::transaction(function () use ($onlyCurrentKegiatan) {
                $this->simpanInformasi();

                if (in_array($this->active_tab, ['kegiatan', 'materi', 'penilaian'], true)) {
                    $this->simpanKegiatan();
                }

                if (in_array($this->active_tab, ['materi', 'penilaian'], true)) {
                    $this->simpanMateri($onlyCurrentKegiatan ? $this->active_aturan_index : null);
                }

                if ($this->active_tab === 'penilaian') {
                    $this->simpanPenilaian($onlyCurrentKegiatan ? $this->active_aturan_index : null);
                }
            });
        } catch (ValidationException $exception) {
            $this->edit_id = $originalEditId;
            $this->aturan = $originalAturan;
            $this->active_tab = $this->tabForValidationErrors($exception->validator->errors()->keys());

            throw $exception;
        } catch (\Throwable $exception) {
            $this->edit_id = $originalEditId;
            $this->aturan = $originalAturan;

            throw $exception;
        }

        $this->save_attempted = false;
        $this->resetErrorBag();
    }

    private function simpanInformasi(): void
    {
        $payload = $this->validate([
            'prodi_id' => ['required', 'exists:prodi,id_prodi'],
            'semester_id' => ['required', 'exists:semester,id_semester'],
            'koordinator_id' => ['required', 'exists:dosen,id_dosen'],
            'asisten_koordinator_id' => ['required', 'different:koordinator_id', 'exists:dosen,id_dosen'],
            'selected_kontributor_ids' => ['array'],
            'selected_kontributor_ids.*' => [
                'integer',
                'distinct',
                'exists:dosen,id_dosen',
                Rule::notIn(array_filter([$this->koordinator_id, $this->asisten_koordinator_id])),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'sks' => ['required', 'numeric', 'min:0.5', 'max:99.9'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'deskripsi' => ['nullable', 'string'],
            'selected_mata_kuliah_ids' => ['array'],
            'selected_mata_kuliah_ids.*' => ['integer', 'exists:mata_kuliah,id'],
        ], [
            'prodi_id.required' => 'Program studi wajib dipilih.',
            'semester_id.required' => 'Semester wajib dipilih.',
            'koordinator_id.required' => 'Koordinator blok wajib dipilih.',
            'asisten_koordinator_id.required' => 'Asisten koordinator blok wajib dipilih.',
            'asisten_koordinator_id.different' => 'Asisten koordinator harus berbeda dari koordinator.',
            'selected_kontributor_ids.*.distinct' => 'Kontributor tidak boleh duplikat.',
            'selected_kontributor_ids.*.not_in' => 'Kontributor harus berbeda dari koordinator dan asisten koordinator.',
            'nama.required' => 'Nama blok wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        $selectedIds = collect($payload['selected_mata_kuliah_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isNotEmpty() && MataKuliah::query()
            ->whereIn('id', $selectedIds)
            ->where(fn ($query) => $query
                ->where('prodi_id', '!=', $this->prodi_id)
                ->orWhere('status', '!=', 'aktif'))
            ->exists()) {
            $this->addError('selected_mata_kuliah_ids', 'Mata kuliah harus aktif dan berada pada program studi yang sama dengan blok.');

            throw ValidationException::withMessages($this->getErrorBag()->toArray());
        }

        DB::transaction(function () use ($payload, $selectedIds) {
            $blokPayload = collect($payload)->except([
                'koordinator_id',
                'asisten_koordinator_id',
                'selected_kontributor_ids',
                'selected_mata_kuliah_ids',
            ])->toArray();
            $blokPayload['status'] = 'aktif';
            if (! $this->edit_id) {
                $blokPayload['kode'] = bin2hex(random_bytes(16));
            }
            $blok = Blok::updateOrCreate(['id' => $this->edit_id], $blokPayload);
            $this->edit_id = $blok->id;
            $this->simpanPengelola($blok, $payload);

            MataKuliah::where('blok_id', $blok->id)
                ->when($selectedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $selectedIds))
                ->update(['blok_id' => null]);

            if ($selectedIds->isNotEmpty()) {
                MataKuliah::whereIn('id', $selectedIds)
                    ->where('prodi_id', $blok->prodi_id)
                    ->where('status', 'aktif')
                    ->update(['blok_id' => $blok->id]);
            }
        });
    }

    private function simpanPengelola(Blok $blok, array $payload): void
    {
        $kontributorIds = collect($payload['selected_kontributor_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $now = now();
        $rows = collect([
            ['dosen_id' => (int) $payload['koordinator_id'], 'jabatan' => 'koordinator'],
            ['dosen_id' => (int) $payload['asisten_koordinator_id'], 'jabatan' => 'asisten_koordinator'],
        ])->concat($kontributorIds->map(fn (int $id) => [
            'dosen_id' => $id,
            'jabatan' => 'kontributor',
        ]))->map(fn (array $row) => $row + [
            'blok_id' => $blok->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        PengelolaBlok::where('blok_id', $blok->id)->delete();
        PengelolaBlok::insert($rows);
    }

    private function simpanKegiatan(): void
    {
        $payload = $this->validate([
            'aturan' => ['required', 'array', 'min:1'],
            'aturan.*.id' => ['nullable', 'integer'],
            'aturan.*.jenis_kegiatan_id' => ['required', 'exists:jenis_kegiatan,id'],
            'aturan.*.durasi_menit' => ['required', 'integer', 'min:1', 'max:1440'],
            'aturan.*.perlu_presensi' => ['boolean'],
            'aturan.*.perlu_logbook' => ['boolean'],
            'aturan.*.perlu_penilaian' => ['boolean'],
            'aturan.*.urutan' => ['required', 'integer', 'min:1'],
        ], [
            'aturan.*.jenis_kegiatan_id.required' => 'Jenis kegiatan wajib dipilih.',
            'aturan.*.durasi_menit.required' => 'Durasi menit wajib diisi.',
        ]);

        $jenisIds = collect($payload['aturan'])->pluck('jenis_kegiatan_id')->filter();
        if ($jenisIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['aturan' => 'Jenis kegiatan tidak boleh duplikat dalam satu blok.']);
        }

        if (! $this->lolosPengecekanNilaiTersimpan($this->aturan)) {
            throw ValidationException::withMessages($this->getErrorBag()->toArray());
        }

        DB::transaction(function () use ($payload) {
            $savedIds = [];

            foreach ($payload['aturan'] as $index => $aturan) {
                $model = AturanKegiatanBlok::updateOrCreate(
                    ['id' => $aturan['id'] ?: null],
                    [
                        'blok_id' => $this->edit_id,
                        'jenis_kegiatan_id' => $aturan['jenis_kegiatan_id'],
                        'durasi_menit' => $aturan['durasi_menit'],
                        'jumlah_mahasiswa_per_kelompok' => null,
                        'perlu_kelompok' => true,
                        'perlu_presensi' => (bool) $aturan['perlu_presensi'],
                        'perlu_logbook' => (bool) $aturan['perlu_logbook'],
                        'perlu_penilaian' => (bool) ($aturan['perlu_penilaian'] ?? false),
                        'urutan' => $aturan['urutan'] ?: $index + 1,
                    ]
                );
                $this->aturan[$index]['id'] = $model->id;
                $savedIds[] = $model->id;
            }

            AturanKegiatanBlok::where('blok_id', $this->edit_id)
                ->whereNotIn('id', $savedIds)
                ->get()
                ->each(function (AturanKegiatanBlok $aturan) {
                    foreach ($aturan->materi_blok as $materi) {
                        $materi->materi_rinci_blok()->delete();
                        $materi->delete();
                    }
                    $aturan->komponen_penilaian_blok()->delete();
                    $aturan->delete();
                });
        });
    }

    private function simpanMateri(?int $aturanIndex = null): void
    {
        $aturanKey = $aturanIndex ?? '*';
        $payload = $this->validate([
            "aturan.{$aturanKey}.materi" => ['required', 'array', 'min:1'],
            "aturan.{$aturanKey}.materi.*.id" => ['nullable', 'integer'],
            "aturan.{$aturanKey}.materi.*.judul" => ['required', 'string', 'max:255'],
            "aturan.{$aturanKey}.materi.*.deskripsi" => ['nullable', 'string'],
            "aturan.{$aturanKey}.materi.*.capaian_pembelajaran" => ['nullable', 'string'],
            "aturan.{$aturanKey}.materi.*.urutan" => ['required', 'integer', 'min:1'],
            "aturan.{$aturanKey}.materi.*.status" => ['required', Rule::in(['aktif', 'nonaktif'])],
            "aturan.{$aturanKey}.materi.*.rinci" => ['array'],
            "aturan.{$aturanKey}.materi.*.rinci.*.id" => ['nullable', 'integer'],
            "aturan.{$aturanKey}.materi.*.rinci.*.judul" => ['required', 'string', 'max:255'],
            "aturan.{$aturanKey}.materi.*.rinci.*.deskripsi" => ['nullable', 'string'],
            "aturan.{$aturanKey}.materi.*.rinci.*.capaian_pembelajaran" => ['nullable', 'string'],
            "aturan.{$aturanKey}.materi.*.rinci.*.referensi" => ['nullable', 'string'],
            "aturan.{$aturanKey}.materi.*.rinci.*.pertemuan_ke" => ['nullable', 'integer', 'min:1', 'max:100'],
            "aturan.{$aturanKey}.materi.*.rinci.*.tanggal_rencana" => ['nullable', 'date_format:Y-m-d'],
            "aturan.{$aturanKey}.materi.*.rinci.*.jam_mulai_rencana" => ['nullable', 'date_format:H:i'],
            "aturan.{$aturanKey}.materi.*.rinci.*.jam_selesai_rencana" => ['nullable', 'date_format:H:i'],
            "aturan.{$aturanKey}.materi.*.rinci.*.jumlah_sesi" => ['required', 'integer', 'min:1', 'max:20'],
            "aturan.{$aturanKey}.materi.*.rinci.*.durasi_menit_per_sesi" => ['required', 'integer', 'min:1', 'max:1440'],
            "aturan.{$aturanKey}.materi.*.rinci.*.urutan" => ['required', 'integer', 'min:1'],
            "aturan.{$aturanKey}.materi.*.rinci.*.status" => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            "aturan.{$aturanKey}.materi.*.judul.required" => 'Judul pokok materi wajib diisi.',
            "aturan.{$aturanKey}.materi.*.rinci.*.judul.required" => 'Judul materi rinci wajib diisi.',
        ]);

        foreach ($payload['aturan'] as $aturanIndex => $aturan) {
            foreach ($aturan['materi'] as $materiIndex => $materi) {
                foreach ($materi['rinci'] ?? [] as $rinciIndex => $rinci) {
                    $prefix = "aturan.$aturanIndex.materi.$materiIndex.rinci.$rinciIndex";
                    if (! empty($rinci['tanggal_rencana']) && $this->tanggal_mulai && $rinci['tanggal_rencana'] < $this->tanggal_mulai) {
                        throw ValidationException::withMessages(["$prefix.tanggal_rencana" => 'Tanggal rencana tidak boleh sebelum tanggal mulai blok.']);
                    }
                    if (! empty($rinci['tanggal_rencana']) && $this->tanggal_selesai && $rinci['tanggal_rencana'] > $this->tanggal_selesai) {
                        throw ValidationException::withMessages(["$prefix.tanggal_rencana" => 'Tanggal rencana tidak boleh setelah tanggal selesai blok.']);
                    }
                }
            }
        }

        DB::transaction(function () use ($payload) {
            foreach ($payload['aturan'] as $aturanIndex => $aturan) {
                $aturanId = $this->aturan[$aturanIndex]['id'];
                $savedMateriIds = [];

                foreach ($aturan['materi'] as $materiIndex => $materi) {
                    $model = $materi['id']
                        ? MateriBlok::query()
                            ->where('aturan_kegiatan_blok_id', $aturanId)
                            ->where('id_materi_blok', $materi['id'])
                            ->firstOrFail()
                        : new MateriBlok;

                    $model->fill([
                        'aturan_kegiatan_blok_id' => $aturanId,
                        'judul' => $materi['judul'],
                        'deskripsi' => $materi['deskripsi'] ?: null,
                        'capaian_pembelajaran' => $materi['capaian_pembelajaran'] ?: null,
                        'urutan' => $materi['urutan'] ?: $materiIndex + 1,
                        'status' => $materi['status'],
                    ])->save();

                    $this->aturan[$aturanIndex]['materi'][$materiIndex]['id'] = $model->id_materi_blok;
                    $savedMateriIds[] = $model->id_materi_blok;
                    $savedRinciIds = [];

                    foreach ($materi['rinci'] ?? [] as $rinciIndex => $rinci) {
                        $rinciModel = $rinci['id']
                            ? MateriRinciBlok::query()
                                ->where('materi_blok_id', $model->id_materi_blok)
                                ->where('id_materi_rinci_blok', $rinci['id'])
                                ->firstOrFail()
                            : new MateriRinciBlok;

                        $rinciModel->fill([
                            'materi_blok_id' => $model->id_materi_blok,
                            'judul' => $rinci['judul'],
                            'deskripsi' => $rinci['deskripsi'] ?: null,
                            'capaian_pembelajaran' => $rinci['capaian_pembelajaran'] ?: null,
                            'referensi' => $rinci['referensi'] ?: null,
                            'pertemuan_ke' => $rinci['pertemuan_ke'] ?: null,
                            'tanggal_rencana' => $rinci['tanggal_rencana'] ?: null,
                            'jam_mulai_rencana' => $rinci['jam_mulai_rencana'] ?: null,
                            'jam_selesai_rencana' => $rinci['jam_selesai_rencana'] ?: null,
                            'jumlah_sesi' => $rinci['jumlah_sesi'] ?: 1,
                            'durasi_menit_per_sesi' => $rinci['durasi_menit_per_sesi'],
                            'urutan' => $rinci['urutan'] ?: $rinciIndex + 1,
                            'status' => $rinci['status'],
                        ])->save();

                        $this->aturan[$aturanIndex]['materi'][$materiIndex]['rinci'][$rinciIndex]['id'] = $rinciModel->id_materi_rinci_blok;
                        $savedRinciIds[] = $rinciModel->id_materi_rinci_blok;
                    }

                    MateriRinciBlok::where('materi_blok_id', $model->id_materi_blok)
                        ->when($savedRinciIds !== [], fn ($query) => $query->whereNotIn('id_materi_rinci_blok', $savedRinciIds))
                        ->delete();
                }

                MateriBlok::where('aturan_kegiatan_blok_id', $aturanId)
                    ->when($savedMateriIds !== [], fn ($query) => $query->whereNotIn('id_materi_blok', $savedMateriIds))
                    ->get()
                    ->each(function (MateriBlok $materi) {
                        $materi->materi_rinci_blok()->delete();
                        $materi->delete();
                    });
            }
        });
    }

    private function simpanPenilaian(?int $aturanIndex = null): void
    {
        $aturanKey = $aturanIndex ?? '*';
        $payload = $this->validate([
            "aturan.{$aturanKey}.komponen" => ['array'],
            "aturan.{$aturanKey}.komponen.*.komponen_penilaian_id" => ['required', 'exists:komponen_penilaian,id'],
            "aturan.{$aturanKey}.komponen.*.nilai_min" => ['required', 'numeric', 'min:0', 'max:9999'],
            "aturan.{$aturanKey}.komponen.*.nilai_maks" => ['required', 'numeric', 'min:0', 'max:9999'],
            "aturan.{$aturanKey}.komponen.*.urutan" => ['required', 'integer', 'min:1'],
        ]);

        foreach ($payload['aturan'] as $index => $aturan) {
            $komponen = collect($aturan['komponen'] ?? []);
            if ($komponen->pluck('komponen_penilaian_id')->filter()->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(["aturan.$index.komponen" => 'Komponen penilaian tidak boleh duplikat dalam satu kegiatan.']);
            }
            if (! empty($this->aturan[$index]['perlu_penilaian']) && $komponen->isEmpty()) {
                throw ValidationException::withMessages(["aturan.$index.komponen" => 'Kegiatan yang ditandai perlu penilaian harus punya minimal satu komponen.']);
            }
            foreach ($komponen as $komponenIndex => $baris) {
                if ((float) $baris['nilai_maks'] <= (float) $baris['nilai_min']) {
                    throw ValidationException::withMessages([
                        "aturan.$index.komponen.$komponenIndex.nilai_maks" => 'Nilai maksimum harus lebih besar dari nilai minimum.',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($payload) {
            foreach ($payload['aturan'] as $index => $aturan) {
                $this->simpanKomponenPenilaian(
                    AturanKegiatanBlok::findOrFail($this->aturan[$index]['id']),
                    $aturan['komponen'] ?? []
                );
            }
        });
    }

    public function save()
    {
        $this->save_attempted = true;
        $this->normalizeAturanBeforeSave();

        try {
            $payload = $this->validate([
                'prodi_id' => ['required', 'exists:prodi,id_prodi'],
                'semester_id' => ['required', 'exists:semester,id_semester'],
                'koordinator_id' => ['required', 'exists:dosen,id_dosen'],
                'asisten_koordinator_id' => ['required', 'different:koordinator_id', 'exists:dosen,id_dosen'],
                'selected_kontributor_ids' => ['array'],
                'selected_kontributor_ids.*' => [
                    'integer',
                    'distinct',
                    'exists:dosen,id_dosen',
                    Rule::notIn(array_filter([$this->koordinator_id, $this->asisten_koordinator_id])),
                ],
                'nama' => ['required', 'string', 'max:255'],
                'sks' => ['required', 'numeric', 'min:0.5', 'max:99.9'],
                'tanggal_mulai' => ['nullable', 'date'],
                'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
                'deskripsi' => ['nullable', 'string'],
                'selected_mata_kuliah_ids' => ['array'],
                'selected_mata_kuliah_ids.*' => ['integer', 'exists:mata_kuliah,id'],
                'aturan' => ['required', 'array', 'min:1'],
                'aturan.*.id' => ['nullable', 'integer'],
                'aturan.*.jenis_kegiatan_id' => ['required', 'exists:jenis_kegiatan,id'],
                'aturan.*.durasi_menit' => ['required', 'integer', 'min:1', 'max:1440'],
                'aturan.*.perlu_kelompok' => ['boolean'],
                'aturan.*.perlu_presensi' => ['boolean'],
                'aturan.*.perlu_logbook' => ['boolean'],
                'aturan.*.perlu_penilaian' => ['boolean'],
                'aturan.*.urutan' => ['required', 'integer', 'min:1'],
                'aturan.*.komponen' => ['array'],
                'aturan.*.komponen.*.id' => ['nullable', 'integer'],
                'aturan.*.komponen.*.komponen_penilaian_id' => ['required', 'exists:komponen_penilaian,id'],
                'aturan.*.komponen.*.nilai_min' => ['required', 'numeric', 'min:0', 'max:9999'],
                'aturan.*.komponen.*.nilai_maks' => ['required', 'numeric', 'min:0', 'max:9999'],
                'aturan.*.komponen.*.urutan' => ['required', 'integer', 'min:1'],
                'aturan.*.materi' => ['required', 'array', 'min:1'],
                'aturan.*.materi.*.id' => ['nullable', 'integer'],
                'aturan.*.materi.*.judul' => ['required', 'string', 'max:255'],
                'aturan.*.materi.*.deskripsi' => ['nullable', 'string'],
                'aturan.*.materi.*.capaian_pembelajaran' => ['nullable', 'string'],
                'aturan.*.materi.*.urutan' => ['required', 'integer', 'min:1'],
                'aturan.*.materi.*.status' => ['required', Rule::in(['aktif', 'nonaktif'])],
                'aturan.*.materi.*.rinci' => ['array'],
                'aturan.*.materi.*.rinci.*.id' => ['nullable', 'integer'],
                'aturan.*.materi.*.rinci.*.judul' => ['required', 'string', 'max:255'],
                'aturan.*.materi.*.rinci.*.deskripsi' => ['nullable', 'string'],
                'aturan.*.materi.*.rinci.*.capaian_pembelajaran' => ['nullable', 'string'],
                'aturan.*.materi.*.rinci.*.referensi' => ['nullable', 'string'],
                'aturan.*.materi.*.rinci.*.pertemuan_ke' => ['nullable', 'integer', 'min:1', 'max:100'],
                'aturan.*.materi.*.rinci.*.tanggal_rencana' => ['nullable', 'date_format:Y-m-d'],
                'aturan.*.materi.*.rinci.*.jam_mulai_rencana' => ['nullable', 'date_format:H:i'],
                'aturan.*.materi.*.rinci.*.jam_selesai_rencana' => ['nullable', 'date_format:H:i'],
                'aturan.*.materi.*.rinci.*.jumlah_sesi' => ['required', 'integer', 'min:1', 'max:20'],
                'aturan.*.materi.*.rinci.*.durasi_menit_per_sesi' => ['nullable', 'integer', 'min:1', 'max:1440'],
                'aturan.*.materi.*.rinci.*.urutan' => ['required', 'integer', 'min:1'],
                'aturan.*.materi.*.rinci.*.status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            ], [
                'prodi_id.required' => 'Program studi wajib dipilih.',
                'semester_id.required' => 'Semester wajib dipilih.',
                'koordinator_id.required' => 'Koordinator blok wajib dipilih.',
                'koordinator_id.exists' => 'Koordinator blok tidak valid.',
                'asisten_koordinator_id.required' => 'Asisten koordinator blok wajib dipilih.',
                'asisten_koordinator_id.different' => 'Asisten koordinator harus berbeda dari koordinator.',
                'asisten_koordinator_id.exists' => 'Asisten koordinator blok tidak valid.',
                'selected_kontributor_ids.*.distinct' => 'Kontributor tidak boleh duplikat.',
                'selected_kontributor_ids.*.not_in' => 'Kontributor harus berbeda dari koordinator dan asisten koordinator.',
                'selected_kontributor_ids.*.exists' => 'Kontributor tidak valid.',
                'nama.required' => 'Nama blok wajib diisi.',
                'sks.required' => 'SKS wajib diisi.',
                'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
                'selected_mata_kuliah_ids.*.exists' => 'Mata kuliah yang dipilih tidak valid.',
                'aturan.required' => 'Aturan kegiatan blok wajib diisi.',
                'aturan.*.jenis_kegiatan_id.required' => 'Jenis kegiatan wajib dipilih.',
                'aturan.*.durasi_menit.required' => 'Durasi menit wajib diisi.',
                'aturan.*.materi.required' => 'Materi pokok wajib diisi pada setiap kegiatan blok.',
                'aturan.*.materi.*.judul.required' => 'Judul pokok materi wajib diisi.',
                'aturan.*.komponen.*.komponen_penilaian_id.required' => 'Komponen penilaian wajib tersedia.',
                'aturan.*.komponen.*.komponen_penilaian_id.exists' => 'Komponen penilaian tidak ditemukan.',
                'aturan.*.komponen.*.nilai_min.required' => 'Nilai minimum komponen wajib diisi.',
                'aturan.*.komponen.*.nilai_maks.required' => 'Nilai maksimum komponen wajib diisi.',
                'aturan.*.komponen.*.urutan.required' => 'Urutan komponen wajib diisi.',
                'aturan.*.materi.*.rinci.*.judul.required' => 'Judul materi rinci wajib diisi.',
                'aturan.*.materi.*.rinci.*.jumlah_sesi.required' => 'Jumlah sesi wajib diisi.',
                'aturan.*.materi.*.rinci.*.tanggal_rencana.date_format' => 'Format tanggal rencana tidak valid.',
                'aturan.*.materi.*.rinci.*.jam_mulai_rencana.date_format' => 'Format jam mulai rencana tidak valid.',
                'aturan.*.materi.*.rinci.*.jam_selesai_rencana.date_format' => 'Format jam selesai rencana tidak valid.',
            ]);
        } catch (ValidationException $exception) {
            $this->active_tab = $this->tabForValidationErrors($exception->validator->errors()->keys());

            throw $exception;
        }

        $jenisIds = collect($payload['aturan'])->pluck('jenis_kegiatan_id')->filter();
        if ($jenisIds->duplicates()->isNotEmpty()) {
            $this->active_tab = 'kegiatan';
            $this->addError('aturan', 'Jenis kegiatan tidak boleh duplikat dalam satu blok.');

            return;
        }

        foreach ($payload['aturan'] as $aturanIndex => $aturan) {
            $komponen = collect($aturan['komponen'] ?? []);

            if ($komponen->pluck('komponen_penilaian_id')->filter()->duplicates()->isNotEmpty()) {
                $this->failOnPenilaianTab($aturanIndex, "aturan.$aturanIndex.komponen", 'Komponen penilaian tidak boleh duplikat dalam satu kegiatan.');

                return;
            }

            foreach ($komponen as $komponenIndex => $baris) {
                if ((float) $baris['nilai_maks'] <= (float) $baris['nilai_min']) {
                    $this->failOnPenilaianTab(
                        $aturanIndex,
                        "aturan.$aturanIndex.komponen.$komponenIndex.nilai_maks",
                        'Nilai maksimum harus lebih besar dari nilai minimum.'
                    );

                    return;
                }
            }

            if (! empty($aturan['perlu_penilaian']) && $komponen->isEmpty()) {
                $this->failOnPenilaianTab($aturanIndex, "aturan.$aturanIndex.komponen", 'Kegiatan yang ditandai perlu penilaian harus punya minimal satu komponen.');

                return;
            }
        }

        if (! $this->lolosPengecekanNilaiTersimpan($payload['aturan'])) {
            return;
        }

        $selectedMataKuliahIds = collect($payload['selected_mata_kuliah_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedMataKuliahIds->isNotEmpty()) {
            $invalidMataKuliah = MataKuliah::query()
                ->whereIn('id', $selectedMataKuliahIds)
                ->where(function ($query) {
                    $query->where('prodi_id', '!=', $this->prodi_id)
                        ->orWhere('status', '!=', 'aktif');
                })
                ->exists();

            if ($invalidMataKuliah) {
                $this->active_tab = 'informasi';
                $this->addError('selected_mata_kuliah_ids', 'Mata kuliah harus aktif dan berada pada program studi yang sama dengan blok.');

                return;
            }
        }

        $tanggalMulaiBlok = $payload['tanggal_mulai'] ?? null;
        $tanggalSelesaiBlok = $payload['tanggal_selesai'] ?? null;

        foreach ($payload['aturan'] as $aturanIndex => $aturan) {
            foreach ($aturan['materi'] ?? [] as $materiIndex => $materi) {
                foreach ($materi['rinci'] ?? [] as $rinciIndex => $rinci) {
                    $prefix = "aturan.$aturanIndex.materi.$materiIndex.rinci.$rinciIndex";

                    $tanggal = $rinci['tanggal_rencana'] ?? null;

                    if ($tanggal && $tanggalMulaiBlok && $tanggal < $tanggalMulaiBlok) {
                        $this->failOnMateriTab($aturanIndex, "$prefix.tanggal_rencana", 'Tanggal rencana tidak boleh sebelum tanggal mulai blok.');

                        return;
                    }

                    if ($tanggal && $tanggalSelesaiBlok && $tanggal > $tanggalSelesaiBlok) {
                        $this->failOnMateriTab($aturanIndex, "$prefix.tanggal_rencana", 'Tanggal rencana tidak boleh setelah tanggal selesai blok.');

                        return;
                    }

                    $jamMulai = $rinci['jam_mulai_rencana'] ?? null;
                    $jamSelesai = $rinci['jam_selesai_rencana'] ?? null;

                    if ($jamSelesai && ! $jamMulai) {
                        $this->failOnMateriTab($aturanIndex, "$prefix.jam_mulai_rencana", 'Jam mulai wajib diisi jika jam selesai diisi.');

                        return;
                    }

                    if ($jamMulai && $jamSelesai && $jamSelesai <= $jamMulai) {
                        $this->failOnMateriTab($aturanIndex, "$prefix.jam_selesai_rencana", 'Jam selesai harus lebih besar dari jam mulai.');

                        return;
                    }
                }
            }
        }

        DB::transaction(function () use ($payload) {
            $selectedMataKuliahIds = collect($payload['selected_mata_kuliah_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $blokPayload = collect($payload)->except([
                'aturan',
                'koordinator_id',
                'asisten_koordinator_id',
                'selected_kontributor_ids',
                'selected_mata_kuliah_ids',
            ])->toArray();

            if (! $this->edit_id) {
                $blokPayload['status'] = 'aktif';
                $blokPayload['kode'] = bin2hex(random_bytes(16));
            }

            $blok = Blok::updateOrCreate(['id' => $this->edit_id], $blokPayload);
            $savedIds = [];
            $this->simpanPengelola($blok, $payload);

            MataKuliah::where('blok_id', $blok->id)
                ->when(! empty($selectedMataKuliahIds), fn ($query) => $query->whereNotIn('id', $selectedMataKuliahIds))
                ->update(['blok_id' => null]);

            if (! empty($selectedMataKuliahIds)) {
                MataKuliah::whereIn('id', $selectedMataKuliahIds)
                    ->where('prodi_id', $blok->prodi_id)
                    ->where('status', 'aktif')
                    ->update(['blok_id' => $blok->id]);
            }

            foreach ($payload['aturan'] as $index => $aturan) {
                $aturanPayload = [
                    'blok_id' => $blok->id,
                    'jenis_kegiatan_id' => $aturan['jenis_kegiatan_id'],
                    'durasi_menit' => $aturan['durasi_menit'],
                    'jumlah_mahasiswa_per_kelompok' => null,
                    'perlu_kelompok' => true,
                    'perlu_presensi' => (bool) $aturan['perlu_presensi'],
                    'perlu_logbook' => (bool) $aturan['perlu_logbook'],
                    'perlu_penilaian' => (bool) ($aturan['perlu_penilaian'] ?? false),
                    'urutan' => $aturan['urutan'] ?: $index + 1,
                ];

                $model = AturanKegiatanBlok::updateOrCreate(
                    ['id' => $aturan['id'] ?: null],
                    $aturanPayload
                );

                $savedIds[] = $model->id;

                $this->simpanKomponenPenilaian($model, $aturan['komponen'] ?? []);

                $savedMateriIds = [];
                foreach ($aturan['materi'] ?? [] as $materiIndex => $materi) {
                    $materiModel = $materi['id']
                        ? MateriBlok::query()
                            ->where('aturan_kegiatan_blok_id', $model->id)
                            ->where('id_materi_blok', $materi['id'])
                            ->firstOrFail()
                        : new MateriBlok;

                    $materiModel->fill([
                        'aturan_kegiatan_blok_id' => $model->id,
                        'judul' => $materi['judul'],
                        'deskripsi' => $materi['deskripsi'] ?: null,
                        'capaian_pembelajaran' => $materi['capaian_pembelajaran'] ?: null,
                        'urutan' => $materi['urutan'] ?: $materiIndex + 1,
                        'status' => $materi['status'],
                    ]);
                    $materiModel->save();

                    $savedMateriIds[] = $materiModel->id_materi_blok;
                    $savedRinciIds = [];

                    foreach ($materi['rinci'] ?? [] as $rinciIndex => $rinci) {
                        $rinciModel = $rinci['id']
                            ? MateriRinciBlok::query()
                                ->where('materi_blok_id', $materiModel->id_materi_blok)
                                ->where('id_materi_rinci_blok', $rinci['id'])
                                ->firstOrFail()
                            : new MateriRinciBlok;

                        $rinciModel->fill(
                            [
                                'materi_blok_id' => $materiModel->id_materi_blok,
                                'judul' => $rinci['judul'],
                                'deskripsi' => $rinci['deskripsi'] ?: null,
                                'capaian_pembelajaran' => $rinci['capaian_pembelajaran'] ?: null,
                                'referensi' => $rinci['referensi'] ?: null,
                                'pertemuan_ke' => $rinci['pertemuan_ke'] ?: null,
                                'tanggal_rencana' => $rinci['tanggal_rencana'] ?: null,
                                'jam_mulai_rencana' => $rinci['jam_mulai_rencana'] ?: null,
                                'jam_selesai_rencana' => $rinci['jam_selesai_rencana'] ?: null,
                                'jumlah_sesi' => $rinci['jumlah_sesi'] ?: 1,
                                'durasi_menit_per_sesi' => $rinci['durasi_menit_per_sesi'],
                                'urutan' => $rinci['urutan'] ?: $rinciIndex + 1,
                                'status' => $rinci['status'],
                            ]
                        );
                        $rinciModel->save();

                        $savedRinciIds[] = $rinciModel->id_materi_rinci_blok;
                    }

                    MateriRinciBlok::where('materi_blok_id', $materiModel->id_materi_blok)
                        ->when(! empty($savedRinciIds), fn ($query) => $query->whereNotIn('id_materi_rinci_blok', $savedRinciIds))
                        ->delete();
                }

                MateriBlok::where('aturan_kegiatan_blok_id', $model->id)
                    ->when(! empty($savedMateriIds), fn ($query) => $query->whereNotIn('id_materi_blok', $savedMateriIds))
                    ->get()
                    ->each(function (MateriBlok $materi) {
                        $materi->materi_rinci_blok()->delete();
                        $materi->delete();
                    });
            }

            AturanKegiatanBlok::where('blok_id', $blok->id)
                ->when(! empty($savedIds), fn ($query) => $query->whereNotIn('id', $savedIds))
                ->get()
                ->each(function (AturanKegiatanBlok $aturan) {
                    foreach ($aturan->materi_blok as $materi) {
                        $materi->materi_rinci_blok()->delete();
                        $materi->delete();
                    }

                    $aturan->komponen_penilaian_blok()->delete();
                    $aturan->delete();
                });
        });

        session()->flash('success', $this->edit_id ? 'Berhasil mengubah data' : 'Berhasil menambah data');

        return $this->redirect(route('blok.index'), navigate: true);
    }
}; ?>

<form wire:submit="saveAndContinue">
    @csrf
    @php
        $totalMateri = collect($aturan)->sum(fn ($item) => count($item['materi'] ?? []));
        $totalRinci = collect($aturan)->sum(fn ($item) => collect($item['materi'] ?? [])->sum(fn ($materi) => count($materi['rinci'] ?? [])));
        $totalPertemuan = collect($aturan)->sum(fn ($item) => collect($item['materi'] ?? [])->sum(fn ($materi) => collect($materi['rinci'] ?? [])->sum(fn ($rinci) => (int) ($rinci['jumlah_sesi'] ?? 1))));
        $totalRinciTanpaTanggal = collect($aturan)->sum(fn ($item) => collect($item['materi'] ?? [])
            ->sum(fn ($materi) => collect($materi['rinci'] ?? [])->filter(fn ($rinci) => empty($rinci['tanggal_rencana']))->count()));
        $activeAturan = $aturan[$active_aturan_index] ?? null;
        $activeJenis = $activeAturan ? $jenis_kegiatan->firstWhere('id', (int) ($activeAturan['jenis_kegiatan_id'] ?? 0)) : null;
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h4 class="mb-0">{{ $edit_id ? 'Edit Blok' : 'Tambah Blok' }}</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-info-subtle text-info">{{ count($aturan) }} kegiatan</span>
                            <span class="badge bg-success-subtle text-success">{{ $totalRinci }} rincian</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs nav-tabs-custom mb-4" role="tablist">
                        <li class="nav-item">
                            <button type="button" class="nav-link {{ $active_tab === 'informasi' ? 'active' : '' }}" wire:click="setActiveTab('informasi')">
                                <i class="ri-information-line"></i> Informasi
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link {{ $active_tab === 'kegiatan' ? 'active' : '' }}" wire:click="setActiveTab('kegiatan')">
                                <i class="ri-list-check-2"></i> Kegiatan
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link {{ $active_tab === 'materi' ? 'active' : '' }}" wire:click="setActiveTab('materi')">
                                <i class="ri-book-open-line"></i> Materi
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link {{ $active_tab === 'penilaian' ? 'active' : '' }}" wire:click="setActiveTab('penilaian')">
                                <i class="ri-graduation-cap-line"></i> Penilaian
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link {{ $active_tab === 'review' ? 'active' : '' }}" wire:click="setActiveTab('review')">
                                <i class="ri-checkbox-circle-line"></i> Review
                            </button>
                        </li>
                    </ul>

                    <x-full-page-loading message="Memproses halaman blok..." />

                    @if ($save_attempted && $errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                            <div class="fw-semibold mb-1">
                                <i class="ri-error-warning-line"></i> Data blok belum lengkap.
                            </div>
                            <div class="mb-2">Periksa kembali isian yang ditandai sebelum menyimpan.</div>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($copy_success_message)
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                            <i class="ri-check-line"></i> {{ $copy_success_message }}
                        </div>
                    @endif

                    @if ($save_success_message)
                        <div class="alert alert-success alert-dismissible fade show" role="status">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                            <i class="ri-check-line"></i> {{ $save_success_message }}
                        </div>
                    @endif

                    @if ($active_tab === 'informasi')
                        @if (! $edit_id)
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="row g-3 align-items-end">
                                    <div class="col-lg-8">
                                        <label class="form-label">Salin Struktur dari Blok Sebelumnya</label>
                                        <select class="form-select" wire:model.live="copy_blok_id">
                                            <option value="">Pilih blok sumber</option>
                                            @foreach ($blok_copy_options as $blokOption)
                                                <option value="{{ $blokOption['id'] }}">{{ $blokOption['label'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('copy_blok_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-lg-4 text-lg-end">
                                        <button type="button" class="btn btn-soft-info" wire:click="copyFromBlok" wire:loading.attr="disabled" wire:target="copyFromBlok">
                                            <span wire:loading.remove wire:target="copyFromBlok"><i class="ri-file-copy-line"></i> Salin Struktur</span>
                                            <span wire:loading wire:target="copyFromBlok">Menyalin...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-xl-8">
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Program Studi</label>
                                        <select class="form-select" wire:model.live="prodi_id">
                                            <option value="">Pilih prodi</option>
                                            @foreach ($prodi as $item)
                                                <option value="{{ $item->id_prodi }}">{{ $item->kode }} - {{ $item->nama }}</option>
                                            @endforeach
                                        </select>
                                        @error('prodi_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Semester</label>
                                        <select class="form-select" wire:model="semester_id">
                                            <option value="">Pilih semester</option>
                                            @foreach ($semester as $item)
                                                <option value="{{ $item->id_semester }}">{{ ucfirst($item->nama) }} {{ $item->tahun }} ({{ $item->kode }})</option>
                                            @endforeach
                                        </select>
                                        @error('semester_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <livewire:dropdown.select-search
                                            :query="'App\Models\Dosen'"
                                            wire_model="koordinator_id"
                                            label="Koordinator Blok"
                                            colSearch="nama"
                                            colValue="id_dosen"
                                            :selected="$koordinator_id"
                                            conditions="status = 'aktif'"
                                            :key="'koordinator-'.$edit_id"
                                        />
                                        @error('koordinator_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <livewire:dropdown.select-search
                                            :query="'App\Models\Dosen'"
                                            wire_model="asisten_koordinator_id"
                                            label="Asisten Koordinator"
                                            colSearch="nama"
                                            colValue="id_dosen"
                                            :selected="$asisten_koordinator_id"
                                            conditions="status = 'aktif'"
                                            :key="'asisten-koordinator-'.$edit_id"
                                        />
                                        @error('asisten_koordinator_id') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <livewire:dropdown.multi-select-search
                                        :query="'App\Models\Dosen'"
                                        :wire_model="'selected_kontributor_ids'"
                                        :label="'Kontributor Blok'"
                                        :colSearch="'nama'"
                                        :colSubtitle="'nidn'"
                                        :colValue="'id_dosen'"
                                        :selected="$selected_kontributor_ids"
                                        conditions="status = 'aktif'"
                                        :limit="10"
                                        :key="'kontributor-blok-'.$edit_id"
                                    />
                                    @error('selected_kontributor_ids.*') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nama Blok</label>
                                    <input type="text" class="form-control" wire:model.live.debounce.500ms="nama">
                                    @error('nama') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tanggal Mulai</label>
                                        <input type="date" class="form-control" wire:model="tanggal_mulai">
                                        @error('tanggal_mulai') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tanggal Selesai</label>
                                        <input type="date" class="form-control" wire:model="tanggal_selesai">
                                        @error('tanggal_selesai') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea class="form-control" wire:model="deskripsi" rows="3"></textarea>
                                    @error('deskripsi') <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="border rounded p-3 h-100">
                                    @error('selected_mata_kuliah_ids') <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>{{ $message }}</div> @enderror

                                    @if (empty($prodi_id))
                                        <div class="alert alert-info mb-0 alert-dismissible fade show" role="alert">
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                            Pilih program studi terlebih dahulu untuk menampilkan mata kuliah.</div>
                                    @else
                                        <livewire:dropdown.multi-select-search
                                            :query="'App\Models\MataKuliah'"
                                            :wire_model="'selected_mata_kuliah_ids'"
                                            :label="'Mata Kuliah yang Memakai Blok'"
                                            :colSearch="'kode'"
                                            :colSubtitle="'nama'"
                                            :colValue="'id'"
                                            :selected="$selected_mata_kuliah_ids"
                                            :conditions="'status = \'aktif\' and prodi_id = '.(int) $prodi_id"
                                            :currentValue="$edit_id"
                                            :limit="10"
                                            :key="'mata-kuliah-blok-'.$prodi_id.'-'.$edit_id"
                                        />
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($active_tab === 'kegiatan')
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                @error('aturan') <div class="alert alert-danger py-2 mb-0 alert-dismissible fade show" role="alert"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>{{ $message }}</div> @enderror
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" wire:click="applyTemplateStandar">
                                <i class="ri-magic-line"></i> Gunakan Template Standar
                            </button>
                        </div>

                        @foreach ($aturan as $index => $item)
                            @php
                                $jenisTerpilih = $jenis_kegiatan->firstWhere('id', (int) ($item['jenis_kegiatan_id'] ?? 0));
                                $materiCount = count($item['materi'] ?? []);
                                $rinciCount = collect($item['materi'] ?? [])->sum(fn ($materi) => count($materi['rinci'] ?? []));
                            @endphp
                            <div class="card border shadow-sm mb-3 aturan-card" wire:key="aturan-config-{{ $index }}">
                                <div class="card-header bg-light-subtle border-bottom">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="badge bg-primary rounded-pill px-2">#{{ $index + 1 }}</span>
                                            <div class="d-flex flex-column">
                                                <span class="text-uppercase small text-muted fw-semibold lh-1">Kegiatan</span>
                                                <span class="fw-semibold fs-15">{{ $jenisTerpilih ? $jenisTerpilih->nama : 'Kegiatan belum dipilih' }}</span>
                                            </div>
                                            <span class="badge bg-primary-subtle text-primary">{{ $materiCount }} materi</span>
                                            <span class="badge bg-secondary-subtle text-secondary">{{ $rinciCount }} rincian</span>
                                            <span class="badge bg-success-subtle text-success">Kelompok belajar</span>
                                        </div>
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="removeAturan({{ $index }})" @disabled(count($aturan) <= 1)>
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-lg-5 mb-3">
                                            <label class="form-label">Jenis Kegiatan</label>
                                            <select class="form-select" wire:model.live="aturan.{{ $index }}.jenis_kegiatan_id">
                                                <option value="">Pilih jenis kegiatan</option>
                                                @foreach ($jenis_kegiatan as $jenis)
                                                    <option
                                                        value="{{ $jenis->id }}"
                                                        @disabled(collect($aturan)->except($index)->pluck('jenis_kegiatan_id')->contains(fn ($id) => (int) $id === (int) $jenis->id))
                                                    >{{ $jenis->kode }} - {{ $jenis->nama }}</option>
                                                @endforeach
                                            </select>
                                            @error("aturan.$index.jenis_kegiatan_id") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-6 col-lg-3 mb-3">
                                            <label class="form-label">Durasi (Menit)</label>
                                            <input type="number" class="form-control" wire:model.live="aturan.{{ $index }}.durasi_menit">
                                            @error("aturan.$index.durasi_menit") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-6 col-lg-2 mb-3">
                                            <label class="form-label">Urutan</label>
                                            <input type="number" class="form-control" wire:model="aturan.{{ $index }}.urutan">
                                            @error("aturan.$index.urutan") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <hr class="my-3">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-lg-7">
                                            <span class="text-uppercase small text-muted fw-semibold d-block mb-2">Pengaturan Kegiatan</span>
                                            <div class="d-flex flex-wrap gap-4">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="perlu_presensi_{{ $index }}" wire:model="aturan.{{ $index }}.perlu_presensi">
                                                    <label class="form-check-label" for="perlu_presensi_{{ $index }}">Presensi</label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="perlu_logbook_{{ $index }}" wire:model="aturan.{{ $index }}.perlu_logbook">
                                                    <label class="form-check-label" for="perlu_logbook_{{ $index }}">Logbook</label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="perlu_penilaian_{{ $index }}" wire:model.live="aturan.{{ $index }}.perlu_penilaian">
                                                    <label class="form-check-label" for="perlu_penilaian_{{ $index }}">
                                                        Penilaian
                                                        @if (! empty($item['komponen']))
                                                            <span class="badge bg-light text-dark border">{{ count($item['komponen']) }} komponen</span>
                                                        @endif
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-5">
                                            <span class="text-uppercase small text-muted fw-semibold d-block mb-2">Aksi Cepat</span>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button type="button" class="btn btn-soft-info btn-sm" wire:click="setActiveAturan({{ $index }})">
                                                    <i class="ri-book-open-line"></i> Isi Materi
                                                </button>
                                                <button type="button" class="btn btn-soft-secondary btn-sm" wire:click="setActivePenilaian({{ $index }})">
                                                    <i class="ri-graduation-cap-line"></i> Isi Penilaian
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="addAturan">
                                <i class="ri-add-box-fill"></i> Tambah Kegiatan
                            </button>
                        </div>
                    @endif

                    @if ($active_tab === 'materi')
                        <div class="row">
                            <div class="col-xl-3 mb-3">
                                <div class="list-group">
                                    @foreach ($aturan as $index => $item)
                                        @php
                                            $jenisTerpilih = $jenis_kegiatan->firstWhere('id', (int) ($item['jenis_kegiatan_id'] ?? 0));
                                            $rinciCount = collect($item['materi'] ?? [])->sum(fn ($materi) => count($materi['rinci'] ?? []));
                                        @endphp
                                        <button type="button" class="list-group-item list-group-item-action {{ $active_aturan_index === $index ? 'active' : '' }}" wire:click="setActiveAturan({{ $index }})">
                                            <div class="fw-semibold">{{ $jenisTerpilih ? $jenisTerpilih->nama : 'Kegiatan belum dipilih' }}</div>
                                            <small>{{ count($item['materi'] ?? []) }} materi, {{ $rinciCount }} rincian</small>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-xl-9">
                                @if (! $activeAturan)
                                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                        Tambahkan kegiatan terlebih dahulu.</div>
                                @else
                                    <div class="mb-3">
                                        <h5 class="mb-1">{{ $activeJenis ? $activeJenis->nama : 'Kegiatan belum dipilih' }}</h5>
                                        <div class="text-muted small">Default kegiatan: {{ $activeAturan['durasi_menit'] ?? 0 }} menit per sesi</div>
                                    </div>

                                    @error("aturan.$active_aturan_index.materi") <div class="text-sm text-danger mb-2">{{ $message }}</div> @enderror
                                    @foreach (($activeAturan['materi'] ?? []) as $materiIndex => $materi)
                                        <div class="border rounded p-3 mb-3" wire:key="materi-active-{{ $active_aturan_index }}-{{ $materiIndex }}">
                                            <div class="row g-3">
                                                <div class="col-lg-7 mb-3">
                                                    <label class="form-label">Judul Pokok Materi</label>
                                                    <input type="text" class="form-control" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.judul">
                                                    @error("aturan.$active_aturan_index.materi.$materiIndex.judul") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="col-md-3 col-lg-2 mb-3">
                                                    <label class="form-label">Urutan</label>
                                                    <input type="number" class="form-control" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.urutan">
                                                </div>
                                                <div class="col-md-4 col-lg-2 mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select class="form-select" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.status">
                                                        <option value="aktif">Aktif</option>
                                                        <option value="nonaktif">Nonaktif</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2 col-lg-1 mb-3 text-end">
                                                    <label class="form-label d-block">&nbsp;</label>
                                                    <button type="button" class="btn btn-danger btn-sm" wire:click="removeMateri({{ $active_aturan_index }}, {{ $materiIndex }})" @disabled(count($activeAturan['materi'] ?? []) <= 1)>
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <details class="mb-3">
                                                <summary class="text-muted">Detail tambahan</summary>
                                                <div class="pt-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">Deskripsi</label>
                                                        <textarea class="form-control" rows="2" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.deskripsi"></textarea>
                                                    </div>
                                                    <div class="mb-0">
                                                        <label class="form-label">Capaian Pembelajaran</label>
                                                        <textarea class="form-control" rows="2" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.capaian_pembelajaran"></textarea>
                                                    </div>
                                                </div>
                                            </details>

                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                                <h6 class="mb-0">Rincian Materi</h6>
                                            </div>

                                            @error("aturan.$active_aturan_index.materi.$materiIndex.rinci") <div class="text-sm text-danger mb-2">{{ $message }}</div> @enderror
                                            @foreach (($materi['rinci'] ?? []) as $rinciIndex => $rinci)
                                                <div class="border rounded bg-light p-3 mb-2" wire:key="rinci-active-{{ $active_aturan_index }}-{{ $materiIndex }}-{{ $rinciIndex }}">
                                                    <div class="row g-3 align-items-start">
                                                        <div class="col-lg-7 mb-3">
                                                            <label class="form-label">Judul Rinci</label>
                                                            <input type="text" class="form-control" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.rinci.{{ $rinciIndex }}.judul">
                                                            @error("aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.judul") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-md-3 col-lg-2 mb-3">
                                                            <label class="form-label">Pertemuan</label>
                                                            <input type="number" class="form-control" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.rinci.{{ $rinciIndex }}.pertemuan_ke">
                                                            @error("aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.pertemuan_ke") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-md-3 col-lg-2 mb-3">
                                                            <label class="form-label">Menit / Sesi</label>
                                                            <input type="number" min="1" max="1440" class="form-control" wire:model.live="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.rinci.{{ $rinciIndex }}.durasi_menit_per_sesi">
                                                            @error("aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.durasi_menit_per_sesi") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-md-2 col-lg-1 mb-3 text-end">
                                                            <label class="form-label d-block">&nbsp;</label>
                                                            <button type="button" class="btn btn-danger btn-sm" wire:click="removeRinci({{ $active_aturan_index }}, {{ $materiIndex }}, {{ $rinciIndex }})">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-4 col-lg-3 mb-3">
                                                            <label class="form-label">Tanggal Rencana</label>
                                                            <input type="date" class="form-control" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.rinci.{{ $rinciIndex }}.tanggal_rencana">
                                                            @error("aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.tanggal_rencana") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-md-4 col-lg-2 mb-3">
                                                            <label class="form-label">Jam Mulai</label>
                                                            <input type="time" class="form-control" wire:model.live="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.rinci.{{ $rinciIndex }}.jam_mulai_rencana">
                                                            @error("aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.jam_mulai_rencana") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-md-4 col-lg-2 mb-3">
                                                            <label class="form-label">Jam Selesai</label>
                                                            <input type="time" class="form-control" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.rinci.{{ $rinciIndex }}.jam_selesai_rencana">
                                                            @error("aturan.$active_aturan_index.materi.$materiIndex.rinci.$rinciIndex.jam_selesai_rencana") <div class="text-sm text-danger">{{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-lg-5 mb-3 d-flex align-items-end">
                                                            <div class="text-muted small">
                                                                <i class="ri-information-line"></i>
                                                                Menjadi usulan awal jadwal. Tiap kelompok masih bisa memakai tanggal berbeda di menu Operasional Blok.
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <details>
                                                        <summary class="text-muted">Referensi dan catatan</summary>
                                                        <div class="pt-3">
                                                            <div class="mb-3">
                                                                <label class="form-label">Deskripsi</label>
                                                                <textarea class="form-control" rows="2" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.rinci.{{ $rinciIndex }}.deskripsi"></textarea>
                                                            </div>
                                                            <div class="mb-0">
                                                                <label class="form-label">Referensi</label>
                                                                <textarea class="form-control" rows="2" wire:model="aturan.{{ $active_aturan_index }}.materi.{{ $materiIndex }}.rinci.{{ $rinciIndex }}.referensi"></textarea>
                                                            </div>
                                                        </div>
                                                    </details>
                                                </div>
                                            @endforeach

                                            <div class="d-flex justify-content-end mt-2">
                                                <button type="button" class="btn btn-primary btn-sm" wire:click="addRinci({{ $active_aturan_index }}, {{ $materiIndex }})">
                                                    <i class="ri-add-box-fill"></i> Tambah Rincian
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach

                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="addMateri({{ $active_aturan_index }})">
                                            <i class="ri-add-box-fill"></i> Tambah Pokok Materi
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($active_tab === 'penilaian')
                        <div class="row">
                            <div class="col-xl-3 mb-3">
                                <div class="list-group">
                                    @foreach ($aturan as $index => $item)
                                        @php
                                            $jenisTerpilih = $jenis_kegiatan->firstWhere('id', (int) ($item['jenis_kegiatan_id'] ?? 0));
                                            $komponenCount = count($item['komponen'] ?? []);
                                        @endphp
                                        <button type="button" class="list-group-item list-group-item-action {{ $active_aturan_index === $index ? 'active' : '' }}" wire:click="setActivePenilaian({{ $index }})">
                                            <div class="fw-semibold">{{ $jenisTerpilih ? $jenisTerpilih->nama : 'Kegiatan belum dipilih' }}</div>
                                            <small>
                                                {{ $komponenCount }} komponen
                                                {{ empty($item['perlu_penilaian']) ? '- tidak dinilai' : '' }}
                                            </small>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-xl-9">
                                @if (! $activeAturan)
                                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                        Tambahkan kegiatan terlebih dahulu.</div>
                                @else
                                    @php
                                        $komponenAktif = $activeAturan['komponen'] ?? [];
                                        $totalNilaiMaks = collect($komponenAktif)
                                            ->sum(fn ($baris) => (float) ($baris['nilai_maks'] ?? 0));
                                    @endphp

                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                        <div>
                                            <h5 class="mb-1">{{ $activeJenis ? $activeJenis->nama : 'Kegiatan belum dipilih' }}</h5>
                                            <div class="text-muted small">
                                                Komponen yang dinilai dosen pada setiap pertemuan kegiatan ini.
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="ambilStandarPenilaian({{ $active_aturan_index }})">
                                            <i class="ri-download-2-line"></i> Ambil dari Standar
                                        </button>
                                    </div>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                            id="perlu_penilaian_tab_{{ $active_aturan_index }}"
                                            wire:model.live="aturan.{{ $active_aturan_index }}.perlu_penilaian">
                                        <label class="form-check-label" for="perlu_penilaian_tab_{{ $active_aturan_index }}">
                                            Kegiatan ini dinilai
                                        </label>
                                    </div>

                                    @error("aturan.$active_aturan_index.komponen")
                                        <div class="alert alert-danger py-2 alert-dismissible fade show" role="alert">
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                            {{ $message }}</div>
                                    @enderror

                                    @if (empty($komponenAktif))
                                        <div class="alert alert-light border alert-dismissible fade show" role="alert">
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                            <i class="ri-information-line"></i>
                                            Belum ada komponen penilaian. Tekan <span class="fw-semibold">Ambil dari Standar</span>
                                            untuk menyalin rubrik jenis kegiatan ini.
                                        </div>
                                    @else
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="min-width: 220px;">Komponen</th>
                                                        <th style="width: 130px;">Nilai Min</th>
                                                        <th style="width: 130px;">Nilai Maks</th>
                                                        <th style="width: 110px;">Urutan</th>
                                                        <th style="width: 70px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($komponenAktif as $komponenIndex => $baris)
                                                        <tr wire:key="komponen-{{ $active_aturan_index }}-{{ $komponenIndex }}">
                                                            <td>
                                                                @php
                                                                    $masterKomponen = $komponen_penilaian->firstWhere(
                                                                        'id',
                                                                        (int) ($baris['komponen_penilaian_id'] ?? 0)
                                                                    );
                                                                @endphp
                                                                <span class="fw-semibold">{{ $masterKomponen?->nama ?: 'Komponen tidak ditemukan' }}</span>
                                                                @error("aturan.$active_aturan_index.komponen.$komponenIndex.komponen_penilaian_id")
                                                                    <div class="text-sm text-danger">{{ $message }}</div>
                                                                @enderror
                                                            </td>
                                                            <td>
                                                                <input type="number" min="0" step="0.01" class="form-control form-control-sm"
                                                                    wire:model.live.debounce.400ms="aturan.{{ $active_aturan_index }}.komponen.{{ $komponenIndex }}.nilai_min">
                                                                @error("aturan.$active_aturan_index.komponen.$komponenIndex.nilai_min")
                                                                    <div class="text-sm text-danger">{{ $message }}</div>
                                                                @enderror
                                                            </td>
                                                            <td>
                                                                <input type="number" min="0.01" step="0.01" class="form-control form-control-sm"
                                                                    wire:model.live.debounce.400ms="aturan.{{ $active_aturan_index }}.komponen.{{ $komponenIndex }}.nilai_maks">
                                                                @error("aturan.$active_aturan_index.komponen.$komponenIndex.nilai_maks")
                                                                    <div class="text-sm text-danger">{{ $message }}</div>
                                                                @enderror
                                                            </td>
                                                            <td>
                                                                <input type="number" class="form-control form-control-sm"
                                                                    wire:model="aturan.{{ $active_aturan_index }}.komponen.{{ $komponenIndex }}.urutan">
                                                                @error("aturan.$active_aturan_index.komponen.$komponenIndex.urutan")
                                                                    <div class="text-sm text-danger">{{ $message }}</div>
                                                                @enderror
                                                            </td>
                                                            <td class="text-end">
                                                                <button type="button" class="btn btn-danger btn-sm"
                                                                    wire:click="removeKomponen({{ $active_aturan_index }}, {{ $komponenIndex }})">
                                                                    <i class="ri-delete-bin-line"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="row g-2 mt-3">
                                            <div class="col-sm-4">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="text-muted small">Jumlah Komponen</div>
                                                    <div class="fs-5 fw-semibold">{{ count($komponenAktif) }}</div>
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="text-muted small">Total Nilai Maksimum per Pertemuan</div>
                                                    <div class="fs-5 fw-semibold">{{ number_format($totalNilaiMaks, 2, ',', '.') }}</div>
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="text-muted small">Rumus Nilai Akhir</div>
                                                    <div class="fw-semibold">Total skor ÷ {{ number_format($totalNilaiMaks, 2, ',', '.') }} × 100</div>
                                                </div>
                                            </div>
                                        </div>

                                        @if ($totalNilaiMaks <= 0)
                                            <div class="alert alert-warning py-2 mt-3 mb-0 alert-dismissible fade show" role="alert">
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                                <i class="ri-alert-line"></i>
                                                Total nilai maksimum harus lebih dari 0 agar nilai akhir dapat dihitung.
                                            </div>
                                        @else
                                            <div class="text-muted small mt-2">
                                                Skor komponen per pertemuan dijumlahkan, lalu dinormalisasi ke skala 100.
                                            </div>
                                        @endif
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($active_tab === 'review')
                        <div class="row">
                            <div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="text-muted">Kegiatan</div><h4 class="mb-0">{{ count($aturan) }}</h4></div></div>
                            <div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="text-muted">Pertemuan</div><h4 class="mb-0">{{ $totalPertemuan }}</h4></div></div>
                            <div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="text-muted">Materi</div><h4 class="mb-0">{{ $totalMateri }}</h4></div></div>
                            <div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="text-muted">Rincian</div><h4 class="mb-0">{{ $totalRinci }}</h4></div></div>
                        </div>

                        @if ($totalRinciTanpaTanggal > 0)
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                <i class="ri-calendar-close-line"></i>
                                {{ $totalRinciTanpaTanggal }} rincian materi belum punya tanggal rencana pertemuan.
                                Blok tetap bisa disimpan, tetapi jadwal per kelompok harus diisi manual di menu Operasional Blok.
                            </div>
                        @endif
                        @foreach ($aturan as $index => $item)
                            @php
                                $jenisTerpilih = $jenis_kegiatan->firstWhere('id', (int) ($item['jenis_kegiatan_id'] ?? 0));
                                $rinciCount = 0;
                                $tanggalRencana = collect();
                                foreach ($item['materi'] ?? [] as $materiReview) {
                                    $rinciCount += count($materiReview['rinci'] ?? []);
                                    foreach ($materiReview['rinci'] ?? [] as $rinciReview) {
                                        if (! empty($rinciReview['tanggal_rencana'])) {
                                            $tanggalRencana->push($rinciReview['tanggal_rencana']);
                                        }
                                    }
                                }
                                $tanggalRencana = $tanggalRencana->sort()->values();
                                $komponenReview = collect($item['komponen'] ?? []);
                                $totalNilaiMaksReview = 0;
                                $daftarKomponenReview = [];
                                foreach ($komponenReview as $barisReview) {
                                    $totalNilaiMaksReview += (float) ($barisReview['nilai_maks'] ?? 0);
                                    $masterKomponenReview = $komponen_penilaian->firstWhere(
                                        'id',
                                        (int) ($barisReview['komponen_penilaian_id'] ?? 0)
                                    );
                                    $namaKomponenReview = $masterKomponenReview
                                        ? $masterKomponenReview->nama
                                        : 'komponen';
                                    $daftarKomponenReview[] = $namaKomponenReview
                                        .' ('.($barisReview['nilai_min'] ?? 0)
                                        .'-'.($barisReview['nilai_maks'] ?? 0).')';
                                }
                                $daftarKomponenReview = implode(', ', $daftarKomponenReview);
                            @endphp
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold">{{ $jenisTerpilih ? $jenisTerpilih->nama : 'Kegiatan belum dipilih' }}</div>
                                        <div class="text-muted small">{{ $item['durasi_menit'] ?? 0 }} menit per sesi</div>
                                        <div class="text-muted small">
                                            Rencana:
                                            @if ($tanggalRencana->isEmpty())
                                                belum diisi
                                            @else
                                                {{ \Illuminate\Support\Carbon::parse($tanggalRencana->first())->format('d/m/Y') }}
                                                &ndash;
                                                {{ \Illuminate\Support\Carbon::parse($tanggalRencana->last())->format('d/m/Y') }}
                                                ({{ $tanggalRencana->count() }} dari {{ $rinciCount }} rincian)
                                            @endif
                                        </div>
                                        <div class="text-muted small">
                                            Penilaian:
                                            @if (empty($item['perlu_penilaian']))
                                                tidak dinilai
                                            @elseif ($komponenReview->isEmpty())
                                                <span class="text-danger">ditandai dinilai tapi komponennya belum disusun</span>
                                            @else
                                                {{ $daftarKomponenReview }}
                                                &mdash; maksimum {{ $totalNilaiMaksReview }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-primary-subtle text-primary">{{ count($item['materi'] ?? []) }} materi</span>
                                        <span class="badge bg-secondary-subtle text-secondary">{{ $rinciCount }} rincian</span>
                                        @if (!empty($item['perlu_penilaian']))
                                            <span class="badge bg-info-subtle text-info">{{ $komponenReview->count() }} komponen nilai</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div style="position: fixed; bottom: 50px; left: 0; width: 100%; display: flex; justify-content: center; gap: 8px; z-index: 1050;">
        @if ($active_tab !== 'review')
            <button type="button" class="btn btn-primary shadow d-flex align-items-center gap-2 fab-save"
                wire:click="saveCurrentTab" wire:loading.attr="disabled" wire:target="saveCurrentTab,saveAndNextKegiatan,saveAndContinue">
                <span wire:loading.remove wire:target="saveCurrentTab">
                    <i class="ri-save-line"></i> SIMPAN
                </span>
                <span wire:loading wire:target="saveCurrentTab">Menyimpan...</span>
            </button>
        @endif

        @if (in_array($active_tab, ['materi', 'penilaian'], true))
            <button type="button" class="btn btn-info shadow d-flex align-items-center gap-2 fab-save"
                wire:click="saveAndNextKegiatan" wire:loading.attr="disabled" wire:target="saveCurrentTab,saveAndNextKegiatan,saveAndContinue">
                <span wire:loading.remove wire:target="saveAndNextKegiatan">
                    <i class="ri-arrow-right-line"></i> LANJUT MENGISI
                </span>
                <span wire:loading wire:target="saveAndNextKegiatan">Menyimpan...</span>
            </button>
        @endif

        <button type="submit" class="btn btn-primary shadow d-flex align-items-center gap-2 fab-save"
            wire:loading.attr="disabled" wire:target="saveCurrentTab,saveAndNextKegiatan,saveAndContinue,save">
            <span wire:loading.remove wire:target="saveAndContinue,save">
                <i class="ri-save-line"></i> {{ $active_tab === 'review' ? 'SIMPAN' : 'SIMPAN DAN LANJUT' }}
            </span>
            <span wire:loading wire:target="saveAndContinue,save">Menyimpan...</span>
        </button>
    </div>
</form>
