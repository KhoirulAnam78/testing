<?php

namespace App\Support\Akademik\Sync;

use App\Models\Kurikulum;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use App\Support\Akademik\AkademikClient;
use DomainException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class SinkronisasiMahasiswa
{
    private const LIMIT = 100;

    private const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    private const CACHE_KEY = 'akademik:hasil-sync:mahasiswa';

    private const KURIKULUM_CACHE_KEY = 'akademik:hasil-sync:kurikulum-mahasiswa';

    public function __construct(private readonly AkademikClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return $this->jalankan(fn (): array => $this->sinkronkan());
    }

    /**
     * @return array<string, mixed>
     */
    public function handleSatu(string $nim): array
    {
        $nim = strtolower(trim($nim));

        if ($nim === '' || strlen($nim) > 255) {
            throw new DomainException('NIM mahasiswa tidak valid.');
        }

        return $this->jalankan(fn (): array => $this->sinkronkanSatu($nim));
    }

    /** @return array<string, mixed> */
    public function handleKurikulum(): array
    {
        return $this->jalankan(
            fn (): array => $this->sinkronkanKurikulum(),
            self::KURIKULUM_CACHE_KEY
        );
    }

    /** @return array<string, mixed> */
    public function handleKurikulumMahasiswa(Mahasiswa $mahasiswa): array
    {
        return $this->jalankan(
            fn (): array => $this->sinkronkanKurikulumMahasiswa($mahasiswa),
            null
        );
    }

    /**
     * @param  callable(): array<string, mixed>  $sinkronisasi
     * @return array<string, mixed>
     */
    private function jalankan(callable $sinkronisasi, ?string $cacheKey = self::CACHE_KEY): array
    {
        $lock = Cache::lock('akademik:sync:mahasiswa', 1800);

        if (! $lock->get()) {
            throw new DomainException('Sinkronisasi mahasiswa sedang berjalan. Tunggu proses sebelumnya selesai.');
        }

        try {
            $hasil = $sinkronisasi();
            if ($cacheKey !== null) {
                Cache::put($cacheKey, $hasil, now()->addDays(30));
            }

            return $hasil;
        } finally {
            $lock->release();
        }
    }

    public static function cacheKey(): string
    {
        return self::CACHE_KEY;
    }

    public static function cacheKeyKurikulum(): string
    {
        return self::KURIKULUM_CACHE_KEY;
    }

    /** @return array<string, mixed> */
    private function sinkronkanKurikulum(): array
    {
        $hasil = [
            'diperiksa' => 0,
            'diubah' => 0,
            'tetap' => 0,
            'dilewati' => 0,
            'rincian' => [],
        ];

        Mahasiswa::query()
            ->select(['id_mahasiswa', 'nim'])
            ->orderBy('id_mahasiswa')
            ->chunkById(self::LIMIT, function ($mahasiswa) use (&$hasil): void {
                foreach ($mahasiswa as $item) {
                    $hasil['diperiksa']++;

                    try {
                        $status = $this->perbaruiKurikulumMahasiswa($item);
                        $hasil[$status]++;
                    } catch (DomainException $e) {
                        $hasil['dilewati']++;

                        if (count($hasil['rincian']) < 20) {
                            $hasil['rincian'][] = strtoupper($item->nim).': '.$e->getMessage();
                        }
                    }
                }
            }, 'id_mahasiswa', 'id_mahasiswa');

        return $hasil + [
            'status' => 'success',
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'pesan' => $hasil['diperiksa'].' mahasiswa diperiksa; '.$hasil['diubah'].' diubah; '.$hasil['dilewati'].' dilewati.',
        ];
    }

    /** @return array<string, mixed> */
    private function sinkronkanKurikulumMahasiswa(Mahasiswa $mahasiswa): array
    {
        $status = $this->perbaruiKurikulumMahasiswa($mahasiswa);
        $hasil = [
            'diperiksa' => 1,
            'diubah' => $status === 'diubah' ? 1 : 0,
            'tetap' => $status === 'tetap' ? 1 : 0,
            'dilewati' => 0,
            'rincian' => [],
        ];

        return $hasil + [
            'status' => 'success',
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'pesan' => $status === 'diubah'
                ? 'Kurikulum mahasiswa '.strtoupper($mahasiswa->nim).' berhasil disesuaikan.'
                : 'Kurikulum mahasiswa '.strtoupper($mahasiswa->nim).' sudah sesuai.',
        ];
    }

    private function perbaruiKurikulumMahasiswa(Mahasiswa $mahasiswa): string
    {
        $detail = $this->ambilDetail(strtolower(trim($mahasiswa->nim)));

        return DB::transaction(function () use ($mahasiswa, $detail): string {
            $tersimpan = Mahasiswa::query()
                ->with('prodi:id_prodi,kode')
                ->lockForUpdate()
                ->findOrFail($mahasiswa->id_mahasiswa);
            $kodeKurikulum = $this->mapKurikulum($detail, trim((string) $tersimpan->prodi?->kode));
            $kurikulum = Kurikulum::query()
                ->where('prodi_id', $tersimpan->prodi_id)
                ->where('kode', $kodeKurikulum)
                ->lockForUpdate()
                ->first();

            if ($kurikulum === null) {
                throw new DomainException("Kurikulum {$kodeKurikulum} untuk prodi mahasiswa tidak ditemukan.");
            }

            if ((int) $tersimpan->kurikulum_id === (int) $kurikulum->id_kurikulum) {
                return 'tetap';
            }

            $tersimpan->update(['kurikulum_id' => $kurikulum->id_kurikulum]);

            return 'diubah';
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sinkronkan(): array
    {
        $offset = 0;
        $totalRecords = null;
        $nimApi = [];

        do {
            $response = $this->client->get('/api/data/mahasiswa', [
                'limit' => self::LIMIT,
                'offset' => $offset,
            ]);

            if (strtolower((string) ($response['status'] ?? '')) !== 'success') {
                throw new DomainException('API mengembalikan status bisnis daftar mahasiswa yang gagal.');
            }

            $items = $response['data'] ?? null;
            $jumlahTotal = filter_var($response['total_records'] ?? null, FILTER_VALIDATE_INT);
            $responseOffset = filter_var($response['offset'] ?? null, FILTER_VALIDATE_INT);

            if (! is_array($items) || ! array_is_list($items)) {
                throw new DomainException('Format daftar mahasiswa dari API tidak sesuai kontrak.');
            }

            if ($jumlahTotal === false || $jumlahTotal < 0 || $responseOffset !== $offset) {
                throw new DomainException('Metadata pagination mahasiswa dari API tidak valid.');
            }

            if ($totalRecords !== null && $totalRecords !== $jumlahTotal) {
                throw new DomainException('Jumlah mahasiswa berubah saat sinkronisasi. Ulangi proses.');
            }

            $totalRecords = $jumlahTotal;

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    throw new DomainException('Data mahasiswa API pada offset '.($offset + $index).' bukan object.');
                }

                $nim = strtolower(trim((string) ($item['nim'] ?? '')));

                if ($nim === '' || strlen($nim) > 255) {
                    throw new DomainException('NIM mahasiswa API pada offset '.($offset + $index).' tidak valid.');
                }

                if (isset($nimApi[$nim])) {
                    throw new DomainException("NIM {$nim} muncul lebih dari sekali pada daftar API.");
                }

                $nimApi[$nim] = true;
            }

            $jumlah = count($items);
            $offset += $jumlah;

            if ($jumlah === 0 && $offset < $totalRecords) {
                throw new DomainException('Pagination mahasiswa berhenti sebelum seluruh data diterima.');
            }
        } while ($offset < $totalRecords);

        if ($offset !== $totalRecords) {
            throw new DomainException('Jumlah mahasiswa tidak sesuai metadata API.');
        }

        if ($nimApi === []) {
            throw new DomainException('Mahasiswa tidak ditemukan di API.');
        }

        $hasil = [
            'dibuat' => 0,
            'diubah' => 0,
            'dipulihkan' => 0,
            'tetap' => 0,
            'dilewati' => 0,
            'foto_disimpan' => 0,
            'foto_tidak_tersedia' => 0,
            'foto_dipertahankan' => 0,
            'foto_gagal' => 0,
            'rincian' => [],
        ];
        $prodi = Prodi::query()->get(['id_prodi', 'kode'])->keyBy(fn (Prodi $item) => trim($item->kode));
        $prodiIds = $prodi->pluck('id_prodi', 'kode')->all();

        foreach (array_keys($nimApi) as $nim) {
            try {
                $detail = $this->ambilDetail($nim);
                $mapped = $this->map($detail, $prodiIds);
                $tersimpan = $this->simpan($mapped);
                $hasil[$tersimpan['hasil']]++;

                try {
                    $foto = $this->sinkronkanFoto($nim, $tersimpan['user']);
                } catch (Throwable $e) {
                    report($e);
                    $foto = 'foto_gagal';
                }

                $hasil[$foto]++;
            } catch (DomainException $e) {
                $hasil['dilewati']++;

                if (count($hasil['rincian']) < 20) {
                    $hasil['rincian'][] = "{$nim}: {$e->getMessage()}";
                }
            }
        }

        $hasil += [
            'status' => 'success',
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => $offset,
            'unik' => count($nimApi),
            'pesan' => count($nimApi).' mahasiswa diproses; '.$hasil['dilewati'].' dilewati.',
        ];

        return $hasil;
    }

    /**
     * @return array<string, mixed>
     */
    private function sinkronkanSatu(string $nim): array
    {
        $prodiIds = Prodi::query()
            ->get(['id_prodi', 'kode'])
            ->pluck('id_prodi', 'kode')
            ->all();
        $mapped = $this->map($this->ambilDetail($nim), $prodiIds);
        $tersimpan = $this->simpan($mapped);

        try {
            $foto = $this->sinkronkanFoto($nim, $tersimpan['user']);
        } catch (Throwable $e) {
            report($e);
            $foto = 'foto_gagal';
        }

        $hasil = [
            'dibuat' => 0,
            'diubah' => 0,
            'dipulihkan' => 0,
            'tetap' => 0,
            'dilewati' => 0,
            'foto_disimpan' => 0,
            'foto_tidak_tersedia' => 0,
            'foto_dipertahankan' => 0,
            'foto_gagal' => 0,
            'rincian' => [],
        ];
        $hasil[$tersimpan['hasil']]++;
        $hasil[$foto]++;

        return $hasil + [
            'status' => 'success',
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => 1,
            'unik' => 1,
            'pesan' => "Mahasiswa {$nim} berhasil disinkronkan.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ambilDetail(string $nim): array
    {
        try {
            $response = $this->client->getRaw('/api/data/mahasiswa/'.rawurlencode($nim), [], 'application/json');
        } catch (DomainException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        if ($response->status() === 404) {
            throw new DomainException('Detail tidak ditemukan di API.');
        }

        if (! $response->successful()) {
            throw new RuntimeException("Detail gagal dimuat dengan status HTTP {$response->status()}.");
        }

        $payload = $response->json();

        if (! is_array($payload)
            || strtolower((string) ($payload['status'] ?? '')) !== 'success'
            || ! is_array($payload['data'] ?? null)
        ) {
            throw new RuntimeException('Format detail dari API tidak sesuai kontrak.');
        }

        if (strtolower(trim((string) ($payload['data']['nim'] ?? ''))) !== $nim) {
            throw new DomainException('NIM detail berbeda dari NIM daftar API.');
        }

        return $payload['data'];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $prodi
     * @return array{nim: string, nama: string, email: ?string, no_hp: ?string, angkatan: int, status: string, prodi_id: int, kode_kurikulum: string}
     */
    private function map(array $item, array $prodi): array
    {
        $nim = strtolower(trim((string) ($item['nim'] ?? '')));
        $nama = trim((string) ($item['nama'] ?? ''));
        $email = strtolower(trim((string) ($item['email_mhs'] ?? '')));
        $email = $email === '' ? null : $email;
        $kodeProdi = trim((string) ($item['kd_prodi'] ?? ''));
        $kodeKurikulum = trim((string) ($item['kd_kur'] ?? ''));
        $angkatan = trim((string) ($item['angkatan'] ?? ''));

        if ($nim === '' || strlen($nim) > 255 || $nama === '' || strlen($nama) > 255) {
            throw new DomainException('NIM atau nama tidak valid.');
        }

        if ($email !== null && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
            throw new DomainException('Email tidak valid.');
        }

        if (! array_key_exists($kodeProdi, $prodi)) {
            throw new DomainException("Kode prodi {$kodeProdi} tidak ditemukan pada data lokal.");
        }

        if ($kodeKurikulum === '' || strlen($kodeKurikulum) > 255) {
            throw new DomainException('Kode kurikulum kosong atau tidak valid.');
        }

        if (! preg_match('/^\d{4}$/', $angkatan) || (int) $angkatan < 2000 || (int) $angkatan > 2100) {
            throw new DomainException('Angkatan tidak valid.');
        }

        return [
            'nim' => $nim,
            'nama' => $nama,
            'email' => $email,
            'no_hp' => $this->nullableString($item['hp_mhs'] ?? null)
                ?? $this->nullableString($item['telp_mhs'] ?? null),
            'angkatan' => (int) $angkatan,
            'status' => $this->mapStatus($item['status'] ?? null),
            'prodi_id' => $prodi[$kodeProdi],
            'kode_kurikulum' => $kodeKurikulum,
        ];
    }

    /** @param array<string, mixed> $item */
    private function mapKurikulum(array $item, string $kodeProdiLokal): string
    {
        $kodeProdi = trim((string) ($item['kd_prodi'] ?? ''));
        $kodeKurikulum = trim((string) ($item['kd_kur'] ?? ''));

        if ($kodeProdiLokal === '') {
            throw new DomainException('Program studi mahasiswa lokal tidak ditemukan.');
        }

        if ($kodeProdi === '' || $kodeProdi !== $kodeProdiLokal) {
            throw new DomainException('Program studi API tidak sesuai dengan program studi mahasiswa lokal.');
        }

        if ($kodeKurikulum === '' || strlen($kodeKurikulum) > 255) {
            throw new DomainException('Kode kurikulum kosong atau tidak valid.');
        }

        return $kodeKurikulum;
    }

    private function mapStatus(mixed $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'A', 'AKTIF' => 'aktif',
            'CUTI' => 'cuti',
            'LULUS' => 'lulus',
            'NONAKTIF', 'NON-AKTIF' => 'nonaktif',
            default => throw new DomainException('Status mahasiswa API tidak dikenali.'),
        };
    }

    /**
     * @param  array{nim: string, nama: string, email: ?string, no_hp: ?string, angkatan: int, status: string, prodi_id: int, kode_kurikulum: string}  $data
     * @return array{hasil: string, user: User}
     */
    private function simpan(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $kurikulum = Kurikulum::query()
                ->where('prodi_id', $data['prodi_id'])
                ->where('kode', $data['kode_kurikulum'])
                ->lockForUpdate()
                ->first();

            if ($kurikulum === null) {
                throw new DomainException("Kurikulum {$data['kode_kurikulum']} untuk prodi mahasiswa tidak ditemukan.");
            }

            unset($data['kode_kurikulum']);
            $data['kurikulum_id'] = $kurikulum->id_kurikulum;
            $mahasiswa = Mahasiswa::withTrashed()->where('nim', $data['nim'])->lockForUpdate()->first();
            $user = $mahasiswa?->user;
            $data['email'] = $this->emailEfektif($mahasiswa?->email, $user?->email, $data['email']);

            if ($data['email'] === null) {
                throw new DomainException('Email API dan email lokal tidak tersedia.');
            }

            $usernameConflict = User::query()->where('username', $data['nim'])
                ->when($user, fn ($query) => $query->whereKeyNot($user->id))
                ->exists();
            $emailConflict = User::query()->where('email', $data['email'])
                ->when($user, fn ($query) => $query->whereKeyNot($user->id))
                ->exists();

            if ($usernameConflict || $emailConflict) {
                throw new DomainException($usernameConflict
                    ? 'Username sudah digunakan akun lain.'
                    : 'Email sudah digunakan akun lain.');
            }

            $dibuat = $mahasiswa === null;
            $dipulihkan = $mahasiswa?->trashed() ?? false;
            $berubah = $mahasiswa && $this->mahasiswaBerubah($mahasiswa, $data);

            if (! $user) {
                $user = User::query()->create([
                    'name' => $data['nama'],
                    'username' => $data['nim'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['nim']),
                ]);
                $berubah = true;
            } else {
                $berubah = $berubah || $user->name !== $data['nama']
                    || $user->username !== $data['nim']
                    || $user->email !== $data['email'];
                $user->update([
                    'name' => $data['nama'],
                    'username' => $data['nim'],
                    'email' => $data['email'],
                ]);
            }

            $user->assignRole('mahasiswa');
            $payload = [
                ...$data,
                'user_id' => $user->id,
                'status_sync' => Mahasiswa::STATUS_SYNC_SYNCED,
                'synced_at' => now(),
            ];

            if ($mahasiswa) {
                $mahasiswa->fill($payload);

                if ($dipulihkan) {
                    $mahasiswa->restore();
                }

                $mahasiswa->save();
            } else {
                Mahasiswa::query()->create($payload);
            }

            return [
                'hasil' => $dibuat ? 'dibuat' : ($dipulihkan ? 'dipulihkan' : ($berubah ? 'diubah' : 'tetap')),
                'user' => $user,
            ];
        });
    }

    private function sinkronkanFoto(string $nim, User $user): string
    {
        if ($user->foto_profil && ! str_starts_with($user->foto_profil, 'foto-profil/akademik-')) {
            return 'foto_dipertahankan';
        }

        try {
            $response = $this->client->getRaw('/api/data/mahasiswa/'.rawurlencode($nim).'/foto');
        } catch (DomainException) {
            return 'foto_gagal';
        }

        if ($response->status() === 404) {
            return 'foto_tidak_tersedia';
        }

        if (! $response->successful()) {
            return 'foto_gagal';
        }

        try {
            [$extension, $body] = $this->validasiFoto($response);
        } catch (DomainException) {
            return 'foto_gagal';
        }
        $path = 'foto-profil/akademik-'.hash('sha256', $nim).'-'.hash('sha256', $body).'.'.$extension;

        if ($user->foto_profil === $path && Storage::disk('public')->exists($path)) {
            return 'foto_disimpan';
        }

        if (! Storage::disk('public')->put($path, $body)) {
            return 'foto_gagal';
        }

        $lama = $user->foto_profil;

        try {
            $user->update(['foto_profil' => $path]);
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        if ($lama && $lama !== $path && str_starts_with($lama, 'foto-profil/akademik-')) {
            Storage::disk('public')->delete($lama);
        }

        return 'foto_disimpan';
    }

    /**
     * @return array{string, string}
     */
    private function validasiFoto(Response $response): array
    {
        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_PHOTO_BYTES) {
            throw new DomainException('Ukuran foto API tidak valid.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new DomainException('Format foto API tidak didukung.'),
        };

        return [$extension, $body];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mahasiswaBerubah(Mahasiswa $mahasiswa, array $data): bool
    {
        foreach ($data as $key => $value) {
            if ((string) $mahasiswa->getAttribute($key) !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    private function emailEfektif(?string $emailLokal, ?string $emailUser, ?string $emailApi): ?string
    {
        return $emailApi ?? $emailUser ?? $emailLokal;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : substr($value, 0, 255);
    }
}
