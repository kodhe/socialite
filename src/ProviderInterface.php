<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite;

/**
 * Contract implemented by every social login provider wrapper.
 */
interface ProviderInterface
{
    /**
     * The provider name (e.g. "google", "github", "facebook").
     */
    public function getName(): string;

    /**
     * Redirect the user to the provider's authentication page.
     *
     * @param  array  $scopes  Optional extra scopes for this request.
     * @return mixed Implementation-specific redirect response.
     */
    public function redirect(array $scopes = []);

    /**
     * Resolve the user that returned from the provider callback.
     */
    public function user(): SocialiteUser;
}
