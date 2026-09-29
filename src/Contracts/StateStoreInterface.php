<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Contracts;

/**
 * Storage for the OAuth "state" anti-CSRF token (and the redirect URI
 * that goes with it) between the authorization redirect and the
 * provider callback.
 *
 * Implementations may use PHP sessions, cache, database, etc.
 */
interface StateStoreInterface
{
    /**
     * Persist a state payload under the given key.
     *
     * @param array{state: string, created_at: int, ...} $payload
     */
    public function put(string $key, array $payload): void;

    /**
     * Read and delete the payload stored under the given key.
     *
     * @return array|null Null when nothing is stored for the key.
     */
    public function pull(string $key): ?array;
}
