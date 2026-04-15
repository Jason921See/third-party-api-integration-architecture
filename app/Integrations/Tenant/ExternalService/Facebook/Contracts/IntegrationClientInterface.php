<?php

namespace App\Integrations\Tenant\ExternalService\Facebook\Contracts;

use App\Integrations\Tenant\ExternalService\Facebook\Dto\ApiResponseDto;
use App\Models\Tenant\Integration;

interface IntegrationClientInterface
{
    public function get(string $endpoint, array $params = []): ApiResponseDto;
    public function post(string $endpoint, array $payload = []): ApiResponseDto;
    public function delete(string $endpoint, array $params = []): ApiResponseDto;
    public function refreshToken(Integration $integration): bool;
}
