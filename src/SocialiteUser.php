<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite;

/**
 * Immutable-style data object representing an authenticated user returned
 * by an OAuth provider (Google, GitHub, Facebook, ...).
 *
 * This decouples the Kodhe framework from any third-party socialite
 * user contract while keeping a familiar, drop-in compatible API.
 */
class SocialiteUser
{
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?string $email = null;
    protected ?string $nickname = null;
    protected ?string $avatar = null;
    protected ?string $accessToken = null;
    protected ?string $refreshToken = null;
    protected ?int $expiresIn = null;
    protected array $extra = [];

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): static
    {
        $this->nickname = $nickname;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getExpiresIn(): ?int
    {
        return $this->expiresIn;
    }

    public function setExpiresIn(?int $expiresIn): static
    {
        $this->expiresIn = $expiresIn;

        return $this;
    }

    /**
     * Raw provider payload / additional attributes.
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    public function setExtra(array $extra): static
    {
        $this->extra = $extra;

        return $this;
    }

    public function __get(string $key)
    {
        return $this->extra[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->extra[$key]);
    }
}
