<?php

namespace App\Support\Akademik\Sync;

use App\Models\Kurikulum;
use App\Models\KurikulumMataKuliah;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\SkalaNilai;
use App\Support\Akademik\AkademikClient;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SinkronisasiKurikulum
{
    private const LIMIT = 500;

    private const CACHE_KEY = 'akademik:hasil-sync:kurikulum';

    public function __construct(private readonly AkademikClient $client) {}

    /** @return array<string, mixed> */
    public function handle(Prodi $prodi): array
    {
        return $this->jalankan(fn (): array => $this->sinkronkan($prodi));
    }

    /** @return array<string, mixed> */
    public function handleSatu(Kurikulum $kurikulum): array
    {
        $prodi = $kurikulum->prodi;

        if ($prodi === null) {
            throw new DomainException('Program studi kurikulum tidak ditemukan.');
        }

        return $this->jalankan(fn (): array => $this->sinkronkan($prodi, $kurikulum));
    }

    public static function cacheKey(): string
    {
        return self::CACHE_KEY;
    }

    /** @param callable(): array<string, mixed> $sinkronisasi */
    private function jalankan(callable $sinkronisasi): array
    {
        $lock = Cache::lock('akademik:sync:kurikulum', 900);

        if (! $lock->get()) {
            throw new DomainException('Sinkronisasi kurikulum sedang berjalan. Tunggu proses sebelumnya selesai.');
        }

        try {
            $hasil = $sinkronisasi();
            Cache::put(self::CACHE_KEY, $hasil, now()->addDays(30));

            return $hasil;
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed> */
    private function sinkronkan(Prodi $prodi, ?Kurikulum $kurikulumExact = null): array
    {
        $headers = $this->ambilSemua('/api/data/kurikulum', ['kd_prodi' => $prodi->kode], 'kurikulum');
        $mappedHeaders = [];

        foreach ($headers['items'] as $index => $item) {
            if (! is_array($item)) {
                throw new DomainException('Data kurikulum API pada offset '.$index.' bukan object.');
            }

            $mapped = $this->mapKurikulum($item, $index, $prodi->kode);

            if (isset($mappedHeaders[$mapped['kode']])) {
                throw new DomainException("Kode kurikulum {$mapped['kode']} muncul lebih dari sekali pada API.");
            }

            $mappedHeaders[$mapped['kode']] = $mapped;
        }

        if ($mappedHeaders === []) {
            throw new DomainException("Kurikulum untuk prodi {$prodi->kode} tidak ditemukan di API.");
        }

        if ($kurikulumExact !== null) {
            $mappedHeaders = $this->pilihUntukSatu($mappedHeaders, $kurikulumExact, $prodi);
        }

        $hasil = [
            'dibuat' => 0,
            'diubah' => 0,
            'direkonsiliasi' => 0,
            'dipulihkan' => 0,
            'tetap' => 0,
            'pivot_dibuat' => 0,
            'pivot_diubah' => 0,
            'pivot_dipulihkan' => 0,
            'pivot_tetap' => 0,
        ];

        foreach ($mappedHeaders as $header) {
            $pemetaan = $this->ambilPemetaan($header, $prodi);
            $statistik = $this->simpan($header, $pemetaan, $prodi);

            foreach ($statistik as $key => $jumlah) {
                $hasil[$key] += $jumlah;
            }
        }

        $unik = count($mappedHeaders);

        return $hasil + [
            'status' => 'success',
            'prodi' => "{$prodi->kode} - {$prodi->nama}",
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => $headers['diterima'],
            'unik' => $unik,
            'pesan' => "{$unik} kurikulum prodi {$prodi->kode} berhasil disinkronkan.",
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $headers
     * @return array<string, array<string, mixed>>
     */
    private function pilihUntukSatu(array $headers, Kurikulum $kurikulum, Prodi $prodi): array
    {
        if (isset($headers[$kurikulum->kode])) {
            return [$kurikulum->kode => $headers[$kurikulum->kode]];
        }

        if ($kurikulum->status !== 'aktif' || $kurikulum->status_sync !== Kurikulum::STATUS_SYNC_PENDING) {
            throw new DomainException("Kurikulum {$kurikulum->kode} tidak ditemukan di API.");
        }

        $pendingAktif = Kurikulum::query()
            ->where('prodi_id', $prodi->id_prodi)
            ->where('status', 'aktif')
            ->where('status_sync', Kurikulum::STATUS_SYNC_PENDING)
            ->get();

        if ($pendingAktif->count() !== 1 || ! $pendingAktif->first()->is($kurikulum)) {
            throw new DomainException('Rekonsiliasi membutuhkan tepat satu kurikulum aktif yang belum sinkron pada prodi ini.');
        }

        $cocok = [];

        foreach ($headers as $kode => $header) {
            $pemetaan = $this->ambilPemetaan($header, $prodi);

            if ($this->semuaMataKuliahTersedia($pemetaan, $prodi)) {
                $cocok[$kode] = $header;
            }
        }

        if (count($cocok) !== 1) {
            throw new DomainException('Kurikulum API yang cocok dengan set mata kuliah lokal tidak ditemukan secara unik.');
        }

        return $cocok;
    }

    /** @return array<string, array<string, mixed>> */
    private function ambilPemetaan(array $header, Prodi $prodi): array
    {
        $response = $this->ambilSemua('/api/data/matakuliah-kurikulum', [
            'id_kurikulum' => $header['kode'],
            'kd_prodi' => $prodi->kode,
        ], 'pemetaan kurikulum');
        $hasil = [];

        foreach ($response['items'] as $index => $item) {
            if (! is_array($item)) {
                throw new DomainException('Data pemetaan kurikulum API pada offset '.$index.' bukan object.');
            }

            $mapped = $this->mapPemetaan($item, $index, $header['kode'], $prodi->kode);

            if (isset($hasil[$mapped['kode_mata_kuliah']])) {
                throw new DomainException("Mata kuliah {$mapped['kode_mata_kuliah']} duplikat pada kurikulum {$header['kode']}.");
            }

            $hasil[$mapped['kode_mata_kuliah']] = $mapped;
        }

        if ($hasil === []) {
            throw new DomainException("Pemetaan mata kuliah kurikulum {$header['kode']} tidak ditemukan di API.");
        }

        ksort($hasil);

        return $hasil;
    }

    /** @return array{items: array<int, mixed>, diterima: int} */
    private function ambilSemua(string $path, array $filter, string $label): array
    {
        $offset = 0;
        $totalRecords = null;
        $semua = [];

        do {
            $response = $this->client->get($path, $filter + ['limit' => self::LIMIT, 'offset' => $offset]);

            if (strtolower((string) ($response['status'] ?? '')) !== 'success') {
                throw new DomainException("API mengembalikan status bisnis {$label} yang gagal.");
            }

            $items = $response['data'] ?? null;
            $jumlahTotal = filter_var($response['total_records'] ?? null, FILTER_VALIDATE_INT);
            $responseOffset = filter_var($response['offset'] ?? null, FILTER_VALIDATE_INT);

            if (! is_array($items) || ! array_is_list($items)) {
                throw new DomainException("Format data {$label} dari API tidak sesuai kontrak.");
            }

            if ($jumlahTotal === false || $jumlahTotal < 0 || $responseOffset !== $offset) {
                throw new DomainException("Metadata pagination {$label} dari API tidak valid.");
            }

            if ($totalRecords !== null && $totalRecords !== $jumlahTotal) {
                throw new DomainException("Jumlah {$label} berubah saat sinkronisasi. Ulangi proses.");
            }

            $totalRecords = $jumlahTotal;
            array_push($semua, ...$items);
            $jumlah = count($items);
            $offset += $jumlah;

            if ($jumlah === 0 && $offset < $totalRecords) {
                throw new DomainException("Pagination {$label} berhenti sebelum seluruh data diterima.");
            }
        } while ($offset < $totalRecords);

        if ($offset !== $totalRecords) {
            throw new DomainException("Jumlah {$label} tidak sesuai metadata API.");
        }

        return ['items' => $semua, 'diterima' => $offset];
    }

    /** @return array{kode: string, nama: string, tahun_berlaku: int, sks_lulus: float, semester_normal: int, status: string} */
    private function mapKurikulum(array $item, int $index, string $kodeProdi): array
    {
        $kode = trim((string) ($item['kd_kur'] ?? ''));
        $nama = trim((string) ($item['nm_kur'] ?? ''));
        $prodi = trim((string) ($item['kd_prodi'] ?? ''));
        $tahun = filter_var($item['th_kur'] ?? null, FILTER_VALIDATE_INT);
        $sks = filter_var($item['min_sks_evaluasi_akhir'] ?? null, FILTER_VALIDATE_FLOAT);
        $semester = filter_var($item['masa_studi_ideal'] ?? null, FILTER_VALIDATE_INT);
        $status = strtoupper(trim((string) ($item['status'] ?? '')));

        if ($kode === '' || strlen($kode) > 255 || $nama === '' || strlen($nama) > 255
            || $prodi !== $kodeProdi || $tahun === false || $tahun < 1900 || $tahun > 2200
            || $sks === false || $sks < 0.5 || $sks > 999.9
            || abs($sks * 10 - round($sks * 10)) > 0.00001
            || $semester === false || $semester < 1 || $semester > 30
        ) {
            throw new DomainException("Data kurikulum API pada offset {$index} tidak valid.");
        }

        if ($status !== 'A') {
            throw new DomainException("Status kurikulum {$kode} dari API tidak dikenali.");
        }

        return [
            'kode' => $kode,
            'nama' => $nama,
            'tahun_berlaku' => $tahun,
            'sks_lulus' => (float) $sks,
            'semester_normal' => $semester,
            'status' => 'aktif',
        ];
    }

    /** @return array{kode_mata_kuliah: string, semester_urutan: int, apakah_wajib: bool} */
    private function mapPemetaan(array $item, int $index, string $kodeKurikulum, string $kodeProdi): array
    {
        $kode = trim((string) ($item['kd_mk'] ?? ''));
        $kurikulum = trim((string) ($item['kd_kur'] ?? ''));
        $prodi = trim((string) ($item['kd_prodi'] ?? ''));
        $semester = filter_var($item['semester_paket'] ?? null, FILTER_VALIDATE_INT);
        $jenis = strtoupper(trim((string) ($item['jenis_mk'] ?? '')));

        if ($kode === '' || strlen($kode) > 255 || $kurikulum !== $kodeKurikulum || $prodi !== $kodeProdi
            || $semester === false || $semester < 1 || $semester > 30 || ! in_array($jenis, ['W', 'P'], true)
        ) {
            throw new DomainException("Data pemetaan kurikulum API pada offset {$index} tidak valid.");
        }

        return [
            'kode_mata_kuliah' => $kode,
            'semester_urutan' => $semester,
            'apakah_wajib' => $jenis === 'W',
        ];
    }

    /** @return array<string, int> */
    private function simpan(array $header, array $pemetaan, Prodi $prodi): array
    {
        return DB::transaction(function () use ($header, $pemetaan, $prodi): array {
            $mataKuliah = MataKuliah::query()
                ->where('prodi_id', $prodi->id_prodi)
                ->whereIn('kode', array_keys($pemetaan))
                ->lockForUpdate()
                ->get()
                ->keyBy('kode');

            $hilang = array_diff(array_keys($pemetaan), $mataKuliah->keys()->all());

            if ($hilang !== []) {
                throw new DomainException('Mata kuliah lokal tidak ditemukan: '.implode(', ', $hilang).'.');
            }

            $kurikulum = Kurikulum::withTrashed()
                ->where('prodi_id', $prodi->id_prodi)
                ->where('kode', $header['kode'])
                ->lockForUpdate()
                ->first();
            $direkonsiliasi = false;

            if ($kurikulum === null) {
                $candidates = Kurikulum::query()
                    ->where('prodi_id', $prodi->id_prodi)
                    ->where('status', 'aktif')
                    ->where('status_sync', Kurikulum::STATUS_SYNC_PENDING)
                    ->lockForUpdate()
                    ->get();

                if ($candidates->count() === 1) {
                    $kurikulum = $candidates->first();
                    $direkonsiliasi = true;
                }
            }

            $dibuat = $kurikulum === null;
            $dipulihkan = $kurikulum?->trashed() ?? false;
            $berubah = $kurikulum && $this->kurikulumBerubah($kurikulum, $header);
            $syncedAt = now();

            if ($kurikulum === null) {
                $skalaNilai = SkalaNilai::query()->where('aktif', true)->lockForUpdate()->get();

                if ($skalaNilai->count() !== 1) {
                    throw new DomainException('Kurikulum baru membutuhkan tepat satu skala nilai aktif.');
                }

                $kurikulum = Kurikulum::query()->create([
                    ...$header,
                    'prodi_id' => $prodi->id_prodi,
                    'skala_nilai_id' => $skalaNilai->first()->id_skala_nilai,
                    'deskripsi' => null,
                    'status_sync' => Kurikulum::STATUS_SYNC_SYNCED,
                    'synced_at' => $syncedAt,
                ]);
            } else {
                $kurikulum->fill([
                    ...$header,
                    'status_sync' => Kurikulum::STATUS_SYNC_SYNCED,
                    'synced_at' => $syncedAt,
                ]);

                if ($dipulihkan) {
                    $kurikulum->restore();
                }

                $kurikulum->save();
            }

            $hasil = [
                'dibuat' => $dibuat ? 1 : 0,
                'diubah' => ! $dibuat && ! $dipulihkan && ! $direkonsiliasi && $berubah ? 1 : 0,
                'direkonsiliasi' => $direkonsiliasi ? 1 : 0,
                'dipulihkan' => $dipulihkan ? 1 : 0,
                'tetap' => ! $dibuat && ! $dipulihkan && ! $direkonsiliasi && ! $berubah ? 1 : 0,
                'pivot_dibuat' => 0,
                'pivot_diubah' => 0,
                'pivot_dipulihkan' => 0,
                'pivot_tetap' => 0,
            ];
            $pivotExisting = KurikulumMataKuliah::withTrashed()
                ->where('kurikulum_id', $kurikulum->id_kurikulum)
                ->whereIn('mata_kuliah_id', $mataKuliah->pluck('id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('mata_kuliah_id');

            foreach ($pemetaan as $kode => $data) {
                $mataKuliahId = $mataKuliah[$kode]->id;
                $pivot = $pivotExisting->get($mataKuliahId);

                if ($pivot === null) {
                    KurikulumMataKuliah::query()->create([
                        'kurikulum_id' => $kurikulum->id_kurikulum,
                        'mata_kuliah_id' => $mataKuliahId,
                        'semester_urutan' => $data['semester_urutan'],
                        'apakah_wajib' => $data['apakah_wajib'],
                        'catatan' => null,
                    ]);
                    $hasil['pivot_dibuat']++;

                    continue;
                }

                $pivotDipulihkan = $pivot->trashed();
                $pivotBerubah = (int) $pivot->semester_urutan !== $data['semester_urutan']
                    || (bool) $pivot->apakah_wajib !== $data['apakah_wajib'];
                $pivot->fill([
                    'semester_urutan' => $data['semester_urutan'],
                    'apakah_wajib' => $data['apakah_wajib'],
                ]);

                if ($pivotDipulihkan) {
                    $pivot->restore();
                }

                $pivot->save();
                $hasil[$pivotDipulihkan ? 'pivot_dipulihkan' : ($pivotBerubah ? 'pivot_diubah' : 'pivot_tetap')]++;
            }

            return $hasil;
        });
    }

    /** @param array<string, array<string, mixed>> $pemetaan */
    private function semuaMataKuliahTersedia(array $pemetaan, Prodi $prodi): bool
    {
        return MataKuliah::query()
            ->where('prodi_id', $prodi->id_prodi)
            ->whereIn('kode', array_keys($pemetaan))
            ->count() === count($pemetaan);
    }

    private function kurikulumBerubah(Kurikulum $kurikulum, array $data): bool
    {
        return $kurikulum->kode !== $data['kode']
            || $kurikulum->nama !== $data['nama']
            || (int) $kurikulum->tahun_berlaku !== $data['tahun_berlaku']
            || (float) $kurikulum->sks_lulus !== $data['sks_lulus']
            || (int) $kurikulum->semester_normal !== $data['semester_normal']
            || $kurikulum->status !== $data['status'];
    }
}
