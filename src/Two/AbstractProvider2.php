<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Two;

use Kodhe\Framework\Socialite\AbstractProvider;
use Kodhe\Framework\Socialite\SocialiteException;
use Kodhe\Framework\Socialite\SocialiteUser;

/**
 * Base class for OAuth 2.0 providers (authorization-code grant).
 *
 * Implements the standard flow directly against each provider's HTTP
 * endpoints — no third-party OAuth package involved:
 *
 *   1. redirect()          -> send user to getAuthUrl() with state token
 *   2. getAccessTokenByCode() -> POST code => token endpoint
 *   3. getUserFromToken()  -> GET user-info endpoint with Bearer token
 */
abstract class AbstractProvider2 extends AbstractProvider
{
    /**
     * The full provider authorization URL including query string.
     */
    public function authorizationUrl(): string
    {
        return $this->buildAuthQuery($this->authorizationParams());
    }

    /**
     * Hook for providers that need extra auth-URL parameters
     * (Google: access_type/prompt, Facebook: display, ...).
     */
    protected function authorizationParams(): array
    {
        return [];
    }

    /**
     * Exchange the "code" query parameter for an access token.
     *
     * @return array{access_token: string, expires_in?: int, refresh_token?: string, ...}
     */
    public function getAccessTokenByCode(): array
    {
        $this->ensureNoProviderError();
        $this->validateState();

        $code = $this->request->input('code');

        if (! $code) {
            throw new SocialiteException(
                "No authorization [code] found on the callback for provider [{$this->name}]."
            );
        }

        $response = $this->http->post($this->getTokenUrl(), [
            'form' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri'  => $this->redirectUrl(),
                'code'          => $code,
            ] + $this->tokenRequestParams(),
        ]);

        return $this->parseTokenResponse($response);
    }

    protected function tokenRequestParams(): array
    {
        return [];
    }

    /**
     * GET a provider API endpoint with the Bearer access token and
     * return the decoded JSON array.
     */
    protected function httpGetForToken(string $url, string $token, array $options = []): array
    {
        $response = $this->http->get($url, array_merge([
            'token'   => $token,
            'headers' => ['User-Agent' => 'Kodhe-Framework-Socialite/1.0'],
        ], $options));

        if (($response['status'] ?? 200) >= 400 || $response['json'] === null) {
            throw new SocialiteException(
                "Failed to fetch user information from provider [{$this->name}] "
                . '(HTTP ' . ($response['status'] ?? '?') . '). '
                . 'The access token may be invalid or expired.'
            );
        }

        return $response['json'];
    }

    /**
     * Map a raw provider payload onto a SocialiteUser using a set of
     * dot-notation-ish key paths (first match wins).
     *
     * @param  array<string, string[]>  $map  e.g. ['id' => ['id', 'user_id']]
     */
    protected function fillUser(SocialiteUser $user, array $raw, array $map): SocialiteUser
    {
        foreach ($map as $property => $paths) {
            foreach ((array) $paths as $path) {
                $value = $this->arrayGet($raw, $path);

                if ($value !== null && $value !== '') {
                    $setter = 'set' . ucfirst($property);
                    $user->{$setter}(is_scalar($value) ? (string) $value : json_encode($value));

                    break;
                }
            }
        }

        return $user->setExtra($raw);
    }

    /**
     * Read "a.b.c" style paths from nested arrays.
     */
    protected function arrayGet(array $data, string $path): mixed
    {
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
