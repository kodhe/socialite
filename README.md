# Kodhe Socialite — Native OAuth Social Login (without laravel/socialite)

A social login component for the Kodhe framework that **implements the
OAuth 1.0a and OAuth 2.0 protocols on its own** and connects *directly*
to each provider's API (Google, GitHub, Facebook, GitLab, LinkedIn, Twitter).

> No third-party dependencies: **not** `laravel/socialite`,
> **not** Guzzle, **not** league/oauth2-client. Only PHP >= 8.1
> is required (+ `ext-curl` recommended; automatically falls back to PHP streams).

## Built-in providers

| Provider   | Protocol | User-info endpoint                          |
|------------|----------|---------------------------------------------|
| Google     | OAuth 2 / OIDC | `openidconnect.googleapis.com/v1/userinfo` |
| GitHub     | OAuth 2  | `api.github.com/user` (+ `/user/emails`)    |
| Facebook   | OAuth 2  | Graph API `/me` (v18.0, configurable)       |
| GitLab     | OAuth 2  | `/api/v4/user` (supports self-hosted)       |
| LinkedIn   | OAuth 2 / OIDC | `api.linkedin.com/v2/userinfo`         |
| Twitter    | OAuth 1.0a | `account/verify_credentials.json` (HMAC-SHA1 signing RFC 5849) |

Other providers can be registered yourself via `Manager::extend()`.

## Usage

### 1. Configuration — `config/socialite.php`

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

Register the callback URL in each provider's developer console,
e.g. `https://example.com/auth/socialite/callback/google`.

### 2. Route redirect & callback

```php
// GET /auth/socialite/{provider}
Route::get('/auth/socialite/{provider}', function ($provider) {
    return socialite($provider)->redirect();   // helper global
    // or: return \Kodhe\Framework\Socialite\Facade::driver($provider)->redirect();
});

// GET /auth/socialite/callback/{provider}
Route::get('/auth/socialite/callback/{provider}', function ($provider) {
    $user = socialite($provider)->user();      // exchange code -> token -> profile

    // $user->getId(), getName(), getEmail(), getNickname(), getAvatar(),
    // $user->getAccessToken(), getRefreshToken(), getExpiresIn(), getExtra()

    // TODO: find-or-create the local user, then log in
});
```

### 3. Additional features

```php
socialite('google')->scopes(['openid', 'email'])->redirect();
socialite('github')->with(['login' => 'octocat'])->redirect();

// Refresh token (OAuth 2)
$new = socialite('google')->refreshAccessToken($user->getRefreshToken());

// Custom provider (e.g. Strava)
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

### 4. Security

- **CSRF state token**: generated with `random_bytes()`, stored in the session
  (`SessionStateStore`), verified with `hash_equals()` on callback.
  Disable it via `'validate_state' => false` only if necessary.
- HTTPS transport with certificate verification; no secrets are
  ever printed in error messages.
- The HTTP client & state store are swappable via interfaces:
  `Contracts\HttpClientInterface`, `Contracts\StateStoreInterface`
  (`Manager::setHttpClient()` / `setStateStore()`).

## Structure

```
socialite/
├── composer.json              # no external dependencies
├── config/socialite.php
├── src/
│   ├── Manager.php            # provider registry + resolver
│   ├── Facade.php             # static access: Socialite::google()
│   ├── helpers.php            # global socialite()
│   ├── AbstractProvider.php   # common flow, state, token parsing
│   ├── RedirectResponse.php   # result of redirect()
│   ├── SocialiteUser.php      # uniform user object
│   ├── Contracts/             # HttpClientInterface, StateStoreInterface
│   ├── Http/CurlClient.php    # cURL/stream, zero-dependency
│   ├── Http/Request.php       # query params + absolute base URL
│   ├── Session/               # SessionStateStore, ArrayStateStore
│   ├── Two/                   # Google, Github, Facebook, Gitlab, Linkedin
│   └── One/TwitterProvider.php# OAuth 1.0a + HMAC-SHA1 signing
└── tests/                     # suite + standalone runner
```

## Running tests

```bash
php socialite/tests/run.php    # without phpunit/composer
# or via standard PHPUnit through composer test
```
