<?php

namespace App\Support\Akademik;

use App\Models\ApiIntegrasi;
use DomainException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;

class AkademikClient
{
    private const CACHE_KEY = 'akademik:access-token:1';

    private const EXPIRY_BUFFER_SECONDS = 60;

    /**
     * @return array{token_type: string, masked_token: string, expires_at: string, cached_until: string, ttl_seconds: int}
     */
    public function login(ApiIntegrasi $integrasi): array
    {
        if (! $integrasi->is_aktif) {
            throw new DomainException('Integrasi belum diaktifkan. Simpan status aktif sebelum tes login.');
        }

        if (parse_url($integrasi->login_url, PHP_URL_SCHEME) !== 'https') {
            throw new DomainException('URL login wajib memakai HTTPS agar kredensial tidak dikirim melalui koneksi terbuka.');
        }

        try {
            $password = $integrasi->password;
        } catch (DecryptException) {
            throw new DomainException('Password API tidak dapat didekripsi. Input ulang password lalu simpan konfigurasi.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(min(10, $integrasi->timeout))
                ->timeout($integrasi->timeout)
                ->withoutRedirecting()
                ->post($integrasi->login_url, [
                    'username' => $integrasi->username,
                    'password' => $password,
                ]);
        } catch (ConnectionException) {
            throw new DomainException('API tidak dapat dihubungi atau melewati batas waktu request.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new DomainException('Login ditolak. Periksa username dan password API.');
        }

        if ($response->redirect()) {
            throw new DomainException('API mengembalikan redirect. URL login harus menunjuk langsung ke endpoint token.');
        }

        if (! $response->successful()) {
            throw new DomainException("Login API gagal dengan status HTTP {$response->status()}.");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new DomainException('Respons login bukan JSON yang valid.');
        }

        $token = $data['access_token'] ?? null;
        $tokenType = $data['token_type'] ?? null;

        if (! is_string($token) || $token === '' || strlen($token) > 16384) {
            throw new DomainException('Respons login tidak memuat access_token yang valid.');
        }

        if (! is_string($tokenType) || strtolower($tokenType) !== 'bearer') {
            throw new DomainException('Respons login tidak memakai token_type bearer.');
        }

        $expiresAt = $this->jwtExpiration($token);
        $cachedUntil = $expiresAt - self::EXPIRY_BUFFER_SECONDS;
        $ttl = $cachedUntil - time();

        if ($ttl <= 0) {
            throw new DomainException('Token sudah kedaluwarsa atau masa aktifnya kurang dari 60 detik.');
        }

        $stored = Cache::put(self::CACHE_KEY, [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'cached_until' => $cachedUntil,
        ], $ttl);

        if (! $stored) {
            throw new DomainException('Login berhasil, tetapi token gagal disimpan ke cache.');
        }

        return $this->status($token, $expiresAt, $cachedUntil);
    }

    /**
     * @return array{token_type: string, masked_token: string, expires_at: string, cached_until: string, ttl_seconds: int}|null
     */
    public function cachedTokenStatus(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (! is_array($cached)
            || ! is_string($cached['access_token'] ?? null)
            || ! is_int($cached['expires_at'] ?? null)
            || ! is_int($cached['cached_until'] ?? null)
        ) {
            Cache::forget(self::CACHE_KEY);

            return null;
        }

        return $this->status($cached['access_token'], $cached['expires_at'], $cached['cached_until']);
    }

    public function forgetToken(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->getRaw($path, $query, 'application/json');

        if (! $response->successful()) {
            throw new DomainException("Request API gagal dengan status HTTP {$response->status()}.");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new DomainException('Respons API bukan JSON object yang valid.');
        }

        return $data;
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    public function getRaw(string $path, array $query = [], string $accept = '*/*'): Response
    {
        $integrasi = ApiIntegrasi::query()->find(1);

        if (! $integrasi) {
            throw new DomainException('Konfigurasi API belum tersedia.');
        }

        if (! $integrasi->is_aktif) {
            throw new DomainException('Integrasi API belum diaktifkan.');
        }

        if (parse_url($integrasi->base_url, PHP_URL_SCHEME) !== 'https') {
            throw new DomainException('Base URL wajib memakai HTTPS.');
        }

        $url = rtrim($integrasi->base_url, '/').'/'.ltrim($path, '/');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = Http::accept($accept)
                    ->withToken($this->accessToken($integrasi))
                    ->connectTimeout(min(10, $integrasi->timeout))
                    ->timeout($integrasi->timeout)
                    ->withoutRedirecting()
                    ->get($url, $query);
            } catch (ConnectionException) {
                throw new DomainException('API tidak dapat dihubungi atau melewati batas waktu request.');
            }

            if ($response->status() === 401 && $attempt === 0) {
                $this->forgetToken();

                continue;
            }

            if ($response->redirect()) {
                throw new DomainException('API mengembalikan redirect yang tidak diizinkan.');
            }

            if (in_array($response->status(), [401, 403], true)) {
                throw new DomainException('API menolak akses. Periksa kredensial dan hak akses token.');
            }

            return $response;
        }

        throw new DomainException('Token API tidak dapat diperbarui.');
    }

    private function accessToken(ApiIntegrasi $integrasi): string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (! is_array($cached) || ! is_string($cached['access_token'] ?? null) || $cached['access_token'] === '') {
            $this->login($integrasi);
            $cached = Cache::get(self::CACHE_KEY);
        }

        if (! is_array($cached) || ! is_string($cached['access_token'] ?? null) || $cached['access_token'] === '') {
            throw new DomainException('Token API tidak tersedia setelah login.');
        }

        return $cached['access_token'];
    }

    private function jwtExpiration(string $token): int
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new DomainException('access_token bukan JWT yang valid.');
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw new DomainException('Payload JWT tidak dapat dibaca.');
        }

        try {
            $claims = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('Payload JWT bukan JSON yang valid.');
        }

        $expiresAt = is_array($claims) ? filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT) : false;

        if ($expiresAt === false) {
            throw new DomainException('JWT tidak memuat claim exp yang valid.');
        }

        return $expiresAt;
    }

    /**
     * @return array{token_type: string, masked_token: string, expires_at: string, cached_until: string, ttl_seconds: int}
     */
    private function status(string $token, int $expiresAt, int $cachedUntil): array
    {
        return [
            'token_type' => 'Bearer',
            'masked_token' => strlen($token) > 20 ? substr($token, 0, 10).'...'.substr($token, -6) : '***',
            'expires_at' => date('d-m-Y H:i:s', $expiresAt),
            'cached_until' => date('d-m-Y H:i:s', $cachedUntil),
            'ttl_seconds' => max(0, $cachedUntil - time()),
        ];
    }
}
