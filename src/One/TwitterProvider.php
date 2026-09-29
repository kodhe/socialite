<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\One;

use Kodhe\Framework\Socialite\AbstractProvider;
use Kodhe\Framework\Socialite\Contracts\HttpClientInterface;
use Kodhe\Framework\Socialite\Contracts\StateStoreInterface;
use Kodhe\Framework\Socialite\Http\CurlClient;
use Kodhe\Framework\Socialite\Http\Request;
use Kodhe\Framework\Socialite\Session\ArrayStateStore;
use Kodhe\Framework\Socialite\SocialiteException;
use Kodhe\Framework\Socialite\SocialiteUser;

/**
 * OAuth 1.0a provider (Twitter "Sign in with Twitter").
 *
 * Full three-legged flow implemented from scratch with RFC 5849
 * HMAC-SHA1 request signing — no external OAuth library required.
 */
class TwitterProvider extends AbstractProvider
{
    public const AUTH_URL             = 'https://api.twitter.com/oauth/authenticate';
    public const LOGIN_VERIFY_URL     = 'https://api.twitter.com/oauth/authorize';
    public const REQUEST_TOKEN_URL    = 'https://api.twitter.com/oauth/request_token';
    public const ACCESS_TOKEN_URL     = 'https://api.twitter.com/oauth/access_token';
    public const USER_INFO_URL        = 'https://api.twitter.com/1.1/account/verify_credentials.json';

    protected string $authUrl = self::AUTH_URL;

    public function __construct(
        string $name,
        array $config = [],
        ?HttpClientInterface $http = null,
        ?Request $request = null,
        ?StateStoreInterface $stateStore = null,
    ) {
        parent::__construct($name, $config, $http, $request, $stateStore ?? new ArrayStateStore());

        if (! empty($config['user_authorization_url'])) {
            $this->authUrl = (string) $config['user_authorization_url'];
        }
    }

    public function getAuthUrl(): string
    {
        return $this->authUrl;
    }

    public function getTokenUrl(): string
    {
        return self::ACCESS_TOKEN_URL;
    }

    /**
     * Step 1 of the OAuth 1.0a dance: fetch a temporary request token.
     *
     * @return array{oauth_token: string, oauth_token_secret: string}
     */
    public function getAccessTokenByCode(): array
    {
        // Compatibility with the generic ProviderInterface flow.
        throw new SocialiteException(
            'Twitter uses OAuth 1.0a. Call requestToken() + redirect(), '
            . 'then userToResponse()/user() on the callback.'
        );
    }

    /**
     * Obtain (or reuse) an OAuth request token and remember its secret.
     */
    public function getRequestToken(): array
    {
        $payload = $this->stateStore->pull($this->tokenKey());

        if (is_array($payload) && ! empty($payload['oauth_token'])) {
            return $payload;
        }

        $response = $this->signedRequest('POST', self::REQUEST_TOKEN_URL, [
            'oauth_callback' => $this->redirectUrl(),
        ]);

        parse_str($response['text'], $data);

        if (($response['status'] ?? 200) >= 400 || empty($data['oauth_token'])) {
            throw new SocialiteException(
                "Unable to fetch OAuth request token from Twitter (HTTP {$response['status']})."
            );
        }

        $this->stateStore->put($this->tokenKey(), $data);

        return $data;
    }

    /**
     * Build the authorization URL and send the user to Twitter.
     */
    public function authorizationUrl(): string
    {
        $token = $this->getRequestToken();

        return $this->getAuthUrl() . '?oauth_token=' . rawurlencode((string) $token['oauth_token']);
    }

    public function redirect(array $scopes = [])
    {
        return parent::redirect($scopes);
    }

