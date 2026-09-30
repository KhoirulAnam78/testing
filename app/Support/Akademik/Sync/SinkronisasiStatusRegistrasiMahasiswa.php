<?php

namespace App\Support\Akademik\Sync;

use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StatusRegistrasiMahasiswa;
use App\Support\Akademik\AkademikClient;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SinkronisasiStatusRegistrasiMahasiswa
{
    private const LIMIT = 500;

    public function __construct(private readonly AkademikClient $client) {}

    /**
     * @return array{status: string, semester: string, selesai_pada: string, diterima: int, dibuat: int, diubah: int, tetap: int, dilewati: int, gagal: int, pesan: string}
     */
    public function handle(Semester $semester): array
    {
        $lock = Cache::lock('akademik:sync:status-registrasi:'.$semester->kode, 300);

        if (! $lock->get()) {
            throw new DomainException('Sinkronisasi semester ini sedang berjalan. Tunggu proses sebelumnya selesai.');
        }

        try {
            $hasil = $this->sinkronkan($semester);
            Cache::put(self::cacheKey($semester->id_semester), $hasil, now()->addDays(30));

            return $hasil;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{status: string, semester: string, selesai_pada: string, diterima: int, dibuat: int, diubah: int, tetap: int, dilewati: int, gagal: int, pesan: string}
     */
    public function handleMahasiswa(Semester $semester, Mahasiswa $mahasiswa): array
    {
        if ($mahasiswa->status === 'lulus') {
            throw new DomainException('Mahasiswa lulus tidak dapat disinkronkan.');
        }

        $lock = Cache::lock('akademik:sync:status-registrasi:'.$semester->kode, 300);

        if (! $lock->get()) {
            throw new DomainException('Sinkronisasi semester ini sedang berjalan. Tunggu proses sebelumnya selesai.');
        }

        try {
            return $this->sinkronkan($semester, $mahasiswa);
        } finally {
            $lock->release();
        }
    }

    public static function cacheKey(int $semesterId): string
    {
        return 'akademik:hasil-sync:status-registrasi:'.$semesterId;
    }

    /**
     * @return array{status: string, semester: string, selesai_pada: string, diterima: int, dibuat: int, diubah: int, tetap: int, dilewati: int, gagal: int, pesan: string}
     */
    private function sinkronkan(Semester $semester, ?Mahasiswa $target = null): array
    {
        $statistik = [
            'diterima' => 0,
            'dibuat' => 0,
            'diubah' => 0,
            'tetap' => 0,
            'dilewati' => 0,
            'gagal' => 0,
        ];
        $offset = 0;
        $seenNim = [];
        $semuaItems = [];

        do {
            $response = $this->client->get('/api/data/mahasiswa/registrasi', [
                'id_semester' => $semester->kode,
                'limit' => self::LIMIT,
                'offset' => $offset,
            ]);

            if (strtolower((string) ($response['status'] ?? '')) !== 'success') {
                throw new DomainException('API mengembalikan status bisnis yang gagal.');
            }

            $items = $response['data'] ?? null;

            if (! is_array($items) || ! array_is_list($items)) {
                throw new DomainException('Format data registrasi dari API tidak sesuai kontrak.');
            }

            $statistik['diterima'] += count($items);
            array_push($semuaItems, ...$items);

            $jumlah = count($items);
            $offset += $jumlah;
            $total = filter_var($response['total_records'] ?? $response['total'] ?? null, FILTER_VALIDATE_INT);
        } while ($jumlah === self::LIMIT && ($total === false || $offset < $total));

        $this->prosesData($semuaItems, $semester, $seenNim, $statistik, $target);

        return [
            'status' => 'success',
            'semester' => $semester->kode,
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            ...$statistik,
            'pesan' => $target
                ? "Status registrasi {$target->nama} berhasil disinkronkan."
                : 'Registrasi lunas disinkronkan sebagai aktif; mahasiswa lokal lainnya sebagai belum aktif.',
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<string, true>  $seenNim
     * @param  array{diterima: int, dibuat: int, diubah: int, tetap: int, dilewati: int, gagal: int}  $statistik
     */
    private function prosesData(
        array $items,
        Semester $semester,
        array &$seenNim,
        array &$statistik,
        ?Mahasiswa $target = null
    ): void {
        $targetNim = $target ? strtoupper(trim($target->nim)) : null;
        $mahasiswa = $target
            ? collect([$targetNim => $target])
            : Mahasiswa::query()
                ->get(['id_mahasiswa', 'nim', 'status'])
                ->keyBy(fn (Mahasiswa $item): string => strtoupper(trim($item->nim)));
        $dataApi = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $statistik['gagal']++;

                continue;
            }

            $nim = strtoupper(trim((string) ($item['nim'] ?? '')));
            $semesterApi = trim((string) ($item['id_semester'] ?? ''));
            $statusBayar = strtoupper(trim((string) ($item['status_bayar'] ?? '')));
            $jenisRegistrasi = strtoupper(trim((string) ($item['jenis_registrasi'] ?? '')));

            if ($targetNim !== null && $nim !== $targetNim) {
                continue;
            }

            if (
                $nim === '' || strlen($nim) > 255
                || $semesterApi !== $semester->kode
                || $statusBayar === '' || strlen($statusBayar) > 255
                || strlen($jenisRegistrasi) > 255
            ) {
                $statistik['gagal']++;

                continue;
            }

            $mahasiswaApi = $mahasiswa->get($nim);

            if (! $mahasiswaApi || $mahasiswaApi->status === 'lulus') {
                $statistik['dilewati']++;

                continue;
            }

            if (isset($seenNim[$nim])) {
                $statistik['dilewati']++;

                continue;
            }

            $seenNim[$nim] = true;
            $dataApi[$nim] = [
                'status' => $statusBayar === 'L' ? 'aktif' : 'belum_aktif',
                'status_bayar' => $statusBayar,
                'jenis_registrasi' => $jenisRegistrasi !== '' ? $jenisRegistrasi : null,
            ];
        }

        $mahasiswa = $mahasiswa->reject(fn (Mahasiswa $item): bool => $item->status === 'lulus');

        if ($mahasiswa->isEmpty()) {
            return;
        }

        $registrasi = StatusRegistrasiMahasiswa::query()
            ->where('semester_id', $semester->id_semester)
            ->whereIn('mahasiswa_id', $mahasiswa->pluck('id_mahasiswa'))
            ->get(['mahasiswa_id', 'status', 'jenis_registrasi', 'status_bayar'])
            ->keyBy('mahasiswa_id');

        $rows = [];
        $waktu = now();

        foreach ($mahasiswa as $nim => $item) {
            $dataBaru = $dataApi[$nim] ?? [
                'status' => 'belum_aktif',
                'status_bayar' => null,
                'jenis_registrasi' => null,
            ];
            $dataLama = $registrasi->get($item->id_mahasiswa);

            if (
                $dataLama
                && $dataLama->status === $dataBaru['status']
                && $dataLama->status_bayar === $dataBaru['status_bayar']
                && $dataLama->jenis_registrasi === $dataBaru['jenis_registrasi']
            ) {
                $statistik['tetap']++;
            } else {
                $statistik[$dataLama === null ? 'dibuat' : 'diubah']++;
            }

            $rows[] = [
                'mahasiswa_id' => $item->id_mahasiswa,
                'semester_id' => $semester->id_semester,
                ...$dataBaru,
                'last_synced_at' => $waktu,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ];
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, self::LIMIT) as $chunk) {
                DB::table('status_registrasi_mahasiswa')->upsert(
                    $chunk,
                    ['mahasiswa_id', 'semester_id'],
                    ['status', 'jenis_registrasi', 'status_bayar', 'last_synced_at', 'updated_at']
                );
            }
        });
    }
}
