<?php

declare(strict_types=1);

use Kodhe\Framework\Socialite\Facade as SocialiteFacade;


if (! function_exists('socialite')) {
    /**
     * Social (OAuth) login shortcut — implemented natively by the Kodhe
     * framework (no laravel/socialite dependency).
     *
     *   socialite()                      => Manager instance
     *   socialite('google')->redirect()  => send user to Google consent page
     *   socialite('github')->user()      => SocialiteUser from the callback
     *
     * Supported out of the box: Google, GitHub, Facebook, GitLab,
     * LinkedIn (OAuth 2.0) and Twitter (OAuth 1.0a). Register custom
     * providers with socialite()->extend('name', fn ($config) => ...).
     *
     * @param string|null $driver Optional provider name to fetch directly.
     */
    function socialite(?string $driver = null): mixed
    {
        $manager = SocialiteFacade::manager();

        if ($driver !== null) {
            return $manager->driver($driver);
        }

        return $manager;
    }
}
