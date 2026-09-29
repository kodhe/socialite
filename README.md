# Kodhe Socialite — Native OAuth Social Login (tanpa laravel/socialite)

Komponen login sosial untuk framework Kodhe yang **mengimplementasikan
protokol OAuth 1.0a dan OAuth 2.0 secara mandiri** dan terhubung *langsung*
ke API penyedia (Google, GitHub, Facebook, GitLab, LinkedIn, Twitter).

> Tidak ada dependensi pihak ketiga: **bukan** `laravel/socialite`,
> **bukan** Guzzle, **bukan** league/oauth2-client. Cukup PHP >= 8.1
> (+ `ext-curl` disarankan; otomatis fallback ke PHP streams).

## Provider bawaan

| Provider   | Protokol | Endpoint user-info                          |
|------------|----------|---------------------------------------------|
| Google     | OAuth 2 / OIDC | `openidconnect.googleapis.com/v1/userinfo` |
| GitHub     | OAuth 2  | `api.github.com/user` (+ `/user/emails`)    |
| Facebook   | OAuth 2  | Graph API `/me` (v18.0, dapat dikonfigurasi)|
| GitLab     | OAuth 2  | `/api/v4/user` (mendukung self-hosted)      |
| LinkedIn   | OAuth 2 / OIDC | `api.linkedin.com/v2/userinfo`         |
| Twitter    | OAuth 1.0a | `account/verify_credentials.json` (HMAC-SHA1 signing RFC 5849) |

Provider lain bisa didaftarkan sendiri lewat `Manager::extend()`.

## Cara pakai

### 1. Konfigurasi — `config/socialite.php`

```php
'callback_uri' => 'https://example.com/auth/socialite/callback',
'providers' => [
    'google' => [
        'client_id'     => getenv('GOOGLE_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
        'scopes'        => ['openid', 'profile', 'email'],
    ],
    // github, facebook, gitlab, linkedin, twitter ...
],
```

Daftarkan URL callback di konsol developer masing-masing penyedia,
mis. `https://example.com/auth/socialite/callback/google`.

### 2. Route redirect & callback

```php
// GET /auth/socialite/{provider}
Route::get('/auth/socialite/{provider}', function ($provider) {
    return socialite($provider)->redirect();   // helper global
    // atau: return \Kodhe\Framework\Socialite\Facade::driver($provider)->redirect();
});

// GET /auth/socialite/callback/{provider}
Route::get('/auth/socialite/callback/{provider}', function ($provider) {
    $user = socialite($provider)->user();      // tukar code -> token -> profil

    // $user->getId(), getName(), getEmail(), getNickname(), getAvatar(),
    // $user->getAccessToken(), getRefreshToken(), getExpiresIn(), getExtra()

    // TODO: find-or-create user lokal lalu login
});
```

### 3. Fitur tambahan

```php
socialite('google')->scopes(['openid', 'email'])->redirect();
socialite('github')->with(['login' => 'octocat'])->redirect();

// Refresh token (OAuth 2)
$new = socialite('google')->refreshAccessToken($user->getRefreshToken());

// Provider custom (mis. Strava)
socialite()->extend('strava', fn (array $c) => new class('strava', $c) extends \Kodhe\Framework\Socialite\Two\AbstractProvider2 {
    public function getAuthUrl(): string  { return 'https://www.strava.com/oauth/authorize'; }
    public function getTokenUrl(): string { return 'https://www.strava.com/api/v3/oauth/token'; }
    public function getUserFromToken(string $t): \Kodhe\Framework\Socialite\SocialiteUser {
        return $this->fillUser(new \Kodhe\Framework\Socialite\SocialiteUser(),
            $this->httpGetForToken('https://www.strava.com/api/v3/athlete', $t),
            ['id' => ['id'], 'name' => ['firstname'], 'avatar' => ['profile']]);
    }
    public function authorizationUrl(): string { return $this->buildAuthQuery(); }
});
```

### 4. Keamanan

- **State token CSRF**: dibuat dengan `random_bytes()`, disimpan di session
  (`SessionStateStore`), diverifikasi `hash_equals()` saat callback.
  Matikan lewat `'validate_state' => false` hanya bila perlu.
- Transport HTTPS dengan verifikasi sertifikat; tidak ada secret yang
  tercetak di pesan error.
- HTTP client & state store dapat diganti lewat interface:
  `Contracts\HttpClientInterface`, `Contracts\StateStoreInterface`
  (`Manager::setHttpClient()` / `setStateStore()`).

## Struktur

```
socialite/
├── composer.json              # tanpa dependensi eksternal
├── config/socialite.php
├── src/
│   ├── Manager.php            # registry + resolver provider
│   ├── Facade.php             # akses statis: Socialite::google()
│   ├── helpers.php            # global socialite()
│   ├── AbstractProvider.php   # flow umum, state, parse token
│   ├── RedirectResponse.php   # hasil redirect()
│   ├── SocialiteUser.php      # objek user seragam
│   ├── Contracts/             # HttpClientInterface, StateStoreInterface
│   ├── Http/CurlClient.php    # cURL/stream, zero-dependency
│   ├── Http/Request.php       # query params + base URL absolut
│   ├── Session/               # SessionStateStore, ArrayStateStore
│   ├── Two/                   # Google, Github, Facebook, Gitlab, Linkedin
│   └── One/TwitterProvider.php# OAuth 1.0a + signing HMAC-SHA1
└── tests/                     # suite + runner mandiri
```

## Menjalankan test

```bash
php socialite/tests/run.php    # tanpa phpunit/composer
# atau via PHPUnit standar pada composer test
```
