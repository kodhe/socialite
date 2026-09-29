<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Http;

/**
 * Tiny abstraction over the incoming HTTP request so providers can read
 * query parameters (code, state, error...) and build absolute redirect
 * URIs without depending on Symfony/Illuminate request objects.
 */
class Request
{
    /**
     * @param  array  $query   Typically $_GET.
     * @param  string|null $baseUrl  Site base URL used to turn relative
     *                               redirect paths into absolute URIs.
     */
    public function __construct(protected array $query = [], protected ?string $baseUrl = null)
    {
    }

    /**
     * Build a request from PHP superglobals.
     */
    public static function capture(?string $baseUrl = null): static
    {
        return new static($_GET ?? [], $baseUrl ?? self::detectBaseUrl());
    }

    /**
     * Derive "https://example.com" (or with port) from $_SERVER.
     */
    public static function detectBaseUrl(): ?string
    {
        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;

        if (! $host) {
            return null;
        }

        // Only trust well-formed hosts (guards against header injection).
        if (! preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
            return null;
        }

        return ($https ? 'https' : 'http') . '://' . $host;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function has(string $key): bool
    {
        return isset($this->query[$key]);
    }

    public function all(): array
    {
        return $this->query;
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * Make a URI absolute using the captured base URL when needed.
     */
    public function absoluteUrl(string $uri): string
    {
        if (preg_match('#^https?://#i', $uri)) {
            return $uri;
        }

        $base = $this->baseUrl ?? '';

        return rtrim($base, '/') . '/' . ltrim($uri, '/');
    }
}
