<?php

namespace App\Support\Akademik\Sync;

use App\Models\Dosen;
use App\Models\Prodi;
use App\Models\User;
use App\Support\Akademik\AkademikClient;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class SinkronisasiDosen
{
    private const LIMIT = 200;

    private const CACHE_KEY = 'akademik:hasil-sync:dosen';

    public function __construct(private readonly AkademikClient $client) {}

    /** @return array<string, mixed> */
    public function handle(Prodi $prodi): array
    {
        return $this->jalankan(fn (): array => $this->sinkronkan($prodi));
    }

    /** @return array<string, mixed> */
    public function handleSatu(Dosen $dosen): array
    {
        $identifiers = array_values(array_unique(array_filter([
            $this->nullableString($dosen->kd_dosen),
            $this->nullableString($dosen->nip),
            $this->nullableString($dosen->nidn),
        ])));

        if ($identifiers === []) {
            throw new DomainException('Kode dosen, NIP, atau NIDN wajib tersedia dan valid.');
        }

        return $this->jalankan(fn (): array => $this->sinkronkanIdentifiers($identifiers, $dosen));
    }

    /** @return list<array<string, mixed>> */
    public function cari(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 3 || mb_strlen($search) > 100) {
            throw new DomainException('Pencarian dosen wajib berisi 3 sampai 100 karakter.');
        }

        $items = $this->ambilSemua([
            'search' => $search,
            'include_pengampu_fk' => 'true',
        ]);
        $prodi = $this->prodiLokal();
        $mappedItems = [];
        $pemilikIdentifier = [];

        foreach ($items as $item) {
            try {
                if (! is_array($item)) {
                    continue;
                }

                $mapped = $this->map($item, $prodi);
                $mappedItems[] = $mapped;
                $index = array_key_last($mappedItems);

                foreach ($this->identifiers($mapped) as $identifier) {
                    $pemilikIdentifier[$identifier][] = $index;
                }
            } catch (DomainException) {
                // Kandidat tidak valid tidak boleh ditawarkan untuk disimpan.
            }
        }

        return array_values(array_map(function (array $mapped): array {
            $identifier = $this->identifier($mapped['kd_dosen'], $mapped['nip'], $mapped['nidn']);

            return [
                'token' => $this->token($mapped),
                'identifier' => $identifier,
                'kd_dosen' => $mapped['kd_dosen'],
                'nip' => $mapped['nip'],
                'nidn' => $mapped['nidn'],
                'nama' => $mapped['nama'],
                'email' => $mapped['email'],
                'prodi' => $mapped['kode_prodi'],
            ];
        }, array_filter($mappedItems, function (array $mapped) use ($pemilikIdentifier): bool {
            foreach ($this->identifiers($mapped) as $identifier) {
                if (count($pemilikIdentifier[$identifier]) > 1) {
                    return false;
                }
            }

            return true;
        })));
    }

    /** @return array<string, mixed> */
    public function tambah(string $token): array
    {
        if (! preg_match('/\A[a-f0-9]{48}\z/D', $token)) {
            throw new DomainException('Kandidat dosen tidak valid. Cari ulang data dari API.');
        }

        $payload = Cache::pull($this->tokenKey($token));

        if (! is_array($payload) || ! is_array($payload['identifiers'] ?? null)) {
            throw new DomainException('Kandidat dosen kedaluwarsa. Cari ulang data dari API.');
        }

        return $this->jalankan(fn (): array => $this->sinkronkanIdentifiers($payload['identifiers']));
    }

    public static function cacheKey(): string
    {
        return self::CACHE_KEY;
    }

    /**
     * @param  callable(): array<string, mixed>  $sinkronisasi
     * @return array<string, mixed>
     */
    private function jalankan(callable $sinkronisasi): array
    {
        $lock = Cache::lock('akademik:sync:dosen', 600);

        if (! $lock->get()) {
            throw new DomainException('Sinkronisasi dosen sedang berjalan. Tunggu proses sebelumnya selesai.');
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
    private function sinkronkan(Prodi $prodi): array
    {
        $items = $this->ambilSemua([
            'kd_prodi' => trim($prodi->kode),
            'include_pengampu_fk' => 'false',
        ]);

        return $this->proses($items, $prodi, null);
    }

    /** @return array<string, mixed> */
    private function sinkronkanIdentifiers(array $identifiers, ?Dosen $dosen = null): array
    {
        foreach ($identifiers as $identifier) {
            $identifier = $this->identifier($this->nullableString($identifier), null, null);
            $items = $this->ambilSemua([
                'search' => $identifier,
                'include_pengampu_fk' => 'true',
            ]);
            $exact = [];

            foreach ($items as $item) {
                if (is_array($item) && in_array($identifier, $this->identifierItem($item), true)) {
                    $exact[] = $item;
                }
            }

            if (count($exact) > 1) {
                throw new DomainException("Identifier {$identifier} ditemukan pada lebih dari satu data API.");
            }

            if (count($exact) === 1) {
                return $this->proses($exact, null, $dosen);
            }
        }

        throw new DomainException('Dosen '.implode(' / ', $identifiers).' tidak ditemukan di API.');
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return list<mixed>
     */
    private function ambilSemua(array $query): array
    {
        $offset = 0;
        $total = null;
        $hasil = [];

        do {
            $response = $this->client->get('/api/data/dosen', [
                ...$query,
                'limit' => self::LIMIT,
                'offset' => $offset,
            ]);

            if (strtolower((string) ($response['status'] ?? '')) !== 'success') {
                throw new DomainException('API mengembalikan status bisnis daftar dosen yang gagal.');
            }

            $items = $response['data'] ?? null;
            $jumlahTotal = filter_var($response['total_records'] ?? null, FILTER_VALIDATE_INT);
            $responseOffset = filter_var($response['offset'] ?? null, FILTER_VALIDATE_INT);

            if (! is_array($items) || ! array_is_list($items)) {
                throw new DomainException('Format daftar dosen dari API tidak sesuai kontrak.');
            }

            if ($jumlahTotal === false || $jumlahTotal < 0 || $responseOffset !== $offset) {
                throw new DomainException('Metadata pagination dosen dari API tidak valid.');
            }

            if ($total !== null && $total !== $jumlahTotal) {
                throw new DomainException('Jumlah dosen berubah saat sinkronisasi. Ulangi proses.');
            }

            $total = $jumlahTotal;
            array_push($hasil, ...$items);
            $jumlah = count($items);
            $offset += $jumlah;

            if ($jumlah === 0 && $offset < $total) {
                throw new DomainException('Pagination dosen berhenti sebelum seluruh data diterima.');
            }
        } while ($offset < $total);

        if ($offset !== $total) {
            throw new DomainException('Jumlah dosen tidak sesuai metadata API.');
        }

        return $hasil;
    }

    /**
     * @param  list<mixed>  $items
     * @return array<string, mixed>
     */
    private function proses(array $items, ?Prodi $prodi, ?Dosen $dosenExact): array
    {
        $hasil = [
            'dibuat' => 0,
            'diubah' => 0,
            'dipulihkan' => 0,
            'tetap' => 0,
            'dilewati' => 0,
            'rincian' => [],
        ];
        $prodiLokal = $this->prodiLokal();
        $mappedItems = [];
        $pemilikIdentifier = [];

        foreach ($items as $index => $item) {
            try {
                if (! is_array($item)) {
                    throw new DomainException('Data bukan object.');
                }

                $mapped = $this->map($item, $prodiLokal, $prodi);
                $mappedItems[$index] = $mapped;

                foreach ($this->identifiers($mapped) as $identifier) {
                    $pemilikIdentifier[$identifier][] = $index;
                }
            } catch (DomainException $e) {
                $hasil['dilewati']++;
                $hasil['rincian'][] = 'Baris '.($index + 1).': '.$e->getMessage();
            }
        }

        $ambigu = [];
        foreach ($pemilikIdentifier as $identifier => $indexes) {
            if (count($indexes) > 1) {
                foreach ($indexes as $index) {
                    $ambigu[$index][] = $identifier;
                }
            }
        }

        foreach ($mappedItems as $index => $mapped) {
            if (isset($ambigu[$index])) {
                $hasil['dilewati']++;
                $hasil['rincian'][] = 'Baris '.($index + 1).': identifier '.implode(', ', $ambigu[$index]).' ambigu pada data API.';

                continue;
            }

            try {
                $status = $this->simpan($mapped, $dosenExact);
                $hasil[$status]++;
            } catch (DomainException $e) {
                $hasil['dilewati']++;
                $hasil['rincian'][] = 'Baris '.($index + 1).': '.$e->getMessage();
            }
        }

        $diproses = count($items) - $hasil['dilewati'];
        $labelProdi = $prodi ? " prodi {$prodi->kode}" : '';
        $hasil += [
            'status' => 'success',
            'prodi' => $prodi ? "{$prodi->kode} - {$prodi->nama}" : null,
            'selesai_pada' => now()->format('d-m-Y H:i:s'),
            'diterima' => count($items),
            'unik' => $diproses,
            'pesan' => "{$diproses} dosen{$labelProdi} berhasil diproses; {$hasil['dilewati']} dilewati.",
        ];

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $prodiLokal
     * @return array<string, mixed>
     */
    private function map(array $item, array $prodiLokal, ?Prodi $prodiPilihan = null): array
    {
        $kdDosen = $this->nullableString($item['kd_dosen'] ?? $item['kode_dosen'] ?? null);
        $nipApi = $this->nullableString($item['nip'] ?? $item['nip_dosen'] ?? null);

        if ($kdDosen !== null && $nipApi !== null && $kdDosen !== $nipApi) {
            throw new DomainException('Kode dosen dan NIP API berbeda.');
        }

        $nip = $kdDosen ?? $nipApi;
        $nidn = $this->nullableString($item['nidn'] ?? null);
        $nama = trim((string) ($item['nm_dosen'] ?? $item['nama'] ?? $item['nama_dosen'] ?? ''));
        $kodeProdi = $this->nullableString($item['kd_prodi'] ?? $item['kode_prodi'] ?? null);
        $email = strtolower((string) ($this->nullableString($item['email'] ?? $item['email_dosen'] ?? null) ?? ''));
        $noHp = $this->nullableString($item['no_hp'] ?? $item['hp_dosen'] ?? $item['telp_dosen'] ?? $item['mobile'] ?? null);
        $gelarDepan = $this->nullableString($item['gelar_depan'] ?? $item['gelar_depan_dosen'] ?? null);
        $gelarBelakang = $this->nullableString($item['gelar_belakang'] ?? $item['gelar_belakang_dosen'] ?? null);
        $bidangKeahlian = $this->nullableString($item['bidang_keahlian'] ?? $item['keahlian'] ?? $item['bidang'] ?? null);
        [$gelarDepanFormat, $gelarBelakangFormat] = $this->gelarDariNamaFormat($nama, $item['nm_dosen_f'] ?? null);
        $gelarDepan ??= $gelarDepanFormat;
        $gelarBelakang ??= $gelarBelakangFormat;

        $this->identifier($kdDosen, $nip, $nidn);

        if ($nama === '' || mb_strlen($nama) > 255) {
            throw new DomainException('Nama dosen kosong atau tidak valid.');
        }

        foreach ([$kdDosen, $nip, $nidn, $kodeProdi, $noHp, $gelarDepan, $gelarBelakang, $bidangKeahlian] as $value) {
            if (mb_strlen(trim((string) $value)) > 255) {
                throw new DomainException('Teks data dosen API melebihi 255 karakter.');
            }
        }

        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
            throw new DomainException('Email dosen tidak valid.');
        }

        if ($prodiPilihan && $kodeProdi !== null && $kodeProdi !== trim($prodiPilihan->kode)) {
            throw new DomainException("Kode prodi {$kodeProdi} berbeda dari prodi yang dipilih.");
        }

        $kodeProdiEfektif = $kodeProdi ?? ($prodiPilihan ? trim($prodiPilihan->kode) : null);

        return [
            'kd_dosen' => $kdDosen,
            'nip' => $nip,
            'nidn' => $nidn,
            'nama' => $nama,
            'email' => $email === '' ? null : $email,
            'no_hp' => $noHp,
            'gelar_depan' => $gelarDepan,
            'gelar_belakang' => $gelarBelakang,
            'bidang_keahlian' => $bidangKeahlian,
            'status' => $this->mapStatus($item['status'] ?? $item['status_dosen'] ?? 'aktif'),
            'prodi_id' => $kodeProdiEfektif === null ? null : ($prodiLokal[$kodeProdiEfektif] ?? null),
            'kode_prodi' => $kodeProdiEfektif,
        ];
    }

    /** @param array<string, mixed> $data */
    private function simpan(array $data, ?Dosen $dosenExact = null): string
    {
        return DB::transaction(function () use ($data, $dosenExact): string {
            $kdDosenMatch = $data['kd_dosen'] ? Dosen::withTrashed()->where('kd_dosen', $data['kd_dosen'])->lockForUpdate()->first() : null;
            $nipMatch = $data['nip'] ? Dosen::withTrashed()->where('nip', $data['nip'])->lockForUpdate()->first() : null;
            $nidnMatch = $data['nidn'] ? Dosen::withTrashed()->where('nidn', $data['nidn'])->lockForUpdate()->first() : null;
            $matches = collect([$kdDosenMatch, $nipMatch, $nidnMatch])->filter()->unique(fn (Dosen $dosen) => $dosen->getKey());

            if ($matches->count() > 1) {
                throw new DomainException('Kode dosen, NIP, dan NIDN terhubung ke dosen lokal yang berbeda.');
            }

            $dosen = $matches->first();

            if (! $dosen && ! $dosenExact && $data['kd_dosen']) {
                $dosen = $this->cariDosenLamaDenganNama($data);
            }

            if ($dosenExact && $dosen && ! $dosenExact->is($dosen)) {
                throw new DomainException('Identifier API terhubung ke dosen lokal lain.');
            }

            $dosen ??= $dosenExact;

            if ($dosen && $data['kd_dosen'] && Dosen::withTrashed()->where('kd_dosen', $data['kd_dosen'])->whereKeyNot($dosen->getKey())->exists()) {
                throw new DomainException('Kode dosen sudah digunakan dosen lokal lain.');
            }

            if ($dosen && $data['nip'] && Dosen::withTrashed()->where('nip', $data['nip'])->whereKeyNot($dosen->getKey())->exists()) {
                throw new DomainException('NIP sudah digunakan dosen lokal lain.');
            }

            if ($dosen && $data['nidn'] && Dosen::withTrashed()->where('nidn', $data['nidn'])->whereKeyNot($dosen->getKey())->exists()) {
                throw new DomainException('NIDN sudah digunakan dosen lokal lain.');
            }

            if ($dosen) {
                $data['nip'] ??= $dosen->nip;
                $data['nidn'] ??= $dosen->nidn;
            }

            $payload = $data;
            unset($payload['kode_prodi']);
            $payload['status_sync'] = Dosen::STATUS_SYNC_SYNCED;
            $payload['synced_at'] = now();
            $dibuat = $dosen === null;
            $dipulihkan = $dosen?->trashed() ?? false;
            $user = $dosen?->user_id
                ? User::query()->whereKey($dosen->user_id)->lockForUpdate()->first()
                : null;
            $emailApi = $payload['email'];
            $akunDibuat = $user === null;
            $username = $this->usernameAkun($payload['nip']);

            if (User::query()->where('username', $username)->when($user, fn ($query) => $query->whereKeyNot($user->id))->exists()) {
                throw new DomainException('NIP sudah digunakan sebagai username akun lain.');
            }

            if ($akunDibuat) {
                $emailAkun = $this->emailAkunBaru($emailApi, $username);

                if (User::query()->where('email', $emailAkun)->exists()) {
                    throw new DomainException('Email dosen sudah digunakan akun lain.');
                }

                $payload['email'] = $emailAkun;
            } else {
                $payload['email'] = $this->emailEfektif($dosen?->email, $user->email, $emailApi);

                if ($emailApi !== null && User::query()->where('email', $emailApi)->whereKeyNot($user->id)->exists()) {
                    throw new DomainException('Email API sudah digunakan akun lain.');
                }
            }

            if ($dosen?->status === 'aktif') {
                $payload['status'] = 'aktif';
            }

            $berubah = ($dosen && $this->berubah($dosen, $payload))
                || $akunDibuat
                || ($user !== null && ($user->name !== $payload['nama'] || $user->username !== $username || ($emailApi !== null && $user->email !== $emailApi)));

            if ($akunDibuat) {
                $user = User::query()->create([
                    'name' => $payload['nama'],
                    'username' => $username,
                    'email' => $payload['email'],
                    'password' => Hash::make($username),
                ]);
            } else {
                $user->update([
                    'name' => $payload['nama'],
                    'username' => $username,
                    ...($emailApi !== null ? ['email' => $emailApi] : []),
                ]);
            }

            $user->assignRole('dosen');
            $payload['user_id'] = $user->id;

            if ($dosen) {
                $dosen->fill($payload);

                if ($dipulihkan) {
                    $dosen->restore();
                }

                $dosen->save();
            } else {
                Dosen::query()->create($payload);
            }

            return $dibuat ? 'dibuat' : ($dipulihkan ? 'dipulihkan' : ($berubah ? 'diubah' : 'tetap'));
        });
    }

    /** @param array<string, mixed> $payload */
    private function berubah(Dosen $dosen, array $payload): bool
    {
        foreach (['prodi_id', 'kd_dosen', 'nidn', 'nip', 'nama', 'email', 'no_hp', 'gelar_depan', 'gelar_belakang', 'bidang_keahlian', 'status'] as $field) {
            if ((string) $dosen->{$field} !== (string) $payload[$field]) {
                return true;
            }
        }

        return false;
    }

    private function mapStatus(mixed $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            '', 'A', 'AKTIF', '1' => 'aktif',
            'N', 'NONAKTIF', 'NON-AKTIF', '0' => 'nonaktif',
            default => throw new DomainException('Status dosen API tidak dikenali.'),
        };
    }

    private function emailEfektif(?string $emailLokal, ?string $emailUser, ?string $emailApi): ?string
    {
        return $emailApi ?? $emailUser ?? $emailLokal;
    }

    private function usernameAkun(?string $nip): string
    {
        $username = mb_strtolower(trim((string) $nip));

        if ($username === '' || mb_strlen($username) > 255) {
            throw new DomainException('NIP wajib tersedia dan valid untuk akun dosen.');
        }

        return $username;
    }

    private function emailAkunBaru(?string $emailApi, string $username): string
    {
        $email = $emailApi ?? $username.'@example.com';

        if (strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Email dosen tidak valid untuk membuat akun.');
        }

        return $email;
    }

    /** @return array{?string, ?string} */
    private function gelarDariNamaFormat(string $nama, mixed $namaFormat): array
    {
        $namaFormat = trim((string) $namaFormat);
        $posisiNama = $namaFormat === '' ? false : mb_strpos($namaFormat, $nama);

        if ($posisiNama === false) {
            return [null, null];
        }

        $gelarDepan = $this->nullableString(mb_substr($namaFormat, 0, $posisiNama));
        $setelahNama = mb_substr($namaFormat, $posisiNama + mb_strlen($nama));
        $gelarBelakang = $this->nullableString(ltrim($setelahNama, " \t\n\r\0\x0B,"));

        return [$gelarDepan, $gelarBelakang];
    }

    private function identifier(?string $kdDosen, ?string $nip, ?string $nidn): string
    {
        $identifier = trim((string) ($kdDosen ?: $nip ?: $nidn));

        if ($identifier === '' || strlen($identifier) > 255) {
            throw new DomainException('Kode dosen, NIP, atau NIDN wajib tersedia dan valid.');
        }

        return $identifier;
    }

    /** @param array<string, mixed> $data @return list<string> */
    private function identifiers(array $data): array
    {
        return array_values(array_unique(array_filter([
            $data['kd_dosen'] ?? null,
            $data['nip'] ?? null,
            $data['nidn'] ?? null,
        ])));
    }

    /** @param array<string, mixed> $data */
    private function cariDosenLamaDenganNama(array $data): ?Dosen
    {
        $namaApi = $this->kunciNama($data['nama']);
        $candidates = Dosen::withTrashed()
            ->whereNull('kd_dosen')
            ->where('prodi_id', $data['prodi_id'])
            ->lockForUpdate()
            ->get();
        $exact = $candidates->filter(function (Dosen $dosen) use ($namaApi): bool {
            $alias = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $dosen->nip));

            return $this->kunciNama($dosen->nama) === $namaApi || ($alias !== '' && $alias === $namaApi);
        });

        if ($exact->count() > 1) {
            throw new DomainException('Nama dosen cocok dengan lebih dari satu data lokal.');
        }

        if ($exact->count() === 1) {
            return $exact->first();
        }

        $mirip = $candidates->filter(function (Dosen $dosen) use ($namaApi): bool {
            $namaLokal = $this->kunciNama($dosen->nama);

            return min(strlen($namaApi), strlen($namaLokal)) >= 5
                && (str_starts_with($namaApi, $namaLokal)
                    || str_starts_with($namaLokal, $namaApi)
                    || levenshtein($namaApi, $namaLokal) <= 1);
        });

        if ($mirip->count() > 1) {
            throw new DomainException('Nama dosen mirip dengan lebih dari satu data lokal.');
        }

        return $mirip->first();
    }

    private function kunciNama(string $nama): string
    {
        $nama = explode(',', strtolower($nama), 2)[0];
        $nama = preg_replace('/^(?:(?:prof(?:esor)?|dr|dokter)\.?\s+)+/i', '', trim($nama));

        return preg_replace('/[^a-z0-9]+/', '', $nama) ?? '';
    }

    /** @param array<string, mixed> $item @return list<string> */
    private function identifierItem(array $item): array
    {
        return array_values(array_filter([
            $this->nullableString($item['kd_dosen'] ?? $item['kode_dosen'] ?? null),
            $this->nullableString($item['nip'] ?? $item['nip_dosen'] ?? null),
            $this->nullableString($item['nidn'] ?? null),
        ]));
    }

    /** @return array<string, int> */
    private function prodiLokal(): array
    {
        return Prodi::query()->pluck('id_prodi', 'kode')->map(fn ($id) => (int) $id)->all();
    }

    /** @param array<string, mixed> $data */
    private function token(array $data): string
    {
        $token = bin2hex(random_bytes(24));
        Cache::put($this->tokenKey($token), [
            'identifiers' => $this->identifiers($data),
        ], now()->addMinutes(10));

        return $token;
    }

    private function tokenKey(string $token): string
    {
        return 'akademik:kandidat-dosen:'.$token;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
