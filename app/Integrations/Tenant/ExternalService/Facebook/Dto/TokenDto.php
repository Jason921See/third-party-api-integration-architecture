<?php

namespace App\Integrations\Tenant\ExternalService\Facebook\Dto;

class TokenDto
{
    public function __construct(
        public readonly string  $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly ?int    $expiresIn = null,
        public readonly ?string $tokenType = 'Bearer',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresIn: $data['expires_in'] ?? null,
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }
}
