<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Two;

use Kodhe\Framework\Socialite\SocialiteUser;

/**
 * LinkedIn "Sign In With OpenID Connect" (the current OAuth 2.0 flow).
 *
 * Endpoints:
 *   authorize: https://www.linkedin.com/oauth/v2/authorization
 *   token:     https://www.linkedin.com/oauth/v2/accessToken
 *   userinfo:  https://api.linkedin.com/v2/userinfo (OpenID)
 */
class LinkedinProvider extends AbstractProvider2
{
    protected array $scopes = ['openid', 'profile', 'email'];

    public function getAuthUrl(): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization';
    }

    public function getTokenUrl(): string
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    public function getUserFromToken(string $token): SocialiteUser
    {
        $raw = $this->httpGetForToken('https://api.linkedin.com/v2/userinfo', $token);

        $picture = $this->arrayGet($raw, 'picture');

        return $this->fillUser(new SocialiteUser(), $raw, [
            'id'       => ['sub'],
            'name'     => ['name'],
            'email'    => ['email'],
            'nickname' => ['given_name'],
            'avatar'   => [],
        ])->setAvatar(is_string($picture) ? $picture : null);
    }
}
