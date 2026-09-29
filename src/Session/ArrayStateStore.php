<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Session;

use Kodhe\Framework\Socialite\Contracts\StateStoreInterface;

/**
 * In-memory state store. Useful for tests and single-request flows;
 * not suitable across redirects unless you persist it yourself.
 */
class ArrayStateStore implements StateStoreInterface
{
    /** @var array<string, array> */
    protected array $data = [];

    public function put(string $key, array $payload): void
    {
        $this->data[$key] = $payload;
    }

    public function pull(string $key): ?array
    {
        if (! array_key_exists($key, $this->data)) {
            return null;
        }

        $payload = $this->data[$key];
        unset($this->data[$key]);

        return is_array($payload) ? $payload : null;
    }
}
