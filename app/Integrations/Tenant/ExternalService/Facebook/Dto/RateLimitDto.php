<?php

namespace App\Integrations\Tenant\ExternalService\Facebook\Dto;

class RateLimitDto
{
    public function __construct(
        public readonly bool  $isLimited,
        public readonly int   $retryAfter = 0,
        public readonly ?int  $requestsLimit = null,
        public readonly ?int  $requestsRemaining = null,
        public readonly ?string $resetAt = null,
    ) {}

    public static function fromHeaders(array $headers): self
    {
        $retryAfter = (int) ($headers['Retry-After'][0] ?? $headers['X-Rate-Limit-Reset'][0] ?? 60);

        return new self(
            isLimited: true,
            retryAfter: $retryAfter,
            requestsLimit: isset($headers['X-Rate-Limit-Limit'][0])
                ? (int) $headers['X-Rate-Limit-Limit'][0]
                : null,
            requestsRemaining: isset($headers['X-Rate-Limit-Remaining'][0])
                ? (int) $headers['X-Rate-Limit-Remaining'][0]
                : null,
            resetAt: $headers['X-Rate-Limit-Reset'][0] ?? null,
        );
    }

    public static function notLimited(): self
    {
        return new self(isLimited: false);
    }
}
