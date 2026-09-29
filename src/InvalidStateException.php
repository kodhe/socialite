<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite;

/**
 * Thrown when the OAuth "state" parameter is missing or does not match
 * the value stored at authorization time (possible CSRF / replay attack).
 */
class InvalidStateException extends SocialiteException
{
    public static function missing(): self
    {
        return new static(
            'Unable to detect the OAuth "state" parameter. Start the login flow '
            . 'with socialite($provider)->redirect() so a state token is generated.'
        );
    }

    public static function mismatch(): self
    {
        return new static(
            'The OAuth "state" parameter returned by the provider does not match '
            . 'the one stored in this session. The login attempt was rejected.'
        );
    }
}
