<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Contracts;

/**
 * Minimal HTTP client contract used by the OAuth providers.
 *
 * Keeping this as an interface lets applications swap in Guzzle, the
 * Kodhe HTTP component, or a test double without touching the provider
 * code. The default implementation (CurlClient) needs no dependencies
 * beyond ext-curl (with a stream-based fallback).
 */
interface HttpClientInterface
{
    /**
     * Perform an HTTP request and return the decoded response.
     *
     * @param  string       $method  GET, POST, PUT, DELETE...
     * @param  string       $url     Absolute URL.
     * @param  array        $options Supported keys:
     *                               - headers: array<string,string>
     *                               - form:    array (application/x-www-form-urlencoded body)
     *                               - json:    array (application/json body)
     *                               - token:   string (Authorization: Bearer <token>)
     *                               - timeout: int seconds (default 30)
     * @return array{text: string, status: int, json: array|null}
     */
    public function request(string $method, string $url, array $options = []): array;

    public function get(string $url, array $options = []): array;

    public function post(string $url, array $options = []): array;
}
