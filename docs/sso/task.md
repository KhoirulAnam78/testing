# Task: Implementasi SSO Laravel 2 Aplikasi (Passport OAuth2)

## Konteks

Ada 2 aplikasi Laravel terpisah, domain beda total:
- **App1** (`https://app1.com`) = Auth Server / Identity Provider. Satu-satunya tempat user login & simpan password.
- **App2** (`https://app2.io`) = Client App. Tidak punya form login sendiri, login dengan redirect ke App1 (mirip "Login with Google" tapi providernya App1).

Pakai **Laravel Passport** dengan **Authorization Code Grant** di App1. App2 pakai HTTP client biasa (tanpa Socialite) untuk komunikasi OAuth2.

Alur: App2 → redirect ke App1 `/oauth/authorize` → user login (kalau belum) + approve consent → App1 redirect balik ke App2 dengan `code` → App2 tukar `code` jadi `access_token` → App2 ambil data user dari App1 `/api/user` → App2 buat/cari user lokal → login di App2.

Juga ada fitur **logout App2 ikut logout App1** (Opsi 1 — simpel) dan opsional **back-channel logout App1 → App2** (Opsi 2).

---

## TASK 1 — App1: Install & Konfigurasi Passport

- [ ] Jalankan `composer require laravel/passport`
- [ ] Jalankan `php artisan migrate`
- [ ] Jalankan `php artisan passport:install`
- [ ] Tambahkan trait `Laravel\Passport\HasApiTokens` ke `app/Models/User.php`
- [ ] Di `config/auth.php`, set guard `api` driver ke `passport`:
  ```php
  'guards' => [
      'api' => ['driver' => 'passport', 'provider' => 'users'],
  ],
  ```
- [ ] Di `AuthServiceProvider` (Laravel <11) atau `bootstrap/app.php` (Laravel 11+), set token expiry:
  ```php
  use Laravel\Passport\Passport;

  Passport::tokensExpireIn(now()->addDays(15));
  Passport::refreshTokensExpireIn(now()->addDays(30));
  ```

## TASK 2 — App1: Daftarkan Client untuk App2

- [ ] Jalankan `php artisan passport:client`
  - Name: `App2 Client`
  - Redirect URI: `https://app2.io/login/sso/callback`
  - Confidential client (bukan `--public`, karena tukar token server-to-server)
- [ ] Catat `Client ID` dan `Client Secret` yang dihasilkan — akan dipakai di `.env` App2

## TASK 3 — App1: Endpoint ambil data user

- [ ] Tambahkan di `routes/api.php`:
  ```php
  use Illuminate\Http\Request;

  Route::middleware('auth:api')->get('/user', function (Request $request) {
      return $request->user()->only(['id', 'name', 'email']);
  });
  ```

## TASK 4 — App1: Endpoint SSO logout

- [ ] Tambahkan di `routes/web.php`:
  ```php
  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\Auth;

  Route::get('/sso/logout', function (Request $request) {
      $redirect = $request->query('redirect', '/');

      $allowedHosts = ['app2.io']; // tambahkan host client lain di sini kalau ada
      abort_unless(in_array(parse_url($redirect, PHP_URL_HOST), $allowedHosts), 400);

      if (Auth::check()) {
          Auth::user()->tokens()->each->revoke();
      }
      Auth::logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();

      return redirect($redirect);
  });
  ```

## TASK 5 — App1 (Opsional): Back-channel logout

- [ ] Tambahkan `SSO_LOGOUT_SECRET` ke `.env` App1 (string random panjang, HARUS sama persis dengan App2)
- [ ] Panggil endpoint App2 saat user logout dari App1 (tambahkan ke logic logout App1 yang sudah ada):
  ```php
  use Illuminate\Support\Facades\Http;

  $payload = json_encode(['sso_id' => $user->id]);
  Http::withHeaders([
      'X-Signature' => hash_hmac('sha256', $payload, config('services.sso.logout_secret')),
  ])->withBody($payload, 'application/json')
    ->post('https://app2.io/sso/backchannel-logout');
  ```
- [ ] Tambahkan `'logout_secret' => env('SSO_LOGOUT_SECRET')` ke `config/services.php` key `sso`

---

## TASK 6 — App2: Konfigurasi

- [ ] Tambahkan ke `.env`:
  ```env
  SSO_CLIENT_ID=<diisi dari TASK 2>
  SSO_CLIENT_SECRET=<diisi dari TASK 2>
  SSO_REDIRECT_URI=https://app2.io/login/sso/callback
  SSO_AUTH_SERVER=https://app1.com
  SSO_LOGOUT_SECRET=<sama persis dengan App1, hanya jika TASK 5 dikerjakan>
  ```
- [ ] Tambahkan ke `config/services.php`:
  ```php
  'sso' => [
      'client_id'     => env('SSO_CLIENT_ID'),
      'client_secret' => env('SSO_CLIENT_SECRET'),
      'redirect'      => env('SSO_REDIRECT_URI'),
      'base_url'      => env('SSO_AUTH_SERVER'),
      'logout_secret' => env('SSO_LOGOUT_SECRET'),
  ],
  ```

## TASK 7 — App2: Migration kolom `sso_id`

- [ ] Buat migration:
  ```php
  Schema::table('users', function (Blueprint $table) {
      $table->string('sso_id')->nullable()->after('email');
  });
  ```
- [ ] Jalankan `php artisan migrate`

## TASK 8 — App2: Routes

- [ ] Tambahkan di `routes/web.php`:
  ```php
  use App\Http\Controllers\SsoController;

  Route::get('/login/sso', [SsoController::class, 'redirect'])->name('sso.redirect');
  Route::get('/login/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');
  Route::post('/logout', [SsoController::class, 'logout'])->name('logout');
  ```

