<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Auth <-> Socialite bridge configuration (kodhe/auth · SocialiteAuth)
|--------------------------------------------------------------------------
|
| This file unifies the two login modes into ONE authentication system:
|
|   - password login : auth()->attempt($email, $password)
|   - social login   : social_auth()->redirect('google')  (start)
|                      social_auth()->login('google')     (callback)
|
| Both flows share the same guard, session payload, remember-me tokens,
| events and roles/permissions/ACL stack — a user who signs in with Google
| has exactly the same access as one who signs in with a password.
|
| OAuth credentials themselves live in config/socialite.php; this file only
| controls how social identities map onto local accounts.
|
*/

return [

    /*
    | Column on your users table holding linked social identities as an
    | array or comma-separated list of "<provider>:<provider-id>" refs,
    | e.g. "google:10934821776543210,github:991".
    | Add it via migration, e.g.:
    |   ALTER TABLE users ADD COLUMN social_accounts TEXT NULL;
    */
    'social_accounts_column' => 'social_accounts',

    // Profile columns copied from the provider onto new local accounts.
    'name_column'   => 'name',
    'email_column'  => 'email',
    'avatar_column' => 'avatar',

    // null = reuse the guard's identifier column (usually "email").
    'identifier_column' => null,

    // Also store a "<provider>_id" column value (e.g. google_id) for apps
    // that prefer per-provider id columns over the shared list column.
    'set_provider_id' => true,

    /*
    | Persist the OAuth access/refresh tokens of every social login into a
    | "social_tokens" TEXT/JSON column on the users table:
    |   ALTER TABLE users ADD COLUMN social_tokens TEXT NULL;
    | Then social_auth()->accessTokenFor('google') returns a usable token
    | and renews it automatically through the stored refresh token.
    */
    'store_tokens' => true,

    /*
    | First-time social logins automatically create a local account with a
    | random password (the user can claim a real password later through the
    | normal password-reset flow). Set false to require pre-existing
    | accounts / manual linking.
    */
    'register_new_users' => true,

    /*
    | When the provider e-mail matches an existing account (e.g. someone
    | registered with a password last year), LINK the social identity to
    | that account instead of creating a duplicate.
    */
    'link_by_email' => true,

    /*
    | Hardening: require the provider to report a VERIFIED e-mail before
    | linking by e-mail is allowed (prevents account takeover via a fake
    | social profile using somebody else's address). Needs the matching
    | provider scopes; Google/GitHub/Facebook supply email_verified.
    */
    'require_verified_email_for_link' => false,

    // Auto-mark e-mails from these trusted providers as verified on signup.
    'auto_verify_trusted' => true,
    'trusted_providers'   => ['google', 'github', 'facebook', 'gitlab', 'linkedin'],

    // Default destination after a successful social login. The "intended"
    // URL remembered by social_auth()->rememberIntended() wins over this.
    'redirect_after_login' => '/dashboard',

    // Where guests are sent when the social callback fails. Set
    // 'throw_on_error' => true to receive an AuthException instead.
    'redirect_on_error' => '/login',
    'throw_on_error'    => false,

    // Route prefix that starts the flow (your app should route
    // GET {login_route}/{provider} -> social_auth()->redirect($provider)).
    'login_route' => '/auth/socialite',

    // Session slot used for the post-login intended URL.
    'intended_key' => 'socialite_intended',

    /*
    | Optional explicit Manager instance or factory (fn(): Manager).
    | Leave null to build one from config/socialite.php automatically.
    */
    'manager'     => null,
    'state_store' => null,

    // Optional pre-built guard instance; null = use the shared auth() guard.
    'guard' => null,

];
