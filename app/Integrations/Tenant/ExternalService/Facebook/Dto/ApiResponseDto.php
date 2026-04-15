<?php

namespace App\Integrations\Tenant\ExternalService\Facebook\Dto;

class ApiResponseDto
{
    public function __construct(
        public readonly bool    $success,
        public readonly int     $httpStatus,
        public readonly ?array  $data = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null,
        public readonly int     $attempt = 1,
        public readonly int     $responseTimeMs = 0,
    ) {}

    public static function success(array $data, int $httpStatus = 200, int $attempt = 1, int $responseTimeMs = 0): self
    {
        return new self(
            success: true,
            httpStatus: $httpStatus,
            data: $data,
            attempt: $attempt,
            responseTimeMs: $responseTimeMs,
        );
    }

    public static function failure(string $errorMessage, string $errorCode, int $httpStatus, int $attempt = 1, int $responseTimeMs = 0): self
    {
        return new self(
            success: false,
            httpStatus: $httpStatus,
            data: null,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            attempt: $attempt,
            responseTimeMs: $responseTimeMs,
        );
    }
}
