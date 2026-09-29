<?php

declare(strict_types=1);

namespace Kodhe\Framework\Socialite;

/**
 * Simple HTTP redirect response produced by AbstractProvider::redirect().
 *
 * send() emits the headers and terminates the hop; __toString() returns
 * the target URL so it can be logged or inspected in tests. If your
 * application has its own response pipeline, use getTargetUrl() and
 * build a native redirect from it.
 */
class RedirectResponse
{
    public function __construct(protected string $targetUrl, protected int $status = 302)
    {
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * Emit the Location header (no-op when headers were already sent,
     * e.g. during CLI tests).
     */
    public function send(bool $exit = true): void
    {
        if (! headers_sent()) {
            header('Location: ' . $this->targetUrl, true, $this->status);
        }

        if ($exit && PHP_SAPI !== 'cli') {
            exit;
        }
    }

    public function __toString(): string
    {
        return $this->targetUrl;
    }
}
