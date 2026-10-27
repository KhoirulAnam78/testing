<?php

namespace App\Support\Akademik\Sync;

use App\Models\KelasKuliah;
use App\Models\MataKuliah;
use App\Models\Semester;
use App\Support\Akademik\AkademikClient;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SinkronisasiKelasKuliah
{
    private const LIMIT = 500;

    public function __construct(private readonly AkademikClient $client) {}

    /** @return array{status: string, semester: string, selesai_pada: string, diterima: int, dipilih: int, dibuat: int, diubah: int, dipulihkan: int, tetap: int, pesan: string} */
    public function handle(Semester $semester): array
    {
        $this->pastikanBersumberDariApi($semester);

        return $this->jalankan($semester);
    }

    /** @return list<array{id_sumber: string, kode_prodi: string, kode_mata_kuliah: string, kode: string, nama: string, kapasitas: int, sumber_json: array<string, mixed>}> */
    public function ambil(Semester $semester): array
    {
        $this->pastikanBersumberDariApi($semester);

        return array_values($this->ambilDariApi($semester)['items']);
    }

    private function pastikanBersumberDariApi(Semester $semester): void
    {
        if ($semester->dikelolaLokal()) {
            throw new DomainException('Sinkronisasi kelas kuliah ditolak: semester ini dikelola lokal.');
        }
    }

    /** @return array{status: string, semester: string, selesai_pada: string, diterima: int, dipilih: int, dibuat: int, diubah: int, dipulihkan: int, tetap: int, tanpa_mata_kuliah: int, pesan: string} */
    private function jalankan(Semester $semester): array
    {
        $lock = Cache::lock('akademik:sync:kelas-kuliah:'.$semester->kode, 300);

        if (! $lock->get()) {
            throw new DomainException('Sinkronisasi kelas kuliah semester ini sedang berjalan.');
        }

        try {
            $hasil = $this->sinkronkan($semester);
            Cache::put(self::cacheKey($semester->id_semester), $hasil, now()->addDays(30));

            return $hasil;
        } finally {
            $lock->release();
        }
    }

    public static function cacheKey(int $semesterId): string
    {
        return 'akademik:hasil-sync:kelas-kuliah:'.$semesterId;
    }

    /** @return array{status: string, semester: string, selesai_pada: string, diterima: int, dipilih: int, dibuat: int, diubah: int, dipulihkan: int, tetap: int, tanpa_mata_kuliah: int, pesan: string} */
    private function sinkronkan(Semester $semester): array
    {
        ['items' => $itemsApi, 'diterima' => $diterima] = $this->ambilDariApi($semester);

        $dipilih = count($itemsApi);
        $statistik = $this->simpan($itemsApi, $semester);

        return [
            'status' => 'success',
            'semester' => $semester->kode,
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => $diterima,
            'dipilih' => $dipilih,
            ...$statistik,
            'pesan' => "{$dipilih} dari {$diterima} kelas kuliah semester {$semester->kode} berhasil diproses.",
        ];
    }

    /**
     * @return array{items: array<string, array{id_sumber: string, kode_prodi: string, kode_mata_kuliah: string, kode: string, nama: string, kapasitas: int, sumber_json: array<string, mixed>}>, diterima: int}
     */
    private function ambilDariApi(Semester $semester): array
    {
        $offset = 0;
        $totalRecords = null;
        $itemsApi = [];

        do {
            $response = $this->client->get('/api/data/kelas', [
                'id_semester' => $semester->kode,
                'limit' => self::LIMIT,
                'offset' => $offset,
            ]);

            if (strtolower((string) ($response['status'] ?? '')) !== 'success') {
                throw new DomainException('API mengembalikan status bisnis yang gagal.');
            }

            $items = $response['data'] ?? null;
            $jumlahTotal = filter_var($response['total_records'] ?? null, FILTER_VALIDATE_INT);
            $responseOffset = filter_var($response['offset'] ?? null, FILTER_VALIDATE_INT);

            if (! is_array($items) || ! array_is_list($items)) {
                throw new DomainException('Format data kelas kuliah dari API tidak sesuai kontrak.');
            }

            if ($jumlahTotal === false || $jumlahTotal < 0 || $responseOffset !== $offset) {
                throw new DomainException('Metadata pagination kelas kuliah dari API tidak valid.');
            }

            if ($totalRecords !== null && $totalRecords !== $jumlahTotal) {
                throw new DomainException('Jumlah kelas kuliah berubah saat sinkronisasi. Ulangi proses.');
            }

            $totalRecords = $jumlahTotal;

            foreach ($items as $index => $item) {
                $mapped = $this->map($item, $offset + $index, $semester);
                $identity = $mapped['id_sumber'];

                if (isset($itemsApi[$identity]) && $itemsApi[$identity] !== $mapped) {
                    throw new DomainException("Kelas kuliah {$identity} duplikat dengan nilai berbeda.");
                }

                $itemsApi[$identity] = $mapped;
            }

            $jumlah = count($items);
            $offset += $jumlah;

            if ($jumlah === 0 && $offset < $totalRecords) {
                throw new DomainException('Pagination kelas kuliah berhenti sebelum seluruh data diterima.');
            }
        } while ($offset < $totalRecords);

        if ($offset !== $totalRecords) {
            throw new DomainException('Jumlah kelas kuliah tidak sesuai metadata API.');
        }

        return [
            'items' => $itemsApi,
            'diterima' => $offset,
        ];
    }

    /**
     * @return array{id_sumber: string, kode_prodi: string, kode_mata_kuliah: string, kode: string, nama: string, kapasitas: int, sumber_json: array<string, mixed>}
     */
    private function map(mixed $item, int $index, Semester $semester): array
    {
        if (! is_array($item)) {
            throw new DomainException("Data kelas kuliah API pada offset {$index} bukan object.");
        }

        $idApi = trim((string) ($item['kd_kelas'] ?? ''));
        $semesterApi = trim((string) ($item['id_semester'] ?? ''));
        $kodeProdi = trim((string) ($item['kd_prodi'] ?? ''));
        $kodeMataKuliah = trim((string) ($item['kd_mk'] ?? ''));
        $kode = trim((string) ($item['kelas_pararel'] ?? ''));
        $kapasitas = filter_var($item['max_peserta'] ?? null, FILTER_VALIDATE_INT);
        $idSumber = $idApi !== ''
            ? $idApi
            : 'fallback:'.implode('|', [$semesterApi, $kodeProdi, $kodeMataKuliah, $kode]);

        if (
            $semesterApi !== $semester->kode
            || $kodeProdi === '' || strlen($kodeProdi) > 255
            || $kodeMataKuliah === '' || strlen($kodeMataKuliah) > 255
            || $kode === '' || strlen($kode) > 255
            || strlen($idSumber) > 255
            || $kapasitas === false || $kapasitas < 0 || $kapasitas > 65535
        ) {
            throw new DomainException("Data kelas kuliah API pada offset {$index} tidak valid.");
        }

        return [
            'id_sumber' => $idSumber,
            'kode_prodi' => $kodeProdi,
            'kode_mata_kuliah' => $kodeMataKuliah,
            'kode' => $kode,
            'nama' => 'Kelas '.$kode,
            'kapasitas' => $kapasitas,
            'sumber_json' => $item,
        ];
    }

    /**
     * @param  array<string, array{id_sumber: string, kode_prodi: string, kode_mata_kuliah: string, kode: string, nama: string, kapasitas: int, sumber_json: array<string, mixed>}>  $itemsApi
     * @return array{dibuat: int, diubah: int, dipulihkan: int, tetap: int, tanpa_mata_kuliah: int}
     */
    private function simpan(array $itemsApi, Semester $semester): array
    {
        $mataKuliah = MataKuliah::query()
            ->with('prodi:id_prodi,kode')
            ->whereIn('kode', array_unique(array_column($itemsApi, 'kode_mata_kuliah')))
            ->get()
            ->keyBy(fn (MataKuliah $item) => $item->prodi?->kode.'|'.$item->kode);
        $syncedAt = now();

        return DB::transaction(function () use ($itemsApi, $semester, $mataKuliah, $syncedAt): array {
            $hasil = ['dibuat' => 0, 'diubah' => 0, 'dipulihkan' => 0, 'tetap' => 0, 'tanpa_mata_kuliah' => 0];
            $rows = [];

            foreach ($itemsApi as $item) {
                $mk = $mataKuliah->get($item['kode_prodi'].'|'.$item['kode_mata_kuliah']);
                $hasil['tanpa_mata_kuliah'] += (int) ($mk === null);
                $kelas = KelasKuliah::withTrashed()
                    ->where('id_sumber', $item['id_sumber'])
                    ->lockForUpdate()
                    ->first();

                if ($kelas === null && $mk !== null) {
                    $fallback = KelasKuliah::withTrashed()
                        ->whereNull('id_sumber')
                        ->where('semester_id', $semester->id_semester)
                        ->where('mata_kuliah_id', $mk->id)
                        ->where('kode', $item['kode'])
                        ->lockForUpdate()
                        ->get();

                    if ($fallback->count() > 1) {
                        throw new DomainException("Kelas lama {$item['kode']} ambigu dan tidak dapat dipasangkan otomatis.");
                    }

                    $kelas = $fallback->first();

                    if ($kelas !== null) {
                        $kelas->update(['id_sumber' => $item['id_sumber']]);
                    }
                }

                $data = [
                    'semester_id' => $semester->id_semester,
                    'mata_kuliah_id' => $mk?->id ?? $kelas?->mata_kuliah_id,
                    'id_sumber' => $item['id_sumber'],
                    'kode_prodi_sumber' => $item['kode_prodi'],
                    'kode_mata_kuliah_sumber' => $item['kode_mata_kuliah'],
                    'sumber_json' => $item['sumber_json'],
                    'kode' => $item['kode'],
                    'nama' => $item['nama'],
                    'kapasitas' => $item['kapasitas'],
                    'last_synced_at' => $syncedAt,
                ];

                $dipulihkan = $kelas?->trashed() ?? false;
                $berubah = $kelas?->fill($data)->isDirty(collect($data)->except('last_synced_at')->keys()->all()) ?? true;
                $hasil[$kelas === null ? 'dibuat' : ($dipulihkan ? 'dipulihkan' : ($berubah ? 'diubah' : 'tetap'))]++;
                $rows[] = [
                    ...$data,
                    'sumber_json' => json_encode($data['sumber_json'], JSON_THROW_ON_ERROR),
                    'status' => $kelas?->status ?? 'aktif',
                    'created_at' => $kelas?->created_at ?? $syncedAt,
                    'updated_at' => $syncedAt,
                    'deleted_at' => null,
                ];
            }

            if ($rows !== []) {
                DB::table('kelas_kuliah')->upsert(
                    $rows,
                    ['id_sumber'],
                    [
                        'semester_id',
                        'mata_kuliah_id',
                        'kode_prodi_sumber',
                        'kode_mata_kuliah_sumber',
                        'sumber_json',
                        'kode',
                        'nama',
                        'kapasitas',
                        'last_synced_at',
                        'updated_at',
                        'deleted_at',
                    ],
                );
            }

            return $hasil;
        });
    }
}
