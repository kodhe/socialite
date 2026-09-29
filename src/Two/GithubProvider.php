<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Two;

use Kodhe\Framework\Socialite\SocialiteUser;

/**
 * GitHub OAuth app ("Log in with GitHub").
 *
 * Endpoints:
 *   authorize: https://github.com/login/oauth/authorize
 *   token:     https://github.com/login/oauth/access_token
 *   user:      https://api.github.com/user (+ /user/emails for private addresses)
 */
class GithubProvider extends AbstractProvider2
{
    protected array $scopes = ['user:email'];

    public function getAuthUrl(): string
    {
        return 'https://github.com/login/oauth/authorize';
    }

    public function getTokenUrl(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    public function apiBaseUrl(): string
    {
        // Supports GitHub Enterprise via config.
        return rtrim((string) ($this->config['api_url'] ?? 'https://api.github.com'), '/');
    }

    public function getUserFromToken(string $token): SocialiteUser
    {
        $raw = $this->httpGetForToken($this->apiBaseUrl() . '/user', $token, [
            'headers' => ['Accept' => 'application/vnd.github+json'],
        ]);

        $user = $this->fillUser(new SocialiteUser(), $raw, [
            'id'       => ['id'],
            'name'     => ['name', 'login'],
            'email'    => ['email'],
            'nickname' => ['login'],
            'avatar'   => ['avatar_url'],
        ]);

        // GitHub hides private e-mail addresses from /user; fetch them too.
        if ($user->getEmail() === null) {
            try {
                $emails = $this->httpGetForToken($this->apiBaseUrl() . '/user/emails', $token, [
                    'headers' => ['Accept' => 'application/vnd.github+json'],
                ]);

                foreach ((array) $emails as $entry) {
                    if (is_array($entry) && ! empty($entry['email'])) {
                        $user->setEmail((string) $entry['email']);

                        if (! empty($entry['primary'])) {
                            break;
                        }
                    }
                }
            } catch (\Throwable) {
                // Non-fatal: leave email null so the app can ask for it.
            }
        }

        return $user;
    }
}
