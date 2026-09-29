<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Socialite (social login) configuration — Kodhe native OAuth client
|--------------------------------------------------------------------------
|
| Login via Google, GitHub, Facebook, GitLab, LinkedIn (OAuth 2.0) and
| Twitter (OAuth 1.0a). Implemented natively by kodhe/socialite: NO
| laravel/socialite or other third-party OAuth package is required.
|
| Fill in the credentials from each provider's developer console, or
| supply them through environment variables.
|
| Typical routes in your application:
|
|   GET /auth/socialite/{provider}           -> controller calls
|       return socialite($provider)->redirect();
|
|   GET /auth/socialite/callback/{provider}  -> controller calls
|       $user = socialite($provider)->user();
|       // then find-or-create the local user and log them in
|
*/

return [

    /*
    | Base URI (or path) that providers redirect back to. Each provider
    | receives "/{provider}" appended automatically, e.g.
    | https://example.com/auth/socialite/callback/google
    */
    'callback_uri' => getenv('SOCIALITE_CALLBACK_URI') ?: '/auth/socialite/callback',

    'providers' => [

        'google' => [
            'enabled'       => filter_var(getenv('GOOGLE_ENABLE') ?: '1', FILTER_VALIDATE_BOOLEAN),
            'client_id'     => getenv('GOOGLE_CLIENT_ID') ?: '',
            'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
            'scopes'        => ['openid', 'profile', 'email'],
            // 'access_type' => 'offline',      // request a refresh token
            // 'prompt'      => 'select_account',
        ],

        'github' => [
            'enabled'       => filter_var(getenv('GITHUB_ENABLE') ?: '1', FILTER_VALIDATE_BOOLEAN),
            'client_id'     => getenv('GITHUB_CLIENT_ID') ?: '',
            'client_secret' => getenv('GITHUB_CLIENT_SECRET') ?: '',
            'scopes'        => ['read:user', 'user:email'],
            // 'api_url'     => 'https://github.example.com/api/v3', // GitHub Enterprise
        ],

        'facebook' => [
            'enabled'       => filter_var(getenv('FACEBOOK_ENABLE') ?: '1', FILTER_VALIDATE_BOOLEAN),
            'client_id'     => getenv('FACEBOOK_CLIENT_ID') ?: '',
            'client_secret' => getenv('FACEBOOK_CLIENT_SECRET') ?: '',
            'scopes'        => ['email', 'public_profile'],
            // 'graph_version' => 'v18.0',
            // 'fields'        => 'id,name,email,picture',
        ],

        'gitlab' => [
            'enabled'       => filter_var(getenv('GITLAB_ENABLE') ?: '0', FILTER_VALIDATE_BOOLEAN),
            'client_id'     => getenv('GITLAB_CLIENT_ID') ?: '',
            'client_secret' => getenv('GITLAB_CLIENT_SECRET') ?: '',
            'scopes'        => ['read_user'],
            // 'base_url'    => 'https://gitlab.example.com', // self-hosted
        ],

        'linkedin' => [
            'enabled'       => filter_var(getenv('LINKEDIN_ENABLE') ?: '0', FILTER_VALIDATE_BOOLEAN),
            'client_id'     => getenv('LINKEDIN_CLIENT_ID') ?: '',
            'client_secret' => getenv('LINKEDIN_CLIENT_SECRET') ?: '',
            'scopes'        => ['openid', 'profile', 'email'],
        ],

        'twitter' => [
            // OAuth 1.0a: "client_id" = API key, "client_secret" = API secret.
            'enabled'       => filter_var(getenv('TWITTER_ENABLE') ?: '0', FILTER_VALIDATE_BOOLEAN),
            'client_id'     => getenv('TWITTER_CONSUMER_KEY') ?: '',
            'client_secret' => getenv('TWITTER_CONSUMER_SECRET') ?: '',
        ],

    ],

];
