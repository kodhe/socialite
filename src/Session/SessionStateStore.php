<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Session;

use Kodhe\Framework\Socialite\Contracts\StateStoreInterface;

/**
 * State store backed by $_SESSION — works out of the box in Kodhe
 * applications (and plain PHP) once a session has been started.
 */
class SessionStateStore implements StateStoreInterface
{
    public function __construct(protected string $prefix = '_kodhe_socialite_')
    {
    }

    protected function &bucket(): array
    {
        if (! isset($_SESSION[$this->prefix]) || ! is_array($_SESSION[$this->prefix])) {
            $_SESSION[$this->prefix] = [];
        }

        return $_SESSION[$this->prefix];
    }

    public function put(string $key, array $payload): void
    {
        $bucket = &$this->bucket();
        $bucket[$key] = $payload;
    }

    public function pull(string $key): ?array
    {
        $bucket = &$this->bucket();

        if (! array_key_exists($key, $bucket)) {
            return null;
        }

        $payload = $bucket[$key];
        unset($bucket[$key]);

        return is_array($payload) ? $payload : null;
    }
}