    /**
     * Callback step: verify the PIN/verifier, exchange the request token
     * for an access token, then fetch the profile.
     */
    public function user(): SocialiteUser
    {
        $this->ensureNoProviderError();

        $requestToken = $this->stateStore->pull($this->tokenKey());

        if (! is_array($requestToken) || empty($requestToken['oauth_token'])) {
            throw new SocialiteException(
                'Missing stored Twitter request token. Start the flow with ->redirect().'
            );
        }

        $oauthVerifier = (string) ($this->request->input('oauth_verifier') ?? '');

        $response = $this->signedRequest('POST', self::ACCESS_TOKEN_URL, [
            'oauth_token'         => (string) $requestToken['oauth_token'],
            'oauth_token_secret'  => (string) ($requestToken['oauth_token_secret'] ?? ''),
            'oauth_verifier'      => $oauthVerifier,
        ]);

        parse_str($response['text'], $access);

        if (($response['status'] ?? 200) >= 400 || empty($access['oauth_token'])) {
            throw new SocialiteException(
                "Twitter access-token exchange failed (HTTP {$response['status']})."
            );
        }

        $raw = $this->fetchUserInfo((string) $access['oauth_token'], (string) $access['oauth_token_secret']);

        $user = (new SocialiteUser())
            ->setId(isset($raw['id']) ? (string) $raw['id'] : ($raw['id_str'] ?? null))
            ->setName($raw['name'] ?? null)
            ->setNickname($raw['screen_name'] ?? null)
            ->setEmail($raw['email'] ?? null)
            ->setAvatar($raw['profile_image_url_https'] ?? null)
            ->setAccessToken((string) $access['oauth_token'])
            ->setRefreshToken((string) ($access['oauth_token_secret'] ?? ''))
            ->setExtra($raw);

        return $user;
    }

    public function getUserFromToken(string $token): SocialiteUser
    {
        throw new SocialiteException('OAuth 1.0a requires both token and secret; call user() instead.');
    }

    /**
     * GET verify_credentials.json with OAuth 1.0a signing.
     *
     * @return array<string, mixed>
     */
    protected function fetchUserInfo(string $token, string $secret): array
    {
        $response = $this->signedRequest('GET', self::USER_INFO_URL, [
            'oauth_token'        => $token,
            'oauth_token_secret' => $secret,
            'include_email'      => 'true',
        ]);

        $json = $response['json'];

        if (($response['status'] ?? 200) >= 400 || ! is_array($json)) {
            throw new SocialiteException(
                "Failed to fetch Twitter profile (HTTP {$response['status']}). "
                . 'Ensure "Request email addresses from users" is enabled on the app.'
            );
        }

        return $json;
    }

    protected function tokenKey(): string
    {
        return 'oauth1_' . $this->name . '_request_token';
    }

    /* ------------------------------------------------------------------
     | RFC 5849 signing helpers
     * ------------------------------------------------------------------ */

    /**
     * Perform an HTTP request signed with OAuth 1.0a HMAC-SHA1.
     */
    protected function signedRequest(string $method, string $url, array $extraParams = []): array
    {
        $secret = $this->clientSecret();
        $token  = $extraParams['oauth_token'] ?? '';
        unset($extraParams['oauth_token']);

        $params = array_merge([
            'oauth_consumer_key'     => $this->clientId(),
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => (string) time(),
            'oauth_version'          => '1.0',
        ], $extraParams);

        if ($token !== '') {
            $params['oauth_token'] = $token;
        }

        $params['oauth_signature'] = $this->sign($method, $url, $params, $secret, (string) ($extraParams['oauth_token_secret'] ?? ''));
        unset($params['oauth_token_secret']);

        $header = 'OAuth ' . implode(', ', array_map(
            fn ($k, $v) => rawurlencode($k) . '="' . rawurlencode($v) . '"',
            array_keys($params),
            $params
        ));

        $options = ['headers' => ['Authorization' => $header]];

        if (strtoupper($method) === 'GET') {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query(array_filter(
                $extraParams,
                fn ($k) => ! str_starts_with((string) $k, 'oauth_'),
                ARRAY_FILTER_USE_KEY
            ));

            return $this->http->get($url, $options);
        }

        return $this->http->post($url, $options + ['form' => array_filter(
            $extraParams,
            fn ($k) => ! str_starts_with((string) $k, 'oauth_'),
            ARRAY_FILTER_USE_KEY
        )]);
    }

    /**
     * Compute the RFC 5849 HMAC-SHA1 signature.
     */
    protected function sign(string $method, string $url, array $params, string $consumerSecret, string $tokenSecret): string
    {
        unset($params['oauth_signature']);

        ksort($params);

        $baseString = strtoupper($method) . '&'
            . rawurlencode($url) . '&'
            . rawurlencode(http_build_query($params, '', '&', PHP_QUERY_RFC3986));

        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);

        return base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    }
}
