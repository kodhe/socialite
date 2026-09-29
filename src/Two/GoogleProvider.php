<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Two;

use Kodhe\Framework\Socialite\SocialiteUser;

/**
 * Google Sign-In via OAuth 2.0 / OpenID Connect.
 *
 * Endpoints (well-known: https://accounts.google.com/.well-known/openid-configuration):
 *   authorize: https://accounts.google.com/o/oauth2/v2/auth
 *   token:     https://oauth2.googleapis.com/token
 *   userinfo:  https://openidconnect.googleapis.com/v1/userinfo
 */
class GoogleProvider extends AbstractProvider2
{
    protected array $scopes = ['openid', 'profile', 'email'];

    public function getAuthUrl(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    public function getTokenUrl(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    public function getUserInfoUrl(): string
    {
        return $this->config['userinfo_url'] ?? 'https://openidconnect.googleapis.com/v1/userinfo';
    }

    protected function authorizationParams(): array
    {
        // Ask Google to always show the account chooser unless configured otherwise.
        return array_filter([
            'access_type' => $this->config['access_type'] ?? 'offline',
            'prompt'      => $this->config['prompt'] ?? 'select_account',
        ]);
    }

    public function getUserFromToken(string $token): SocialiteUser
    {
        $raw = $this->httpGetForToken($this->getUserInfoUrl(), $token);

        $avatar = $this->arrayGet($raw, 'picture');

        return $this->fillUser(new SocialiteUser(), $raw, [
            'id'       => ['sub', 'id'],
            'name'     => ['name'],
            'email'    => ['email'],
            'nickname' => ['hd'],
            'avatar'   => ['picture'],
        ])->setAvatar(is_string($avatar) ? $avatar : null);
    }
}
