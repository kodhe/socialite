<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite\Two;

use Kodhe\Framework\Socialite\SocialiteUser;

/**
 * Facebook Login via Graph API OAuth 2.0.
 *
 * Endpoints:
 *   authorize: https://www.facebook.com/{version}/dialog/oauth
 *   token:     https://graph.facebook.com/{version}/oauth/access_token
 *   me:        https://graph.facebook.com/{version}/me?fields=...
 */
class FacebookProvider extends AbstractProvider2
{
    protected array $scopes = ['email', 'public_profile'];

    public function getAuthUrl(): string
    {
        return 'https://www.facebook.com/' . $this->graphVersion() . '/dialog/oauth';
    }

    public function getTokenUrl(): string
    {
        return 'https://graph.facebook.com/' . $this->graphVersion() . '/oauth/access_token';
    }

    protected function graphVersion(): string
    {
        return (string) ($this->config['graph_version'] ?? 'v18.0');
    }

    protected function authorizationParams(): array
    {
        return array_filter([
            'display' => $this->config['display'] ?? 'popup',
        ]);
    }

    public function getUserFromToken(string $token): SocialiteUser
    {
        $fields = $this->config['fields'] ?? 'id,name,email,picture.width(200){url,is_silhouette}';

        // Facebook wants the token as a query parameter on Graph calls.
        $raw = $this->httpGetForToken(
            'https://graph.facebook.com/' . $this->graphVersion() . '/me?'
                . http_build_query(['fields' => $fields, 'access_token' => $token]),
            $token
        );

        return $this->fillUser(new SocialiteUser(), $raw, [
            'id'       => ['id'],
            'name'     => ['name'],
            'email'    => ['email'],
            'nickname' => ['first_name'],
            'avatar'   => ['picture.data.url'],
        ]);
    }
}
