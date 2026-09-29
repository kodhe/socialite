<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite;

use Kodhe\Framework\Socialite\Contracts\HttpClientInterface;
use Kodhe\Framework\Socialite\Contracts\StateStoreInterface;
use Kodhe\Framework\Socialite\Http\CurlClient;
use Kodhe\Framework\Socialite\Http\Request;
use Kodhe\Framework\Socialite\Session\SessionStateStore;

/**
 * Shared plumbing for every provider: configuration, HTTP client,
 * request object, state store and the redirect()/user() flow skeleton.
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected HttpClientInterface $http;
    protected Request $request;
    protected StateStoreInterface $stateStore;

    /** Extra query parameters merged into the authorization URL. */
    protected array $parameters = [];

    /** Scopes requested during authorization. */
    protected array $scopes = [];

    /** Token payload from the most recent token exchange (see accessTokenPayload()). */
    protected ?array $lastToken = null;

    /** Epoch seconds of that exchange, so expires_in can be turned into an absolute time. */
    protected ?int $tokenFetchedAt = null;

    /** Extra key/values merged into the stored state payload (withStateData()). */
    protected array $stateData = [];

    /** The state payload validated during the CURRENT callback request. */
    protected ?array $lastState = null;

    public function __construct(
        protected string $name,
        protected array $config = [],
        ?HttpClientInterface $http = null,
        ?Request $request = null,
        ?StateStoreInterface $stateStore = null,
    ) {
        $this->http       = $http ?? new CurlClient();
        $this->request    = $request ?? Request::capture();
        $this->stateStore = $stateStore ?? new SessionStateStore();

        $this->scopes     = (array) ($config['scopes'] ?? $this->scopes);
        $this->parameters = (array) ($config['parameters'] ?? []);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /* ------------------------------------------------------------------
     | Endpoint / credential accessors (overridden by concrete providers)
     * ------------------------------------------------------------------ */

    abstract public function getAuthUrl(): string;

    abstract public function getTokenUrl(): string;

    abstract public function getUserFromToken(string $token): SocialiteUser;

    /* ------------------------------------------------------------------
     | Fluent helpers
     * ------------------------------------------------------------------ */

    /**
     * Add extra parameters to the authorization query string
     * (e.g. prompt, access_type for Google).
     */
    public function with(array $parameters): static
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * Override the scopes for this provider instance.
     *
     * @param  string[]  $scopes
     */
    public function scopes(array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function addScope(string $scope): static
    {
        if (! in_array($scope, $this->scopes, true)) {
            $this->scopes[] = $scope;
        }

        return $this;
    }

    /**
     * Replace the callback URI for this instance only — handy when the
     * same provider is used from several apps/domains or behind a proxy.
     */
    public function setRedirectUrl(string $uri): static
    {
        $this->config['redirect'] = $uri;

        return $this;
    }

    /**
     * Attach extra data to the CSRF state token (e.g. a "link account"
     * nonce minted by the Auth bridge). The payload travels through the
     * state store — never the URL — and is readable on the callback via
     * stateData() after user()/login() has validated it.
     */
    public function withStateData(array $data): static
    {
        $this->stateData = array_merge($this->stateData, $data);

        // Also expose it on the current instance in case no round-trip
        // happens (single-request usage / tests).
        return $this;
    }

    /**
     * The state payload stored for the CURRENT callback request:
     * ['state' => token, 'created_at' => int, ...custom keys].
     * Available right after user(); null before that.
     *
     * @return array<string,mixed>|null
     */
    public function stateData(): ?array
    {
        return $this->lastState;
    }

    /* ------------------------------------------------------------------
     | Token persistence helpers (used by the Auth bridge)
     | ------------------------------------------------------------------ */

    /**
     * The OAuth token payload belonging to the CURRENT callback request.
     *
     * Returns null before user() has run, and the very same array once it
     * has — the access/refresh tokens are cached so controllers can store
     * them next to the local account without re-exchanging the code.
     *
     * @return array<string,mixed>|null
     */
    public function accessTokenPayload(): ?array
    {
        return $this->lastToken;
    }

    /**
     * Epoch seconds at which the cached access token expires
     * (expires_in is relative to the token response), or null when the
     * provider does not send an expiry / no token was fetched yet.
     */
    public function tokenExpiresAt(): ?int
    {
        $payload = $this->accessTokenPayload();

        if ($payload === null || ! isset($payload['expires_in'])) {
            return null;
        }

        return ($this->tokenFetchedAt ?? time()) + (int) $payload['expires_in'];
    }

    /**
     * True when we know the cached access token has expired.
     */
    public function tokenHasExpired(): bool
    {
        $expiresAt = $this->tokenExpiresAt();

        // 60s safety margin so requests never race the actual expiry.
        return $expiresAt !== null && time() >= ($expiresAt - 60);
    }

    /* ------------------------------------------------------------------
     | The OAuth flow
     * ------------------------------------------------------------------ */

    /**
     * Build the full provider authorization URL (without redirecting).
     */
    abstract public function authorizationUrl(): string;

    /**
     * Send the browser to the provider's consent page.
     *
     * Returns a RedirectResponse that emits the Location header when
     * ->send() is called, so controllers can simply
     * `return socialite('google')->redirect();`.
     */
    public function redirect(array $scopes = [])
    {
        if ($scopes !== []) {
            $this->scopes = $scopes;
        }

        return new RedirectResponse($this->authorizationUrl());
    }

    /**
     * Handle the callback: exchange the authorization code for tokens
     * and fetch the user.
     */
    public function user(): SocialiteUser
    {
        $token = $this->getAccessTokenByCode();

        $user = $this->getUserFromToken($token['access_token']);

        $user->setAccessToken($token['access_token'])
            ->setRefreshToken($token['refresh_token'] ?? null);

        if (isset($token['expires_in'])) {
            $user->setExpiresIn((int) $token['expires_in']);
        }

        // Stamp the provider name so downstream consumers (e.g. the
        // Auth<->Socialite bridge) always know where a profile came from.
        $extra       = $user->getExtra();
        $extra['__provider'] = $this->name;
        $user->setExtra($extra);

        return $user;
    }

    /**
     * Refresh an access token using a refresh token (OAuth2).
     *
     * @return array{access_token: string, expires_in?: int, refresh_token?: string}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->parseTokenResponse(
            $this->http->post($this->getTokenUrl(), [
                'form' => [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id'     => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                ],
            ])
        );
    }

    /* ------------------------------------------------------------------
     | Config helpers
     * ------------------------------------------------------------------ */

    protected function clientId(): string
    {
        return (string) ($this->config['client_id'] ?? '');
    }

    protected function clientSecret(): string
    {
        return (string) ($this->config['client_secret'] ?? '');
    }

    /**
     * The URI the provider must redirect back to. Accepts absolute URLs
     * or paths (made absolute using the current host).
     */
    public function redirectUrl(): string
    {
        $redirect = $this->config['redirect'] ?? '';

        if ($redirect === '' || $redirect === null) {
            throw new SocialiteException(
                "No redirect URI configured for provider [{$this->name}]. "
                . 'Set "callback_uri" or the provider "redirect" key in config/socialite.php.'
            );
        }

        return $this->request->absoluteUrl((string) $redirect);
    }

    protected function scopeSeparator(): string
    {
        return ' ';
    }

    protected function buildAuthQuery(array $extra = []): string
    {
        $query = http_build_query(array_merge([
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $this->redirectUrl(),
            'response_type' => 'code',
            'scope'         => implode($this->scopeSeparator(), $this->scopes),
            'state'         => $this->makeState(),
        ], $this->parameters, $extra), '', '&');

        return $this->getAuthUrl() . (str_contains($this->getAuthUrl(), '?') ? '&' : '?') . $query;
    }

    /* ------------------------------------------------------------------
     | State (CSRF) handling
     * ------------------------------------------------------------------ */

    protected function stateKey(): string
    {
        return 'state_' . $this->name;
    }

    /**
     * Generate (or reuse within this request) a CSRF-proof state token.
     */
    protected function makeState(): string
    {
        $payload = $this->stateStore->pull($this->stateKey());

        if (! is_array($payload) || empty($payload['state'])) {
            // Reuse the state already present on an in-flight authorization
            // URL for this provider instance (same-request redirect()),
            // otherwise mint a fresh token.
            $existing = $this->parameters['state'] ?? null;

            $payload = [
                'state'      => is_string($existing) && $existing !== ''
                    ? $existing
                    : bin2hex(random_bytes(16)),
                'created_at' => time(),
            ] + $this->stateData;
        } else {
            // Callback round-trip: keep any custom keys already stored.
            $payload += $this->stateData;
        }

        // Put it back so validateState() can compare on the callback.
        $this->stateStore->put($this->stateKey(), $payload);
        $this->lastState = $payload;

        return (string) $payload['state'];
    }

    /**
     * Validate the "state" returned by the provider against the stored one.
     */
    protected function validateState(): void
    {
        if (($this->config['validate_state'] ?? true) === false) {
            return;
        }

        $returned = $this->request->input('state');
        $stored   = $this->stateStore->pull($this->stateKey());

        if (! is_array($stored) || ! isset($stored['state'], $returned)) {
            throw InvalidStateException::missing();
        }

        if (! hash_equals((string) $stored['state'], (string) $returned)) {
            throw InvalidStateException::mismatch();
        }

        // Remember the validated payload — stateData() exposes the custom
        // keys (link nonce, intended URL, ...) to the Auth bridge.
        $this->lastState = $stored;
    }

    protected function parseTokenResponse(array $response): array
    {
        $data = $response['json'];

        if ($data === null && ! empty($response['text'])) {
            // Some providers (Facebook) reply url-encoded instead of JSON.
            parse_str($response['text'], $data);
        }

        if (($response['status'] ?? 200) >= 400 || empty($data['access_token'])) {
            $message = $data['error_description'] ?? $data['error']['message']
                ?? $data['error_message'] ?? $data['error']
                ?? 'unknown error (' . substr((string) ($response['text'] ?? ''), 0, 200) . ')';

            throw new SocialiteException(
                "Token exchange failed for provider [{$this->name}]: {$message}"
            );
        }

        // Cache the payload so accessTokenPayload()/tokenExpiresAt() can
        // report it later (the Auth bridge persists these tokens).
        $this->lastToken     = $data;
        $this->tokenFetchedAt = time();

        return $data;
    }

    /**
     * Ensure the provider did not return an error on the callback.
     */
    protected function ensureNoProviderError(): void
    {
        if ($error = $this->request->input('error')) {
            $description = $this->request->input('error_description', $error);

            throw new SocialiteException("Provider [{$this->name}] returned an error: {$description}");
        }
    }
}
