<?php

namespace App\Support\Akademik\Sync;

use App\Models\Semester;
use App\Support\Akademik\AkademikClient;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SinkronisasiSemester
{
    private const LIMIT = 500;

    private const CACHE_KEY = 'akademik:hasil-sync:semester';

    public function __construct(private readonly AkademikClient $client) {}

    /**
     * @return array{status: string, selesai_pada: string, diterima: int, unik: int, dibuat: int, diubah: int, tetap: int, pesan: string}
     */
    public function handle(?string $kodeSemester, int $mulaiTahun): array
    {
        $lock = Cache::lock('akademik:sync:semester', 300);

        if (! $lock->get()) {
            throw new DomainException('Sinkronisasi semester sedang berjalan. Tunggu proses sebelumnya selesai.');
        }

        try {
            $hasil = $this->sinkronkan($kodeSemester, $mulaiTahun);
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
     * @return array{status: string, selesai_pada: string, diterima: int, unik: int, dibuat: int, diubah: int, tetap: int, pesan: string}
     */
    private function sinkronkan(?string $kodeSemester, int $mulaiTahun): array
    {
        $offset = 0;
        $diterima = 0;
        $semesterApi = [];

        do {
            $query = [
                'limit' => self::LIMIT,
                'offset' => $offset,
            ];

            if ($kodeSemester !== null) {
                $query['id_semester'] = $kodeSemester;
            } else {
                $query['mulai_tahun'] = (string) $mulaiTahun;
            }

            $response = $this->client->get('/api/data/semester', $query);

            if (strtolower((string) ($response['status'] ?? '')) !== 'success') {
                throw new DomainException('API mengembalikan status bisnis yang gagal.');
            }

            $items = $response['data'] ?? null;

            if (! is_array($items) || ! array_is_list($items)) {
                throw new DomainException('Format data semester dari API tidak sesuai kontrak.');
            }

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    throw new DomainException('Data semester API pada offset '.($offset + $index).' bukan object.');
                }

                $mapped = $this->map($item, $offset + $index);
                $kode = $mapped['kode'];

                if (isset($semesterApi[$kode]) && $semesterApi[$kode] !== $mapped) {
                    throw new DomainException("Data semester {$kode} berbeda antarprogram studi.");
                }

                $semesterApi[$kode] = $mapped;
            }

            $jumlah = count($items);
            $diterima += $jumlah;
            $offset += $jumlah;
        } while ($jumlah === self::LIMIT);

        if ($semesterApi === []) {
            throw new DomainException($kodeSemester
                ? "Semester {$kodeSemester} tidak ditemukan di API."
                : "Data semester mulai tahun {$mulaiTahun} tidak ditemukan di API.");
        }

        if (collect($semesterApi)->where('is_aktif', true)->count() > 1) {
            throw new DomainException('API mengembalikan lebih dari satu semester aktif.');
        }

        $statistik = ['dibuat' => 0, 'diubah' => 0, 'tetap' => 0];

        foreach (array_chunk($semesterApi, self::LIMIT) as $batch) {
            $hasilBatch = DB::transaction(function () use ($batch): array {
                $hasil = ['dibuat' => 0, 'diubah' => 0, 'tetap' => 0];
                $aktif = collect($batch)->firstWhere('is_aktif', true);

                if ($aktif !== null) {
                    Semester::query()
                        ->where('kode', '!=', $aktif['kode'])
                        ->where('is_aktif', true)
                        ->update(['is_aktif' => false]);
                }

                foreach ($batch as $data) {
                    $semester = Semester::withTrashed()->where('kode', $data['kode'])->first();

                    if ($semester === null) {
                        Semester::query()->create($data);
                        $hasil['dibuat']++;

                        continue;
                    }

                    $berubah = $semester->trashed() || $this->berubah($semester, $data);
                    $semester->fill($data);

                    if ($semester->trashed()) {
                        $semester->restore();
                    }

                    $semester->save();
                    $hasil[$berubah ? 'diubah' : 'tetap']++;
                }

                return $hasil;
            });

            foreach ($statistik as $nama => $jumlah) {
                $statistik[$nama] = $jumlah + $hasilBatch[$nama];
            }
        }

        $unik = count($semesterApi);

        return [
            'status' => 'success',
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => $diterima,
            'unik' => $unik,
            ...$statistik,
            'pesan' => "{$unik} semester berhasil diproses dari {$diterima} baris API.",
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{nama: string, tahun: int, kode: string, tanggal_mulai: ?string, tanggal_selesai: ?string, kontrak_mulai: ?string, kontrak_selesai: ?string, is_aktif: bool}
     */
    private function map(array $item, int $index): array
    {
        $kode = trim((string) ($item['id_semester'] ?? ''));
        $tahun = filter_var($item['kd_ta'] ?? null, FILTER_VALIDATE_INT);
        $kodeJenis = trim((string) ($item['kd_smt'] ?? ''));
        $nama = ['1' => 'ganjil', '2' => 'genap', '3' => 'pendek'][$kodeJenis] ?? null;
        $nilaiAktif = $item['is_semester_aktif'] ?? null;
        $aktif = match (true) {
            in_array($nilaiAktif, [1, '1', true], true) => true,
            in_array($nilaiAktif, [0, '0', false], true) => false,
            default => null,
        };

        if (
            $kode === '' || strlen($kode) > 255
            || $tahun === false || $tahun < 2000 || $tahun > 2100
            || $nama === null || $kode !== $tahun.$kodeJenis
            || $aktif === null
        ) {
            throw new DomainException("Data semester API pada offset {$index} tidak valid.");
        }

        $data = [
            'nama' => $nama,
            'tahun' => $tahun,
            'kode' => $kode,
            'tanggal_mulai' => $this->tanggal($item['tgl_mulai_kuliah1'] ?? null, false, $kode),
            'tanggal_selesai' => $this->tanggal($item['tgl_akhir_kuliah1'] ?? null, false, $kode),
            'kontrak_mulai' => $this->tanggal($item['tgl_mulai_krs'] ?? null, true, $kode),
            'kontrak_selesai' => $this->tanggal($item['tgl_akhir_krs'] ?? null, true, $kode),
            'is_aktif' => $aktif,
        ];

        if (
            ($data['tanggal_mulai'] !== null && $data['tanggal_selesai'] !== null && $data['tanggal_selesai'] < $data['tanggal_mulai'])
            || ($data['kontrak_mulai'] !== null && $data['kontrak_selesai'] !== null && $data['kontrak_selesai'] < $data['kontrak_mulai'])
        ) {
            throw new DomainException("Rentang tanggal semester {$kode} tidak valid.");
        }

        return $data;
    }

    private function tanggal(mixed $value, bool $denganWaktu, string $kode): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
            throw new DomainException("Format tanggal semester {$kode} tidak valid.");
        }

        $tanggal = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value);

        if ($tanggal === false || $tanggal->format('Y-m-d\TH:i:s') !== $value) {
            throw new DomainException("Format tanggal semester {$kode} tidak valid.");
        }

        return $tanggal->format($denganWaktu ? 'Y-m-d H:i:s' : 'Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function berubah(Semester $semester, array $data): bool
    {
        return $semester->nama !== $data['nama']
            || (int) $semester->tahun !== $data['tahun']
            || $semester->kode !== $data['kode']
            || $semester->tanggal_mulai?->format('Y-m-d') !== $data['tanggal_mulai']
            || $semester->tanggal_selesai?->format('Y-m-d') !== $data['tanggal_selesai']
            || $semester->kontrak_mulai?->format('Y-m-d H:i:s') !== $data['kontrak_mulai']
            || $semester->kontrak_selesai?->format('Y-m-d H:i:s') !== $data['kontrak_selesai']
            || (bool) $semester->is_aktif !== $data['is_aktif'];
    }
}
