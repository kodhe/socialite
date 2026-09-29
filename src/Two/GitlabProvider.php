<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Two;

use Kodhe\Framework\Socialite\SocialiteUser;

/**
 * GitLab OAuth 2.0 ("Sign in with GitLab"). Works with gitlab.com and
 * self-hosted instances via the "base_url" config key.
 */
class GitlabProvider extends AbstractProvider2
{
    protected array $scopes = ['read_user'];

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://gitlab.com'), '/');
    }

    public function getAuthUrl(): string
    {
        return $this->baseUrl() . '/oauth/authorize';
    }

    public function getTokenUrl(): string
    {
        return $this->baseUrl() . '/oauth/token';
    }

    public function getUserFromToken(string $token): SocialiteUser
    {
        $raw = $this->httpGetForToken($this->baseUrl() . '/api/v4/user', $token);

        return $this->fillUser(new SocialiteUser(), $raw, [
            'id'       => ['id'],
            'name'     => ['name'],
            'email'    => ['email'],
            'nickname' => ['username'],
            'avatar'   => ['avatar_url', 'web_url'],
        ]);
    }
}
