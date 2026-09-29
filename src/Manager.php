<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite;

use Kodhe\Framework\Socialite\Contracts\HttpClientInterface;
use Kodhe\Framework\Socialite\Contracts\StateStoreInterface;
use Kodhe\Framework\Socialite\One\TwitterProvider;
use Kodhe\Framework\Socialite\Two\FacebookProvider;
use Kodhe\Framework\Socialite\Two\GithubProvider;
use Kodhe\Framework\Socialite\Two\GitlabProvider;
use Kodhe\Framework\Socialite\Two\GoogleProvider;
use Kodhe\Framework\Socialite\Two\LinkedinProvider;

/**
 * Main entry point for social login — a self-contained OAuth client.
 *
 * This component implements the OAuth 1.0a / 2.0 flows natively and
 * talks directly to the Google, GitHub, Facebook, GitLab, LinkedIn and
 * Twitter APIs. It does NOT depend on laravel/socialite or any other
 * third-party OAuth package (PHP >= 8.1, ext-curl recommended).
 *
 * Usage:
 *
 *      $socialite = new Manager(require 'config/socialite.php');
 *
 *      // Step 1: send the browser to the provider
 *      return $socialite->driver('google')->redirect();
 *
 *      // Step 2: on your callback route
 *      $user = $socialite->driver('google')->user();
 *      // $user->getEmail(), $user->getName(), $user->getAvatar(), ...
 */
class Manager
{
    /**
     * Built-in providers shipped with this component, keyed by name.
     *
     * @var array<string, class-string<ProviderInterface>>
     */
    public const PROVIDERS = [
        'google'   => GoogleProvider::class,
        'github'   => GithubProvider::class,
        'facebook' => FacebookProvider::class,
        'gitlab'   => GitlabProvider::class,
        'linkedin' => LinkedinProvider::class,
        'twitter'  => TwitterProvider::class,
    ];

    /**
     * Provider configuration array (see config/socialite.php).
     */
    protected array $config;

    /** @var array<string, ProviderInterface> */
    protected array $resolved = [];

    /** @var array<string, callable> */
    protected array $customCreators = [];

    protected ?HttpClientInterface $http = null;

    protected ?StateStoreInterface $stateStore = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * The full configuration array (used by companion components such as
     * the Auth<->Socialite bridge to pick up shared settings).
     */
    public function config(): array
    {
        return $this->config;
    }

    /* ------------------------------------------------------------------
     | Dependency injection (optional)
     * ------------------------------------------------------------------ */

    public function setHttpClient(?HttpClientInterface $http): static
    {
        $this->http = $http;

        return $this;
    }

    public function setStateStore(?StateStoreInterface $store): static
    {
        $this->stateStore = $store;

        return $this;
    }

    /* ------------------------------------------------------------------
     | Resolving providers
     * ------------------------------------------------------------------ */

    /**
     * Get a provider instance by name.
     */
    public function driver(string $name): ProviderInterface
    {
        $name = strtolower($name);

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (isset($this->customCreators[$name])) {
            $config = isset($this->config['providers'][$name])
                ? $this->configFor($name)
                : [];

            return $this->resolved[$name] = ($this->customCreators[$name])($config);
        }

        $class = static::PROVIDERS[$name] ?? null;

        if ($class === null) {
            throw new SocialiteException(
                "Social provider [{$name}] is not supported out of the box. "
                . 'Supported: ' . implode(', ', array_keys(static::PROVIDERS))
                . '. Register custom providers with Manager::extend().'
            );
        }

        $config = $this->configFor($name);

        // Append the per-provider callback path to the base callback URI.
        if (! isset($config['redirect']) || $config['redirect'] === '') {
            $config['redirect'] = $this->callbackUriFor($name);
        }

        return $this->resolved[$name] = new $class($name, $config, $this->http, null, $this->stateStore);
    }

    /**
     * Magic accessor: $socialite->google, $socialite->github, ...
     */
    public function __get(string $name): ProviderInterface
    {
        return $this->driver($name);
    }

    /**
     * Register a custom provider implementation.
     *
     * @param  callable  $callback  Receives the provider config array and
     *                              must return a ProviderInterface instance.
     */
    public function extend(string $name, callable $callback): void
    {
        $this->customCreators[strtolower($name)] = $callback;
    }

    /**
     * Determine whether configuration exists for the given provider.
     */
    public function hasProvider(string $name): bool
    {
        $name = strtolower($name);

        return isset($this->customCreators[$name])
            || (isset(static::PROVIDERS[$name]) && isset($this->config['providers'][$name]));
    }

    /**
     * All configured (enabled + credentialed) provider names.
     *
     * @return string[]
     */
    public function getProviders(): array
    {
        $providers = [];

        foreach ($this->config['providers'] ?? [] as $name => $options) {
            if (($options['enabled'] ?? true) && ! empty($options['client_id'])) {
                $providers[] = (string) $name;
            }
        }

        return array_merge($providers, array_keys($this->customCreators));
    }

    /**
     * Configuration for a single provider (validated).
     */
    public function configFor(string $name): array
    {
        $name = strtolower($name);

        $config = $this->config['providers'][$name] ?? null;

        if ($config === null) {
            throw new SocialiteException(
                "Social provider [{$name}] is not configured. "
                . 'Add it to config/socialite.php under the "providers" key.'
            );
        }

        if (! ($config['enabled'] ?? true)) {
            throw new SocialiteException("Social provider [{$name}] is disabled.");
        }

        foreach (['client_id', 'client_secret'] as $required) {
            if (empty($config[$required])) {
                throw new SocialiteException(
                    "Missing [{$required}] for social provider [{$name}]. "
                    . 'Fill it in config/socialite.php or via environment variables.'
                );
            }
        }

        return $config;
    }

    /**
     * Build the canonical callback URI for a provider, e.g.
     * https://example.com/auth/socialite/callback/google
     */
    public function callbackUriFor(string $name): string
    {
        $base = rtrim((string) ($this->config['callback_uri'] ?? '/auth/socialite/callback'), '/');

        return $base . '/' . strtolower($name);
    }
}
