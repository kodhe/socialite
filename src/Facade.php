<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite;

/**
 * Static facade for the social login manager.
 *
 *   use Kodhe\Framework\Socialite\Facade as Socialite;
 *
 *   return Socialite::driver('google')->redirect();
 *   $user = Socialite::driver('google')->user();
 *
 * Functionally identical to the global socialite() helper; both share
 * the same singleton Manager instance.
 */
class Facade
{
    protected static ?Manager $manager = null;

    /**
     * The shared Manager instance, built lazily from config/socialite.php.
     */
    public static function manager(): Manager
    {
        return self::$manager ??= new Manager(static::loadConfig());
    }

    /**
     * Swap the underlying manager (mainly for tests / custom configs).
     */
    public static function setManager(?Manager $manager): void
    {
        self::$manager = $manager;
    }

    public static function driver(string $name): ProviderInterface
    {
        return static::manager()->driver($name);
    }

    public static function hasProvider(string $name): bool
    {
        return static::manager()->hasProvider($name);
    }

    /** @return string[] */
    public static function getProviders(): array
    {
        return static::manager()->getProviders();
    }

    public static function extend(string $name, callable $callback): void
    {
        static::manager()->extend($name, $callback);
    }

    /**
     * Load configuration from common Kodhe locations. Returns an empty
     * array when no config file is present so the component degrades
     * gracefully until the application provides one.
     */
    protected static function loadConfig(): array
    {
        $candidates = [];

        if (function_exists('config_path')) {
            $candidates[] = config_path('socialite.php');
        }

        if (function_exists('app_path')) {
            $candidates[] = app_path('config/socialite.php');
        }

        $candidates[] = dirname(__DIR__) . '/config/socialite.php';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $config = require $path;

                return is_array($config) ? $config : [];
            }
        }

        return [];
    }

    /**
     * Pass-through so Socialite::google(), Socialite::github(), ... work.
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $manager = static::manager();

        if (method_exists($manager, $name)) {
            return $manager->{$name}(...$arguments);
        }

        return $manager->driver($name);
    }
}
