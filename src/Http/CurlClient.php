<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Http;

use Kodhe\Framework\Socialite\Contracts\HttpClientInterface;
use Kodhe\Framework\Socialite\SocialiteException;

/**
 * Dependency-free HTTP client built on ext-curl, with an automatic
 * fallback to PHP streams (file_get_contents) when curl is missing.
 *
 * This is what lets the Kodhe Socialite component talk directly to
 * Google / GitHub / Facebook / ... token and user-info endpoints without
 * pulling in Guzzle or any other package.
 */
class CurlClient implements HttpClientInterface
{
    public function __construct(protected int $timeout = 30)
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
        $method = strtoupper($method);
        $headers = $this->normalizeHeaders($options['headers'] ?? [], $options);

        $body = null;

        if (isset($options['json'])) {
            $body = json_encode($options['json'], JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        } elseif (isset($options['form'])) {
            $body = http_build_query($options['form'], '', '&');
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if (function_exists('curl_init')) {
            return $this->doRequestWithCurl($method, $url, $headers, $body, $options);
        }

        return $this->doRequestWithStreams($method, $url, $headers, $body, $options);
    }

    /**
     * @param  string[]  $headers
     */
    protected function doRequestWithCurl(string $method, string $url, array $headers, ?string $body, array $options): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => $options['timeout'] ?? $this->timeout,
            CURLOPT_TIMEOUT        => $options['timeout'] ?? $this->timeout,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Kodhe-Framework-Socialite/1.0',
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $text = curl_exec($ch);

        if ($text === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new SocialiteException("HTTP request to [{$url}] failed: {$error}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $this->buildResult((string) $text, $status);
    }

    /**
     * @param  string[]  $headers
     */
    protected function doRequestWithStreams(string $method, string $url, array $headers, ?string $body, array $options): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => $options['timeout'] ?? $this->timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $text = @file_get_contents($url, false, $context);

        if ($text === false) {
            throw new SocialiteException("HTTP request to [{$url}] failed.");
        }

        $status = 0;

        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
        }

        return $this->buildResult($text, $status);
    }

    /**
     * @return string[]
     */
    protected function normalizeHeaders(array $headers, array $options): array
    {
        $out = [];

        foreach ($headers as $key => $value) {
            $out[] = is_int($key) ? (string) $value : "{$key}: {$value}";
        }

        if (! empty($options['token'])) {
            $out[] = 'Authorization: Bearer ' . $options['token'];
        }

        $out[] = 'Accept: application/json';

        return $out;
    }

    protected function buildResult(string $text, int $status): array
    {
        $json = json_decode($text, true);

        return [
            'text'   => $text,
            'status' => $status,
            'json'   => is_array($json) ? $json : null,
        ];
    }
}
