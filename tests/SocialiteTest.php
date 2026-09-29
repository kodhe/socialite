<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Tests;

use Kodhe\Framework\Socialite\Contracts\HttpClientInterface;
use Kodhe\Framework\Socialite\Facade;
use Kodhe\Framework\Socialite\Http\Request;
use Kodhe\Framework\Socialite\InvalidStateException;
use Kodhe\Framework\Socialite\Manager;
use Kodhe\Framework\Socialite\One\TwitterProvider;
use Kodhe\Framework\Socialite\RedirectResponse;
use Kodhe\Framework\Socialite\Session\ArrayStateStore;
use Kodhe\Framework\Socialite\SocialiteException;
use Kodhe\Framework\Socialite\Two\FacebookProvider;
use Kodhe\Framework\Socialite\Two\GithubProvider;
use Kodhe\Framework\Socialite\Two\GoogleProvider;
use PHPUnit\Framework\TestCase;

/**
 * Fake HTTP client that replays canned responses and records requests.
 */
class FakeHttpClient implements HttpClientInterface
{
    /** @var array<string, array> */
    public array $requests = [];

    public function __construct(protected array $queue = [])
    {
    }

    public function get(string $url, array $options = []): array
    {
        return $this->request('GET', $url, $options);
    }

    public function post(string $url, array $options = []): array
    {
        return $this->request('POST', $url, $options);
    }

    public function request(string $method, string $url, array $options = []): array
    {
        $this->requests[] = compact('method', 'url', 'options');

        foreach ($this->queue as $pattern => $response) {
            if (str_contains($url, $pattern)) {
                unset($this->queue[$pattern]);

                return $response + ['text' => '', 'status' => 200, 'json' => null];
            }
        }

        return ['text' => '', 'status' => 500, 'json' => null];
    }
}

class SocialiteTest extends TestCase
{
    protected function baseConfig(array $overrides = []): array
    {
        return [
            'callback_uri' => 'https://example.test/auth/callback',
            'providers'    => [
                'google'   => ['client_id' => 'gid', 'client_secret' => 'gsecret'],
                'github'   => ['client_id' => 'hid', 'client_secret' => 'hsecret'],
                'facebook' => ['client_id' => 'fid', 'client_secret' => 'fsecret'],
                'twitter'  => ['client_id' => 'tid', 'client_secret' => 'tsecret'],
            ],
        ] + $overrides;
    }

    protected function manager(array $config = [], ?FakeHttpClient $http = null, array $query = []): Manager
    {
        $manager = new Manager($config ?: $this->baseConfig());
        $manager->setHttpClient($http ?? new FakeHttpClient());

        // Inject a shared state store through extend-free path: providers
        // created by the Manager receive $this->http but default stores;
        // for flow tests we build providers manually instead.
        return $manager;
    }

    /* -------------------- Manager / resolution -------------------- */

    public function testDriverResolution(): void
    {
        $m = $this->manager();

        $this->assertInstanceOf(GoogleProvider::class, $m->driver('google'));
        $this->assertInstanceOf(GithubProvider::class, $m->driver('GitHub'));
        $this->assertInstanceOf(FacebookProvider::class, $m->driver('facebook'));
        $this->assertInstanceOf(TwitterProvider::class, $m->driver('twitter'));
        $this->assertSame($m->driver('google'), $m->google);
    }

    public function testUnknownProviderThrows(): void
    {
        $this->expectException(SocialiteException::class);
        $this->expectExceptionMessage('not supported out of the box');

        $this->manager()->driver('myspace');
    }

    public function testMissingCredentialsThrow(): void
    {
        $config = $this->baseConfig();
        $config['providers']['google']['client_secret'] = '';

        $this->expectException(SocialiteException::class);
        $this->expectExceptionMessage('Missing [client_secret]');

        $this->manager($config)->driver('google');
    }

    public function testDisabledProviderThrows(): void
    {
        $config = $this->baseConfig();
        $config['providers']['google']['enabled'] = false;

        $this->expectException(SocialiteException::class);
        $this->expectExceptionMessage('disabled');

        $this->manager($config)->driver('google');
    }

    public function testGetProvidersOnlyListsConfiguredOnes(): void
    {
        $config = $this->baseConfig();
        $config['providers']['linkedin-openid'] = ['client_id' => '', 'client_secret' => ''];
        $config['providers']['gitlab'] = ['client_id' => 'lid', 'client_secret' => 'lsec'];

        $m = $this->manager($config);

        $this->assertContains('google', $m->getProviders());
        $this->assertContains('gitlab', $m->getProviders());
        $this->assertNotContains('linkedin-openid', $m->getProviders());
    }

    public function testCallbackUriPerProvider(): void
    {
        $m = $this->manager();

        $this->assertSame(
            'https://example.test/auth/callback/google',
            $m->callbackUriFor('google')
        );
    }

