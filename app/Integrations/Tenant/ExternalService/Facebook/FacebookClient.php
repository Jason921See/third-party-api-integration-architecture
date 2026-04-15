<?php

namespace App\Integrations\Tenant\ExternalService\Facebook;

use App\Integrations\Tenant\ExternalService\Facebook\Contracts\IntegrationClientInterface;
use App\Integrations\Tenant\ExternalService\Facebook\Dto\ApiResponseDto;
use App\Integrations\Tenant\ExternalService\Facebook\Dto\RateLimitDto;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationApiLog;
use App\Models\Tenant\IntegrationRateLimit;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class FacebookClient implements IntegrationClientInterface
{
    private const BASE_URL    = 'https://graph.facebook.com';
    private const API_VERSION = 'v19.0';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 1000; // milliseconds

    // Error codes
    private const ERROR_RATE_LIMIT  = 'rate_limit';
    private const ERROR_AUTH        = 'auth_error';
    private const ERROR_SERVER      = 'server_error';
    private const ERROR_CLIENT      = 'client_error';
    private const ERROR_NETWORK     = 'network_error';
    private const ERROR_UNKNOWN     = 'unknown_error';

    // Facebook specific error codes
    private const FB_ERROR_AUTH         = [190, 102, 104];
    private const FB_ERROR_RATE_LIMIT   = [4, 17, 32, 613];

    public function __construct(private readonly Integration $integration) {}

    // ─────────────────────────────────────────────
    // Public Interface Methods
    // ─────────────────────────────────────────────

    public function get(string $endpoint, array $params = []): ApiResponseDto
    {
        return $this->request('GET', $endpoint, $params);
    }

    public function post(string $endpoint, array $payload = []): ApiResponseDto
    {
        return $this->request('POST', $endpoint, $payload);
    }

    public function delete(string $endpoint, array $params = []): ApiResponseDto
    {
        return $this->request('DELETE', $endpoint, $params);
    }

    // ─────────────────────────────────────────────
    // Token Refresh
    // ─────────────────────────────────────────────

    public function refreshToken(Integration $integration): bool
    {
        try {
            $response = Http::get(self::BASE_URL . '/' . self::API_VERSION . '/oauth/access_token', [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => config('services.facebook.client_id'),
                'client_secret'     => config('services.facebook.client_secret'),
                'fb_exchange_token' => $integration->access_token,
            ]);

            if (!$response->successful()) {
                Log::error('Facebook token refresh failed', [
                    'integration_id' => $integration->id,
                    'response'       => $response->json(),
                ]);
                return false;
            }

            $data = $response->json();

            $integration->update([
                'access_token'       => $data['access_token'],
                'token_expires_at'   => now()->addSeconds($data['expires_in'] ?? 5184000),
                'last_refreshed_at'  => now(),
                'status'             => 'active',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Facebook token refresh exception', [
                'integration_id' => $integration->id,
                'error'          => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ─────────────────────────────────────────────
    // Core Request with Retry + Backoff
    // ─────────────────────────────────────────────

    private function request(string $method, string $endpoint, array $data = []): ApiResponseDto
    {
        // Check rate limit before attempting
        $rateLimit = $this->getRateLimitRecord($endpoint);

        if ($rateLimit->isBlocked()) {
            return ApiResponseDto::failure(
                errorMessage: "Rate limited. Retry in {$rateLimit->secondsUntilUnblocked()} seconds.",
                errorCode: self::ERROR_RATE_LIMIT,
                httpStatus: 429,
            );
        }

        // Auto-refresh token if expired
        if ($this->integration->isTokenExpired()) {
            $refreshed = $this->refreshToken($this->integration);
            if (!$refreshed) {
                return ApiResponseDto::failure(
                    errorMessage: 'Access token expired and refresh failed.',
                    errorCode: self::ERROR_AUTH,
                    httpStatus: 401,
                );
            }
            $this->integration->refresh(); // reload model
        }

        $attempt  = 0;
        $response = null;
        $dto      = null;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;
            $startTime = microtime(true);

            try {
                $response      = $this->makeHttpRequest($method, $endpoint, $data);
                $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
                $dto            = $this->handleResponse($response, $endpoint, $method, $data, $attempt, $responseTimeMs, $rateLimit);

                // Success — stop retrying
                if ($dto->success) {
                    $rateLimit->incrementRequests();
                    break;
                }

                // Auth error — no point retrying
                if ($dto->errorCode === self::ERROR_AUTH) {
                    break;
                }

                // Rate limit — no point retrying, already blocked
                if ($dto->errorCode === self::ERROR_RATE_LIMIT) {
                    break;
                }

                // Retry with exponential backoff for server errors
                if ($attempt < self::MAX_RETRIES) {
                    $delay = $this->calculateBackoff($attempt);
                    Log::warning("Facebook API retry {$attempt} for {$endpoint}, waiting {$delay}ms");
                    usleep($delay * 1000);
                }
            } catch (\Exception $e) {
                $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

                $dto = ApiResponseDto::failure(
                    errorMessage: $e->getMessage(),
                    errorCode: self::ERROR_NETWORK,
                    httpStatus: 0,
                    attempt: $attempt,
                    responseTimeMs: $responseTimeMs,
                );

                $this->log($endpoint, $method, 0, false, $attempt, $responseTimeMs, $data, null, $e->getMessage(), self::ERROR_NETWORK);

                if ($attempt < self::MAX_RETRIES) {
                    $delay = $this->calculateBackoff($attempt);
                    usleep($delay * 1000);
                }
            }
        }

        return $dto;
    }

    // ─────────────────────────────────────────────
    // HTTP Request
    // ─────────────────────────────────────────────

    private function makeHttpRequest(string $method, string $endpoint, array $data): Response
    {
        $url    = $this->buildUrl($endpoint);
        $params = array_merge($data, ['access_token' => $this->integration->access_token]);

        /** @var Response $response */
        $response = match (strtoupper($method)) {
            'GET'    => Http::timeout(30)->get($url, $params),
            'POST'   => Http::timeout(30)->post($url, $params),
            'DELETE' => Http::timeout(30)->delete($url, $params),
            default  => throw new InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        return $response;
    }

    // ─────────────────────────────────────────────
    // Response Handler
    // ─────────────────────────────────────────────

    private function handleResponse(
        Response $response,
        string $endpoint,
        string $method,
        array $requestData,
        int $attempt,
        int $responseTimeMs,
        IntegrationRateLimit $rateLimit,
    ): ApiResponseDto {
        $body      = $response->json();
        $status    = $response->status();
        $fbError   = $body['error'] ?? null;
        $errorCode = null;

        // Rate limited
        if ($status === 429 || $this->isFacebookRateLimit($fbError)) {
            $rateLimitDto = RateLimitDto::fromHeaders($response->headers());
            $rateLimit->applyBlock($rateLimitDto->retryAfter);

            $this->log($endpoint, $method, $status, false, $attempt, $responseTimeMs, $requestData, $body, $fbError['message'] ?? 'Rate limited', self::ERROR_RATE_LIMIT);

            return ApiResponseDto::failure(
                errorMessage: $fbError['message'] ?? 'Rate limit reached.',
                errorCode: self::ERROR_RATE_LIMIT,
                httpStatus: $status,
                attempt: $attempt,
                responseTimeMs: $responseTimeMs,
            );
        }

        // Auth error
        if ($status === 401 || $this->isFacebookAuthError($fbError)) {
            // Try token refresh once
            $refreshed = $this->refreshToken($this->integration);

            $this->log($endpoint, $method, $status, false, $attempt, $responseTimeMs, $requestData, $body, $fbError['message'] ?? 'Auth error', self::ERROR_AUTH);

            if (!$refreshed) {
                $this->integration->update(['status' => 'expired']);

                return ApiResponseDto::failure(
                    errorMessage: $fbError['message'] ?? 'Authentication failed.',
                    errorCode: self::ERROR_AUTH,
                    httpStatus: $status,
                    attempt: $attempt,
                    responseTimeMs: $responseTimeMs,
                );
            }

            // Retry once with new token
            $this->integration->refresh();
            $retryResponse = $this->makeHttpRequest('GET', $endpoint, $requestData);
            $body          = $retryResponse->json();
            $status        = $retryResponse->status();
        }

        // Server error (5xx) — retriable
        if ($status >= 500) {
            $this->log($endpoint, $method, $status, false, $attempt, $responseTimeMs, $requestData, $body, $fbError['message'] ?? 'Server error', self::ERROR_SERVER);

            return ApiResponseDto::failure(
                errorMessage: $fbError['message'] ?? 'Facebook server error.',
                errorCode: self::ERROR_SERVER,
                httpStatus: $status,
                attempt: $attempt,
                responseTimeMs: $responseTimeMs,
            );
        }

        // Client error (4xx) — not retriable
        if ($status >= 400) {
            $this->log($endpoint, $method, $status, false, $attempt, $responseTimeMs, $requestData, $body, $fbError['message'] ?? 'Client error', self::ERROR_CLIENT);

            return ApiResponseDto::failure(
                errorMessage: $fbError['message'] ?? 'Client error.',
                errorCode: self::ERROR_CLIENT,
                httpStatus: $status,
                attempt: $attempt,
                responseTimeMs: $responseTimeMs,
            );
        }

        // Success
        $this->log($endpoint, $method, $status, true, $attempt, $responseTimeMs, $requestData, $body);

        $this->integration->update(['last_used_at' => now()]);

        return ApiResponseDto::success(
            data: $body,
            httpStatus: $status,
            attempt: $attempt,
            responseTimeMs: $responseTimeMs,
        );
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function buildUrl(string $endpoint): string
    {
        return rtrim(self::BASE_URL, '/') . '/' . self::API_VERSION . '/' . ltrim($endpoint, '/');
    }

    private function calculateBackoff(int $attempt): int
    {
        // Exponential backoff: 1s, 2s, 4s + jitter
        return (int) (self::RETRY_DELAY * pow(2, $attempt - 1)) + rand(0, 500);
    }

    private function isFacebookAuthError(?array $error): bool
    {
        if (!$error) return false;
        return in_array($error['code'] ?? null, self::FB_ERROR_AUTH);
    }

    private function isFacebookRateLimit(?array $error): bool
    {
        if (!$error) return false;
        return in_array($error['code'] ?? null, self::FB_ERROR_RATE_LIMIT);
    }

    private function getRateLimitRecord(string $endpoint): IntegrationRateLimit
    {
        $rateLimit = IntegrationRateLimit::firstOrCreate(
            [
                'integration_id' => $this->integration->id,
                'endpoint'       => $endpoint,
            ],
        );

        // Reset window if expired
        if ($rateLimit->isWindowExpired()) {
            $rateLimit->resetWindow();
        }

        return $rateLimit;
    }

    private function log(
        string  $endpoint,
        string  $method,
        int     $httpStatus,
        bool    $success,
        int     $attempt,
        int     $responseTimeMs,
        array   $requestPayload = [],
        ?array  $responsePayload = null,
        ?string $errorMessage = null,
        ?string $errorCode = null,
    ): void {
        IntegrationApiLog::create([
            'integration_id'   => $this->integration->id,
            'endpoint'         => $endpoint,
            'method'           => $method,
            'http_status'      => $httpStatus,
            'success'          => $success,
            'attempt'          => $attempt,
            'response_time_ms' => $responseTimeMs,
            'request_payload'  => $requestPayload,
            'response_payload' => $responsePayload,
            'error_message'    => $errorMessage,
            'error_code'       => $errorCode,
        ]);
    }
}
