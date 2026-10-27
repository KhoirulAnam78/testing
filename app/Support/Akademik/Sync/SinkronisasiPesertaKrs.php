<?php

namespace App\Support\Akademik\Sync;

use App\Models\AnggotaKelasKuliah;
use App\Models\KelasKuliah;
use App\Models\Mahasiswa;
use App\Models\PesertaBlok;
use App\Models\Semester;
use App\Support\Akademik\AkademikClient;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SinkronisasiPesertaKrs
{
    private const LIMIT = 200;

    public function __construct(private readonly AkademikClient $client) {}

    /**
     * @return array<string, int|string>
     */
    public function handle(Semester $semester): array
    {
        if ($semester->dikelolaLokal()) {
            throw new DomainException('Sinkronisasi peserta KRS ditolak: semester ini dikelola lokal.');
        }

        $lock = Cache::lock('akademik:sync:peserta-krs:'.$semester->kode, 600);

        if (! $lock->get()) {
            throw new DomainException('Sinkronisasi peserta KRS semester ini sedang berjalan.');
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
        return 'akademik:hasil-sync:peserta-krs:'.$semesterId;
    }

    /** @return array{items: list<array{nim: string, id_sumber_kelas: string, id_sumber_krs: ?string, status_sumber: ?string, sumber_json: array<string, mixed>}>, diterima: int, tidak_valid: int} */
    public static function petakanHeader(mixed $header, string $semester): array
    {
        if (! is_array($header) || ! is_array($header['detail'] ?? null) || ! array_is_list($header['detail'])) {
            return ['items' => [], 'diterima' => 1, 'tidak_valid' => 1];
        }

        $nim = strtoupper(trim((string) ($header['nim'] ?? '')));
        $semesterApi = trim((string) ($header['id_semester'] ?? ''));
        $statusKrsValid = ! array_key_exists('status_krs', $header) || is_scalar($header['status_krs']) || $header['status_krs'] === null;
        $statusKrs = is_scalar($header['status_krs'] ?? null) ? trim((string) $header['status_krs']) : null;
        $jumlahDetail = count($header['detail']);

        if (! $statusKrsValid || $nim === '' || strlen($nim) > 255 || $semesterApi !== $semester || ($statusKrs !== null && strlen($statusKrs) > 100)) {
            return ['items' => [], 'diterima' => max(1, $jumlahDetail), 'tidak_valid' => max(1, $jumlahDetail)];
        }

        $items = [];
        $tidakValid = 0;

        foreach ($header['detail'] as $detail) {
            $idKelas = is_array($detail) ? trim((string) ($detail['kd_kelas'] ?? '')) : '';
            $idKrsMentah = is_array($detail) ? ($detail['kd_krs'] ?? $header['kd_krs'] ?? null) : null;
            $idKrsValid = $idKrsMentah === null || is_scalar($idKrsMentah);
            $idKrs = $idKrsValid && $idKrsMentah !== null ? trim((string) $idKrsMentah) : null;

            if ($idKelas === '' || strlen($idKelas) > 255 || ! $idKrsValid || ($idKrs !== null && strlen($idKrs) > 255)) {
                $tidakValid++;

                continue;
            }

            $items[] = [
                'nim' => $nim,
                'id_sumber_kelas' => $idKelas,
                'id_sumber_krs' => $idKrs === '' ? null : $idKrs,
                'status_sumber' => $statusKrs,
                'sumber_json' => [
                    'header' => array_diff_key($header, ['detail' => true]),
                    'detail' => $detail,
                ],
            ];
        }

        return ['items' => $items, 'diterima' => $jumlahDetail, 'tidak_valid' => $tidakValid];
    }

    /**
     * @return array<string, int|string>
     */
    private function sinkronkan(Semester $semester): array
    {
        $data = $this->ambilDariApi($semester);
        $statistik = DB::transaction(fn (): array => $this->simpan($data['items'], $semester, $data['tidak_valid'] === 0));
        $statistik['tidak_valid'] += $data['tidak_valid'];
        $statistik['dilewati'] = array_sum([
            $statistik['duplikat'],
            $statistik['kelas_tidak_ditemukan'],
            $statistik['tidak_valid'],
        ]);

        return [
            'status' => 'success',
            'semester' => $semester->kode,
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => $data['diterima'],
            ...$statistik,
            'pesan' => "{$statistik['dibuat']} KRS dibuat, {$statistik['diubah']} diperbarui, {$statistik['dinonaktifkan']} tidak lagi ada di sumber, {$statistik['ditautkan']} tautan lokal dilengkapi; {$statistik['dilewati']} data dilewati.",
        ];
    }

    /**
     * @return array{items: list<array{nim: string, id_sumber_kelas: string, id_sumber_krs: ?string, status_sumber: ?string, sumber_json: array<string, mixed>}>, diterima: int, tidak_valid: int}
     */
    private function ambilDariApi(Semester $semester): array
    {
        $offset = 0;
        $totalRecords = null;
        $itemsApi = [];
        $diterima = 0;
        $tidakValid = 0;

        do {
            $response = $this->client->get('/api/data/krs', [
                'id_semester' => $semester->kode,
                'limit' => self::LIMIT,
                'offset' => $offset,
            ]);

            if (strtolower((string) ($response['status'] ?? '')) !== 'success') {
                throw new DomainException('API mengembalikan status bisnis KRS yang gagal.');
            }

            $items = $response['data'] ?? null;
            $jumlahTotal = filter_var($response['total_records'] ?? null, FILTER_VALIDATE_INT);
            $responseOffset = filter_var($response['offset'] ?? null, FILTER_VALIDATE_INT);

            if (! is_array($items) || ! array_is_list($items)) {
                throw new DomainException('Format data KRS dari API tidak sesuai kontrak.');
            }

            if ($jumlahTotal === false || $jumlahTotal < 0 || $responseOffset !== $offset) {
                throw new DomainException('Metadata pagination KRS dari API tidak valid.');
            }

            if ($totalRecords !== null && $totalRecords !== $jumlahTotal) {
                throw new DomainException('Jumlah peserta KRS berubah saat sinkronisasi. Ulangi proses.');
            }

            $totalRecords = $jumlahTotal;

            foreach ($items as $item) {
                $mapped = self::petakanHeader($item, $semester->kode);
                array_push($itemsApi, ...$mapped['items']);
                $diterima += $mapped['diterima'];
                $tidakValid += $mapped['tidak_valid'];
            }

            $jumlah = count($items);
            $offset += $jumlah;

            if ($jumlah === 0 && $offset < $totalRecords) {
                throw new DomainException('Pagination KRS berhenti sebelum seluruh data diterima.');
            }
        } while ($offset < $totalRecords);

        if ($offset !== $totalRecords) {
            throw new DomainException('Jumlah peserta KRS tidak sesuai metadata API.');
        }

        return ['items' => $itemsApi, 'diterima' => $diterima, 'tidak_valid' => $tidakValid];
    }

    /**
     * @param  list<array{nim: string, id_sumber_kelas: string, id_sumber_krs: ?string, status_sumber: ?string, sumber_json: array<string, mixed>}>  $items
     * @return array<string, int>
     */
    private function simpan(array $items, Semester $semester, bool $snapshotLengkap): array
    {
        $hasil = [
            'dibuat' => 0,
            'diubah' => 0,
            'diaktifkan' => 0,
            'dinonaktifkan' => 0,
            'ditautkan' => 0,
            'tanpa_peserta_blok' => 0,
            'tanpa_mahasiswa' => 0,
            'duplikat' => 0,
            'kelas_tidak_ditemukan' => 0,
            'tidak_valid' => 0,
        ];
        $kelas = KelasKuliah::query()
            ->where('semester_id', $semester->id_semester)
            ->whereNotNull('id_sumber')
            ->orderBy('id_kelas_kuliah')
            ->lockForUpdate()
            ->get()
            ->keyBy('id_sumber');
        $mahasiswa = Mahasiswa::query()
            ->whereIn('nim', array_unique(array_column($items, 'nim')))
            ->get(['id_mahasiswa', 'nim'])
            ->keyBy(fn (Mahasiswa $item): string => strtoupper(trim($item->nim)));
        $peserta = PesertaBlok::query()
            ->whereIn('blok_id', $kelas->pluck('blok_id')->filter()->unique())
            ->whereIn('mahasiswa_id', $mahasiswa->pluck('id_mahasiswa'))
            ->whereIn('status', ['aktif', 'mengulang'])
            ->orderBy('id_peserta_blok')
            ->lockForUpdate()
            ->get(['id_peserta_blok', 'blok_id', 'mahasiswa_id'])
            ->keyBy(fn (PesertaBlok $item): string => $item->blok_id.'|'.$item->mahasiswa_id);
        $anggota = AnggotaKelasKuliah::query()
            ->whereIn('kelas_kuliah_id', $kelas->pluck('id_kelas_kuliah'))
            ->orderBy('id_anggota_kelas_kuliah')
            ->lockForUpdate()
            ->get();
        $anggotaPerNim = $anggota->whereNotNull('nim_sumber')->keyBy(fn (AnggotaKelasKuliah $item): string => $item->kelas_kuliah_id.'|'.strtoupper(trim($item->nim_sumber)));
        $anggotaPerMahasiswa = $anggota->whereNotNull('mahasiswa_id')->keyBy(fn (AnggotaKelasKuliah $item): string => $item->kelas_kuliah_id.'|'.$item->mahasiswa_id);
        $seen = [];
        $rows = [];
        $waktu = now();

        foreach ($items as $item) {
            $identity = $item['nim'].'|'.$item['id_sumber_kelas'];

            if (isset($seen[$identity])) {
                $hasil['duplikat']++;

                continue;
            }

            $seen[$identity] = true;
            $kelasTujuan = $kelas->get($item['id_sumber_kelas']);
            $mahasiswaTujuan = $mahasiswa->get($item['nim']);
            $pesertaTujuan = $kelasTujuan?->blok_id && $mahasiswaTujuan
                ? $peserta->get($kelasTujuan->blok_id.'|'.$mahasiswaTujuan->id_mahasiswa)
                : null;
            if ($kelasTujuan === null) {
                $hasil['kelas_tidak_ditemukan']++;

                continue;
            }

            $kelasTujuanId = (int) $kelasTujuan->id_kelas_kuliah;
            $anggotaSaatIni = $anggotaPerNim->get($kelasTujuanId.'|'.$item['nim'])
                ?? ($mahasiswaTujuan ? $anggotaPerMahasiswa->get($kelasTujuanId.'|'.$mahasiswaTujuan->id_mahasiswa) : null);
            $baru = $anggotaSaatIni === null;
            $anggotaSaatIni ??= new AnggotaKelasKuliah(['kelas_kuliah_id' => $kelasTujuanId]);
            $mahasiswaId = $anggotaSaatIni->mahasiswa_id ?? $mahasiswaTujuan?->id_mahasiswa;
            $pesertaBlokId = $anggotaSaatIni->peserta_blok_id ?? $pesertaTujuan?->id_peserta_blok;
            $ditautkan = ($anggotaSaatIni->mahasiswa_id === null && $mahasiswaId !== null)
                || ($anggotaSaatIni->peserta_blok_id === null && $pesertaBlokId !== null);
            $sumber = in_array($anggotaSaatIni->sumber, ['lokal', 'lokal_api'], true) ? 'lokal_api' : 'api';
            $hasil['diaktifkan'] += (int) (! $baru && ! $anggotaSaatIni->aktif_sumber);
            $rows[] = [
                'kelas_kuliah_id' => $kelasTujuanId,
                'mahasiswa_id' => $mahasiswaId,
                'nim_sumber' => $item['nim'],
                'id_sumber_krs' => $item['id_sumber_krs'],
                'status_sumber' => $item['status_sumber'],
                'sumber' => $sumber,
                'aktif_sumber' => true,
                'missing_since' => null,
                'sumber_json' => json_encode($item['sumber_json'], JSON_THROW_ON_ERROR),
                'last_synced_at' => $waktu,
                'peserta_blok_id' => $pesertaBlokId,
                'created_at' => $anggotaSaatIni->created_at ?? $waktu,
                'updated_at' => $waktu,
            ];
            $hasil[$baru ? 'dibuat' : 'diubah']++;
            $hasil['ditautkan'] += (int) $ditautkan;
            $hasil['tanpa_mahasiswa'] += (int) ($mahasiswaId === null);
            $hasil['tanpa_peserta_blok'] += (int) ($pesertaBlokId === null);
        }

        if ($rows !== []) {
            DB::table('anggota_kelas_kuliah')->upsert(
                $rows,
                ['kelas_kuliah_id', 'nim_sumber'],
                ['mahasiswa_id', 'id_sumber_krs', 'status_sumber', 'sumber', 'aktif_sumber', 'missing_since', 'sumber_json', 'last_synced_at', 'peserta_blok_id', 'updated_at'],
            );
        }

        if ($snapshotLengkap) {
            $kelasPerId = $kelas->keyBy('id_kelas_kuliah');
            foreach ($anggota as $item) {
                $idSumberKelas = $kelasPerId->get($item->kelas_kuliah_id)?->id_sumber;
                $identity = strtoupper(trim((string) $item->nim_sumber)).'|'.$idSumberKelas;

                if (! in_array($item->sumber, ['api', 'lokal_api'], true) || isset($seen[$identity])) {
                    continue;
                }

                DB::table('anggota_kelas_kuliah')
                    ->where('id_anggota_kelas_kuliah', $item->id_anggota_kelas_kuliah)
                    ->update([
                        'aktif_sumber' => false,
                        'missing_since' => $item->missing_since ?? $waktu,
                        'updated_at' => $waktu,
                    ]);
                $hasil['dinonaktifkan'] += (int) $item->aktif_sumber;
            }
        }

        return $hasil;
    }
}