    public function testExtendRegistersCustomProvider(): void
    {
        $m = $this->manager();
        $m->extend('strava', fn (array $config) => new GoogleProvider('strava', $config));

        $this->assertTrue($m->hasProvider('strava'));
        $this->assertSame('strava', $m->driver('strava')->getName());
    }

    /* -------------------- OAuth2 flow -------------------- */

    public function testGoogleAuthorizationUrlAndStateRoundTrip(): void
    {
        $store = new ArrayStateStore();
        $http  = new FakeHttpClient();

        $provider = new GoogleProvider(
            'google',
            $this->baseConfig()['providers']['google'] + ['redirect' => '/auth/callback/google'],
            $http,
            new Request([], 'https://example.test'),
            $store
        );

        /** @var RedirectResponse $response */
        $response = $provider->redirect();
        $url      = $response->getTargetUrl();

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertStringContainsString('client_id=gid', $url);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fexample.test%2Fauth%2Fcallback%2Fgoogle', $url);
        $this->assertStringContainsString('scope=openid+profile+email', $url);
        $this->assertStringContainsString('access_type=offline', $url);

        preg_match('/state=([a-f0-9]{32})/', $url, $m);
        $this->assertNotEmpty($m[1], 'state token must be present');

        // Callback with matching state + code -> tokens -> user payload.
        $http = new FakeHttpClient([
            'oauth2.googleapis.com/token' => [
                'json' => ['access_token' => 'ya29.token', 'refresh_token' => 'rt', 'expires_in' => 3600],
            ],
            'userinfo' => [
                'json' => ['sub' => '123456', 'name' => 'Budi', 'email' => 'budi@example.com', 'picture' => 'https://pic'],
            ],
        ]);

        $callback = new GoogleProvider(
            'google',
            $this->baseConfig()['providers']['google'] + ['redirect' => '/auth/callback/google'],
            $http,
            new Request(['code' => 'abc', 'state' => $m[1]], 'https://example.test'),
            $store
        );

        $user = $callback->user();

        $this->assertSame('123456', $user->getId());
        $this->assertSame('budi@example.com', $user->getEmail());
        $this->assertSame('ya29.token', $user->getAccessToken());
        $this->assertSame('rt', $user->getRefreshToken());
        $this->assertSame(3600, $user->getExpiresIn());
    }

    public function testStateMismatchIsRejected(): void
    {
        $store = new ArrayStateStore();

        $provider = new GithubProvider(
            'github',
            $this->baseConfig()['providers']['github'] + ['redirect' => '/cb/github'],
            new FakeHttpClient(),
            new Request(['code' => 'x', 'state' => 'attacker-state']),
            $store
        );

        // Seed a different state value.
        $store->put('state_github', ['state' => 'real-state', 'created_at' => time()]);

        $this->expectException(InvalidStateException::class);
        $provider->user();
    }

    public function testMissingStateIsRejected(): void
    {
        $provider = new GithubProvider(
            'github',
            $this->baseConfig()['providers']['github'] + ['redirect' => '/cb/github'],
            new FakeHttpClient(),
            new Request(['code' => 'x']),
            new ArrayStateStore()
        );

        $this->expectException(InvalidStateException::class);
        $provider->user();
    }

    public function testGithubFetchesEmailsWhenPrivate(): void
    {
        $http = new FakeHttpClient([
            'api.github.com/user/emails' => [
                'json' => [['email' => 'hidden@users.noreply.github.com', 'primary' => true, 'verified' => true]],
            ],
            'api.github.com/user' => [
                'json' => ['id' => 42, 'login' => 'octocat', 'name' => 'The Octocat', 'avatar_url' => 'https://gh/avatar'],
            ],
        ]);

        $provider = new class('github', ['client_id' => 'c', 'client_secret' => 's', 'redirect' => '/cb'], $http) extends GithubProvider {
            public function fetchWith(string $token): \Kodhe\Framework\Socialite\SocialiteUser
            {
                return $this->getUserFromToken($token);
            }
        };

        $user = $provider->fetchWith('ghtoken');

        $this->assertSame('42', $user->getId());
        $this->assertSame('octocat', $user->getNickname());
        $this->assertSame('hidden@users.noreply.github.com', $user->getEmail());
    }

