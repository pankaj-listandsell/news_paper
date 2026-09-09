<?php

namespace App\Social;

/**
 * What a platform gave back after a post attempt.
 */
class SocialResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $remoteId = null,
        public readonly ?string $remoteUrl = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function sent(?string $remoteId = null, ?string $remoteUrl = null): self
    {
        return new self(true, $remoteId, $remoteUrl);
    }

    public static function failed(string $error): self
    {
        return new self(false, error: $error);
    }
}
