<?php

namespace App\Support\Akademik\Sync;

use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Support\Akademik\AkademikClient;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SinkronisasiMataKuliah
{
    private const LIMIT = 500;

    private const CACHE_KEY = 'akademik:hasil-sync:mata-kuliah';

    public function __construct(private readonly AkademikClient $client) {}

    /**
     * @return array{status: string, prodi: string, selesai_pada: string, diterima: int, unik: int, dibuat: int, diubah: int, dipulihkan: int, tetap: int, pesan: string}
     */
    public function handle(Prodi $prodi): array
    {
        return $this->jalankan(fn (): array => $this->sinkronkan($prodi));
    }

    /**
     * @return array{status: string, prodi: string, selesai_pada: string, diterima: int, unik: int, dibuat: int, diubah: int, dipulihkan: int, tetap: int, pesan: string}
     */
    public function handleSatu(Prodi $prodi, string $kode): array
    {
        $kode = trim($kode);

        if ($kode === '' || strlen($kode) > 255) {
            throw new DomainException('Kode mata kuliah tidak valid.');
        }

        return $this->jalankan(fn (): array => $this->sinkronkan($prodi, $kode));
    }

    /**
     * @param  callable(): array{status: string, prodi: string, selesai_pada: string, diterima: int, unik: int, dibuat: int, diubah: int, dipulihkan: int, tetap: int, pesan: string}  $sinkronisasi
     * @return array{status: string, prodi: string, selesai_pada: string, diterima: int, unik: int, dibuat: int, diubah: int, dipulihkan: int, tetap: int, pesan: string}
     */
    private function jalankan(callable $sinkronisasi): array
    {
        $lock = Cache::lock('akademik:sync:mata-kuliah', 300);

        if (! $lock->get()) {
            throw new DomainException('Sinkronisasi mata kuliah sedang berjalan. Tunggu proses sebelumnya selesai.');
        }

        try {
            $hasil = $sinkronisasi();
            Cache::put(self::CACHE_KEY, $hasil, now()->addDays(30));

            return $hasil;
        } finally {
            $lock->release();
        }
    }

    public static function cacheKey(): string
    {
        return self::CACHE_KEY;
    }

    /**
     * @return array{status: string, prodi: string, selesai_pada: string, diterima: int, unik: int, dibuat: int, diubah: int, dipulihkan: int, tetap: int, pesan: string}
     */
    private function sinkronkan(Prodi $prodi, ?string $kodeExact = null): array
    {
        $offset = 0;
        $totalRecords = null;
        $mataKuliahApi = [];

        do {
            $query = [
                'kd_prodi' => $prodi->kode,
                'limit' => self::LIMIT,
                'offset' => $offset,
            ];

            if ($kodeExact !== null) {
                $query['search'] = $kodeExact;
            }

            $response = $this->client->get('/api/data/matakuliah', $query);

            if (strtolower((string) ($response['status'] ?? '')) !== 'success') {
                throw new DomainException('API mengembalikan status bisnis yang gagal.');
            }

            $items = $response['data'] ?? null;
            $jumlahTotal = filter_var($response['total_records'] ?? null, FILTER_VALIDATE_INT);
            $responseOffset = filter_var($response['offset'] ?? null, FILTER_VALIDATE_INT);

            if (! is_array($items) || ! array_is_list($items)) {
                throw new DomainException('Format data mata kuliah dari API tidak sesuai kontrak.');
            }

            if ($jumlahTotal === false || $jumlahTotal < 0 || $responseOffset !== $offset) {
                throw new DomainException('Metadata pagination mata kuliah dari API tidak valid.');
            }

            if ($totalRecords !== null && $totalRecords !== $jumlahTotal) {
                throw new DomainException('Jumlah data mata kuliah berubah saat sinkronisasi. Ulangi proses.');
            }

            $totalRecords = $jumlahTotal;

            foreach ($this->mapItems($items, $offset, $prodi->kode, $kodeExact) as $kode => $mapped) {

                if (isset($mataKuliahApi[$kode]) && $mataKuliahApi[$kode] !== $mapped) {
                    throw new DomainException("Data mata kuliah {$kode} duplikat dengan nilai berbeda.");
                }

                $mataKuliahApi[$kode] = $mapped;
            }

            $jumlah = count($items);
            $offset += $jumlah;

            if ($jumlah === 0 && $offset < $totalRecords) {
                throw new DomainException('Pagination mata kuliah berhenti sebelum seluruh data diterima.');
            }
        } while ($offset < $totalRecords);

        if ($offset !== $totalRecords) {
            throw new DomainException('Jumlah data mata kuliah tidak sesuai metadata API.');
        }

        if ($mataKuliahApi === []) {
            $pesan = $kodeExact === null
                ? "Mata kuliah untuk prodi {$prodi->kode} tidak ditemukan di API."
                : "Mata kuliah {$kodeExact} untuk prodi {$prodi->kode} tidak ditemukan di API.";

            throw new DomainException($pesan);
        }

        $statistik = $this->simpan($mataKuliahApi, $prodi);
        $unik = count($mataKuliahApi);
        $pesan = $kodeExact === null
            ? "{$unik} mata kuliah prodi {$prodi->kode} berhasil diproses dari {$offset} baris API."
            : "Mata kuliah {$kodeExact} prodi {$prodi->kode} berhasil diproses dari {$offset} baris API.";

        return [
            'status' => 'success',
            'prodi' => "{$prodi->kode} - {$prodi->nama}",
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => $offset,
            'unik' => $unik,
            ...$statistik,
            'pesan' => $pesan,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, array{kode: string, nama: string, sks: float}>
     */
    private function mapItems(array $items, int $offset, string $kodeProdi, ?string $kodeExact = null): array
    {
        $hasil = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new DomainException('Data mata kuliah API pada offset '.($offset + $index).' bukan object.');
            }

            if ($kodeExact !== null && (
                trim((string) ($item['kd_mk'] ?? '')) !== $kodeExact
                || trim((string) ($item['kd_prodi'] ?? '')) !== $kodeProdi
            )) {
                continue;
            }

            $mapped = $this->map($item, $offset + $index, $kodeProdi);
            $kode = $mapped['kode'];

            if (isset($hasil[$kode]) && $hasil[$kode] !== $mapped) {
                throw new DomainException("Data mata kuliah {$kode} duplikat dengan nilai berbeda.");
            }

            $hasil[$kode] = $mapped;
        }

        return $hasil;
    }

    /**
     * @param  array<string, array{kode: string, nama: string, sks: float}>  $mataKuliahApi
     * @return array{dibuat: int, diubah: int, dipulihkan: int, tetap: int}
     */
    private function simpan(array $mataKuliahApi, Prodi $prodi): array
    {
        $syncedAt = now();

        return DB::transaction(function () use ($mataKuliahApi, $prodi, $syncedAt): array {
            $hasil = ['dibuat' => 0, 'diubah' => 0, 'dipulihkan' => 0, 'tetap' => 0];
            $existing = MataKuliah::withTrashed()
                ->where('prodi_id', $prodi->id_prodi)
                ->whereIn('kode', array_keys($mataKuliahApi))
                ->lockForUpdate()
                ->get()
                ->keyBy('kode');

            foreach ($mataKuliahApi as $kode => $data) {
                $mataKuliah = $existing->get($kode);

                if ($mataKuliah === null) {
                    MataKuliah::query()->create([
                        'prodi_id' => $prodi->id_prodi,
                        ...$data,
                        'status' => 'aktif',
                        'status_sync' => MataKuliah::STATUS_SYNC_SYNCED,
                        'synced_at' => $syncedAt,
                    ]);
                    $hasil['dibuat']++;

                    continue;
                }

                $dipulihkan = $mataKuliah->trashed();
                $berubah = $this->berubah($mataKuliah, $data);
                $mataKuliah->fill([
                    ...$data,
                    'status_sync' => MataKuliah::STATUS_SYNC_SYNCED,
                    'synced_at' => $syncedAt,
                ]);

                if ($dipulihkan) {
                    $mataKuliah->restore();
                }

                $mataKuliah->save();
                $hasil[$dipulihkan ? 'dipulihkan' : ($berubah ? 'diubah' : 'tetap')]++;
            }

            return $hasil;
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{kode: string, nama: string, sks: float}
     */
    private function map(array $item, int $index, string $kodeProdi): array
    {
        $kode = trim((string) ($item['kd_mk'] ?? ''));
        $nama = trim((string) ($item['nm_mk'] ?? ''));
        $prodiApi = trim((string) ($item['kd_prodi'] ?? ''));
        $sks = filter_var($item['sks_mk'] ?? null, FILTER_VALIDATE_FLOAT);

        if (
            $kode === '' || strlen($kode) > 255
            || $nama === '' || strlen($nama) > 255
            || $prodiApi !== $kodeProdi
            || $sks === false || $sks < 0.5 || $sks > 99.9
            || abs($sks * 10 - round($sks * 10)) > 0.00001
        ) {
            throw new DomainException("Data mata kuliah API pada offset {$index} tidak valid.");
        }

        return [
            'kode' => $kode,
            'nama' => $nama,
            'sks' => (float) $sks,
        ];
    }

    /**
     * @param  array{kode: string, nama: string, sks: float}  $data
     */
    private function berubah(MataKuliah $mataKuliah, array $data): bool
    {
        return $mataKuliah->kode !== $data['kode']
            || $mataKuliah->nama !== $data['nama']
            || (float) $mataKuliah->sks !== $data['sks'];
    }
}