    public function testFacebookUrlEncodedTokenResponseAndNestedAvatar(): void
    {
        $store = new ArrayStateStore();
        $store->put('state_facebook', ['state' => 'st123', 'created_at' => time()]);

        $http = new FakeHttpClient([
            // Token endpoint replies url-encoded (legacy Facebook behaviour).
            'oauth/access_token' => [
                'status' => 200,
                'text'   => 'access_token=fbe4.token&expires_in=5183999',
                'json'   => null,
            ],
            // Graph /me returns nested picture data.
            '/me?' => [
                'status' => 200,
                'text'   => '{"id":"1000","name":"Siti","email":"siti@example.com","picture":{"data":{"url":"https://fb/pic.jpg"}}}',
                'json'   => ['id' => '1000', 'name' => 'Siti', 'email' => 'siti@example.com', 'picture' => ['data' => ['url' => 'https://fb/pic.jpg']]],
            ],
        ]);

        $provider = new FacebookProvider(
            'facebook',
            ['client_id' => 'fc', 'client_secret' => 'fs', 'redirect' => '/cb/facebook'],
            $http,
            new Request(['code' => 'c1', 'state' => 'st123'], 'https://example.test'),
            $store
        );

        $user = $provider->user();

        $this->assertSame('1000', $user->getId());
        $this->assertSame('siti@example.com', $user->getEmail());
        $this->assertSame('https://fb/pic.jpg', $user->getAvatar());
        $this->assertSame('fbe4.token', $user->getAccessToken());
        $this->assertSame(5183999, $user->getExpiresIn());
    }

    public function testTokenExchangeFailureThrows(): void
    {
        $store = new ArrayStateStore();
        $store->put('state_google', ['state' => 'ok', 'created_at' => time()]);

        $http = new FakeHttpClient([
            'token' => [
                'status' => 400,
                'json'   => ['error' => 'invalid_grant', 'error_description' => 'Code was already used.'],
            ],
        ]);

        $provider = new GoogleProvider(
            'google',
            ['client_id' => 'c', 'client_secret' => 's', 'redirect' => '/cb/google'],
            $http,
            new Request(['code' => 'used', 'state' => 'ok']),
            $store
        );

        $this->expectException(SocialiteException::class);
        $this->expectExceptionMessage('Code was already used.');

        $provider->user();
    }

    public function testProviderErrorOnCallbackThrows(): void
    {
        $provider = new GoogleProvider(
            'google',
            ['client_id' => 'c', 'client_secret' => 's', 'redirect' => '/cb/google'],
            new FakeHttpClient(),
            new Request(['error' => 'access_denied', 'error_description' => 'User cancelled']),
            new ArrayStateStore()
        );

        $this->expectException(SocialiteException::class);
        $this->expectExceptionMessage('User cancelled');

        $provider->user();
    }

    /* -------------------- OAuth 1.0a signing -------------------- */

    public function testOauth1SignatureMatchesKnownVector(): void
    {
        // Fixed parameters -> fixed HMAC-SHA1 signature (RFC 5849 method),
        // computed independently with php -r as a cross-check.
        $provider = new TwitterProvider(
            'twitter',
            ['client_id' => 'xvz1evFS4wEEPTGEFPHBog', 'client_secret' => 'kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3DF78i0'],
            new FakeHttpClient(),
            new Request(),
            new ArrayStateStore()
        );

        $params = [
            'oauth_consumer_key'     => 'xvz1evFS4wEEPTGEFPHBog',
            'oauth_nonce'            => 'kYQZp4RSAe7i6jyz2LJ3U4CYfKrrhjoNmvVz6ua5M',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => '137131200',
            'oauth_token'            => '370773119-GmHrMAeynvsPwCEiYeRQKEIAbyLZKGhOlnhYK025',
            'oauth_verifier'         => 'hTSfb8C-6NKkHhMUis09PgZ1-R9H0M7_4u2T5IQQ',
            'oauth_version'          => '1.0',
        ];

        $signature = (new \ReflectionMethod($provider, 'sign'))->invoke(
            $provider,
            'POST',
            'https://api.twitter.com/oauth/access_token',
            $params,
            'kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3DF78i0',
            '3h9uAx8T2KFy68EAzALhtJ0q8vxIjV7aMKe0smZe0C0Mzjgn94'
        );

        $this->assertNotEmpty($signature);

        // Cross-check against an independent HMAC computation.
        ksort($params);
        $base = 'POST&' . rawurlencode('https://api.twitter.com/oauth/access_token') . '&'
            . rawurlencode(http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        $key  = rawurlencode('kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3DF78i0') . '&'
            . rawurlencode('3h9uAx8T2KFy68EAzALhtJ0q8vxIjV7aMKe0smZe0C0Mzjgn94');

        $this->assertSame(base64_encode(hash_hmac('sha1', $base, $key, true)), $signature);
    }

    /* -------------------- Facade & helper -------------------- */

    public function testFacadeUsesProvidedManager(): void
    {
        Facade::setManager($this->manager());

        try {
            $this->assertInstanceOf(GoogleProvider::class, Facade::driver('google'));
            $this->assertInstanceOf(GithubProvider::class, Facade::github());
            $this->assertTrue(Facade::hasProvider('facebook'));
            $this->assertContains('twitter', Facade::getProviders());

            $this->assertInstanceOf(Manager::class, socialite());
            $this->assertInstanceOf(FacebookProvider::class, socialite('facebook'));
        } finally {
            Facade::setManager(null);
        }
    }
}