## TASK 9 — App2: `SsoController`

- [ ] Buat `app/Http/Controllers/SsoController.php`:
  ```php
  namespace App\Http\Controllers;

  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\Http;
  use Illuminate\Support\Facades\Auth;
  use Illuminate\Support\Str;
  use App\Models\User;

  class SsoController extends Controller
  {
      public function redirect(Request $request)
      {
          $state = Str::random(40);
          $request->session()->put('sso_state', $state);

          $query = http_build_query([
              'client_id'     => config('services.sso.client_id'),
              'redirect_uri'  => config('services.sso.redirect'),
              'response_type' => 'code',
              'scope'         => '',
              'state'         => $state,
          ]);

          return redirect(config('services.sso.base_url') . '/oauth/authorize?' . $query);
      }

      public function callback(Request $request)
      {
          if ($request->state !== $request->session()->pull('sso_state')) {
              abort(403, 'Invalid state');
          }

          if (!$request->has('code')) {
              abort(400, 'Authorization code tidak ditemukan');
          }

          $response = Http::asForm()->post(config('services.sso.base_url') . '/oauth/token', [
              'grant_type'    => 'authorization_code',
              'client_id'     => config('services.sso.client_id'),
              'client_secret' => config('services.sso.client_secret'),
              'redirect_uri'  => config('services.sso.redirect'),
              'code'          => $request->code,
          ]);

          if ($response->failed()) {
              abort(400, 'Gagal menukar token: ' . $response->body());
          }

          $accessToken = $response->json('access_token');

          $userResponse = Http::withToken($accessToken)
              ->get(config('services.sso.base_url') . '/api/user');

          if ($userResponse->failed()) {
              abort(400, 'Gagal mengambil data user');
          }

          $ssoUser = $userResponse->json();

          $user = User::updateOrCreate(
              ['email' => $ssoUser['email']],
              [
                  'name'     => $ssoUser['name'],
                  'password' => bcrypt(Str::random(32)),
                  'sso_id'   => $ssoUser['id'],
              ]
          );

          Auth::login($user, remember: true);

          return redirect()->intended('/dashboard');
      }

      public function logout(Request $request)
      {
          Auth::logout();
          $request->session()->invalidate();
          $request->session()->regenerateToken();

          return redirect(
              config('services.sso.base_url') . '/sso/logout?redirect=' . urlencode(url('/'))
          );
      }
  }
  ```

## TASK 10 (Opsional) — App2: Back-channel logout receiver

Hanya kerjakan kalau TASK 5 juga dikerjakan. Butuh `SESSION_DRIVER=database`.

- [ ] Pastikan `.env` App2: `SESSION_DRIVER=database`, lalu `php artisan session:table && php artisan migrate`
- [ ] Tambahkan route di `routes/web.php`:
  ```php
  Route::post('/sso/backchannel-logout', [SsoController::class, 'backchannelLogout']);
  ```
- [ ] Tambahkan method di `SsoController`:
  ```php
  use Illuminate\Support\Facades\DB;

  public function backchannelLogout(Request $request)
  {
      $expected = hash_hmac('sha256', $request->getContent(), config('services.sso.logout_secret'));
      abort_unless(hash_equals($expected, (string) $request->header('X-Signature')), 403);

      $userIds = User::where('sso_id', $request->input('sso_id'))->pluck('id');
      DB::table('sessions')->whereIn('user_id', $userIds)->delete();

      return response()->noContent();
  }
  ```
- [ ] Tambahkan `sso/backchannel-logout` ke pengecualian CSRF (`VerifyCsrfToken::class` middleware `$except`, atau di Laravel 11+ lewat `bootstrap/app.php` → `->withMiddleware(fn ($m) => $m->validateCsrfTokens(except: ['sso/backchannel-logout']))`)

## TASK 11 — App2: UI

- [ ] Tambahkan tombol login di view yang relevan:
  ```blade
  <a href="{{ route('sso.redirect') }}">Login dengan App1</a>
  ```
- [ ] Pastikan tombol/form logout memakai route `logout` (POST) yang sudah didaftarkan di TASK 8, bukan logout bawaan Laravel breeze/jetstream default (ganti action-nya ke route `logout` custom ini)

---

## TASK 12 — Testing End-to-End

- [ ] Buka App2, klik "Login dengan App1" → harus redirect ke `app1.com/oauth/authorize`
- [ ] Login di App1 dengan akun yang ada → muncul halaman consent/authorize Passport → klik Approve
- [ ] Harus redirect balik ke `app2.io/login/sso/callback?code=...` lalu otomatis ke `/dashboard` App2 dalam keadaan login
- [ ] Cek tabel `users` di App2 — harus ada row baru dengan `sso_id` terisi sesuai `id` user di App1
- [ ] Logout dari App2 → sesi App2 hilang, DAN kalau buka App1 harus dalam keadaan logout juga (TASK 4)
- [ ] (Kalau TASK 5 & 10 dikerjakan) Logout dari App1 langsung → sesi App2 juga mati tanpa perlu klik logout di App2

---

## Catatan Keamanan (Jangan Dilewati)

- Redirect URI di `passport:client` (TASK 2) harus **persis sama** (termasuk trailing slash) dengan `SSO_REDIRECT_URI` App2
- Semua komunikasi wajib HTTPS di production
- `state` parameter wajib divalidasi (sudah ada di TASK 9) — mencegah CSRF pada flow OAuth
- `$allowedHosts` di TASK 4 wajib di-whitelist, jangan redirect ke URL sembarang (cegah open redirect)
- `SSO_LOGOUT_SECRET` (TASK 5 & 10) harus sama persis di kedua `.env` dan tidak boleh bocor ke publik
