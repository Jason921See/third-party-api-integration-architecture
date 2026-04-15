<?php

namespace App\Integrations\Tenant\ExternalService\Facebook;

use App\Integrations\Tenant\ExternalService\Facebook\Dto\ApiResponseDto;
use App\Integrations\Tenant\ExternalService\Facebook\FacebookClient;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookService
{
    private FacebookClient $client;
    private IntegrationProvider $provider;

    public function __construct(private readonly Integration $integration)
    {
        $this->client = new FacebookClient($integration);
        $this->provider = $this->getProvider();
    }

    // ─────────────────────────────────────────────
    // Ad Account
    // ─────────────────────────────────────────────

    public function getAdAccount(string $adAccountId): ApiResponseDto
    {
        return $this->client->get("{$adAccountId}", [
            'fields' => 'id,name,account_status,currency,timezone_name,spend_cap,amount_spent',
        ]);
    }

    public function getAdAccounts(): ApiResponseDto
    {
        $userId = $this->integration->external_user_id ?? 'me';

        return $this->client->get("{$userId}/adaccounts", [
            'fields' => 'id,name,account_status,currency,timezone_name',
        ]);
    }

    // ─────────────────────────────────────────────
    // Campaigns
    // ─────────────────────────────────────────────

    public function getCampaigns(string $adAccountId, array $filters = []): ApiResponseDto
    {
        return $this->client->get("{$adAccountId}/campaigns", array_merge([
            'fields' => 'id,name,status,objective,daily_budget,lifetime_budget,start_time,stop_time',
        ], $filters));
    }

    public function getCampaign(string $campaignId): ApiResponseDto
    {
        return $this->client->get("{$campaignId}", [
            'fields' => 'id,name,status,objective,daily_budget,lifetime_budget,start_time,stop_time,insights',
        ]);
    }

    public function createCampaign(string $adAccountId, array $data): ApiResponseDto
    {
        return $this->client->post("{$adAccountId}/campaigns", $data);
    }

    public function updateCampaign(string $campaignId, array $data): ApiResponseDto
    {
        return $this->client->post("{$campaignId}", $data);
    }

    public function deleteCampaign(string $campaignId): ApiResponseDto
    {
        return $this->client->delete("{$campaignId}");
    }

    // ─────────────────────────────────────────────
    // Ad Sets
    // ─────────────────────────────────────────────

    public function getAdSets(string $adAccountId, array $filters = []): ApiResponseDto
    {
        return $this->client->get("{$adAccountId}/adsets", array_merge([
            'fields' => 'id,name,status,campaign_id,daily_budget,lifetime_budget,targeting,start_time,end_time',
        ], $filters));
    }

    public function getAdSet(string $adSetId): ApiResponseDto
    {
        return $this->client->get("{$adSetId}", [
            'fields' => 'id,name,status,campaign_id,daily_budget,targeting',
        ]);
    }

    public function createAdSet(string $adAccountId, array $data): ApiResponseDto
    {
        return $this->client->post("{$adAccountId}/adsets", $data);
    }

    public function updateAdSet(string $adSetId, array $data): ApiResponseDto
    {
        return $this->client->post("{$adSetId}", $data);
    }

    public function deleteAdSet(string $adSetId): ApiResponseDto
    {
        return $this->client->delete("{$adSetId}");
    }

    // ─────────────────────────────────────────────
    // Ads
    // ─────────────────────────────────────────────

    public function getAds(string $adAccountId, array $filters = []): ApiResponseDto
    {
        return $this->client->get("{$adAccountId}/ads", array_merge([
            'fields' => 'id,name,status,adset_id,campaign_id,creative,created_time',
        ], $filters));
    }

    public function getAd(string $adId): ApiResponseDto
    {
        return $this->client->get("{$adId}", [
            'fields' => 'id,name,status,adset_id,campaign_id,creative',
        ]);
    }

    public function createAd(string $adAccountId, array $data): ApiResponseDto
    {
        return $this->client->post("{$adAccountId}/ads", $data);
    }

    public function updateAd(string $adId, array $data): ApiResponseDto
    {
        return $this->client->post("{$adId}", $data);
    }

    public function deleteAd(string $adId): ApiResponseDto
    {
        return $this->client->delete("{$adId}");
    }

    // ─────────────────────────────────────────────
    // Insights / Analytics
    // ─────────────────────────────────────────────

    public function getInsights(string $objectId, array $params = []): ApiResponseDto
    {
        return $this->client->get("{$objectId}/insights", array_merge([
            'fields'     => 'impressions,clicks,spend,reach,cpc,cpm,ctr,actions',
            'date_preset' => 'last_30d',
        ], $params));
    }

    public function getAccountInsights(string $adAccountId, array $params = []): ApiResponseDto
    {
        return $this->getInsights($adAccountId, $params);
    }

    public function getCampaignInsights(string $campaignId, array $params = []): ApiResponseDto
    {
        return $this->getInsights($campaignId, $params);
    }

    // ─────────────────────────────────────────────
    // Static Factory — resolve from DB
    // ─────────────────────────────────────────────

    public static function forUser(int $userId): self
    {
        $integration = Integration::where('user_id', $userId)
            ->whereHas('provider', fn($q) => $q->where('slug', 'facebook'))
            ->where('status', 'active')
            ->firstOrFail();

        return new self($integration);
    }

    public static function forIntegration(Integration $integration): self
    {
        return new self($integration);
    }

    // ─────────────────────────────────────────────
    // Graceful Response Helper
    // ─────────────────────────────────────────────

    public function handleResponse(ApiResponseDto $response, string $context = ''): array
    {
        if ($response->success) {
            return $response->data ?? [];
        }

        Log::warning("FacebookService failure [{$context}]", [
            'integration_id' => $this->integration->id,
            'error_code'     => $response->errorCode,
            'error_message'  => $response->errorMessage,
            'http_status'    => $response->httpStatus,
            'attempt'        => $response->attempt,
        ]);

        return [];
    }

    public function connect(int $userId, string $accessToken, string $adAccountId): array
    {
        if (!$this->provider) {
            return $this->error('Facebook integration provider not configured.', 500);
        }

        // Step 1 — Validate token and scopes
        $validation = $this->validateToken($accessToken);

        if (!$validation['valid']) {
            return $this->error($validation['error'], 422);
        }

        // Step 2 — Fetch ad account details
        $accountDetails = $this->fetchAdAccountDetails($accessToken, $adAccountId);

        if (!$accountDetails['success']) {
            return $this->error($accountDetails['error'], 422);
        }

        // Step 3 — Persist integration
        return $this->storeIntegration(
            userId: $userId,
            accessToken: $accessToken,
            adAccountId: $adAccountId,
            validation: $validation,
            accountDetails: $accountDetails['data'],
        );
    }

    // ─────────────────────────────────────────────
    // Token Validation
    // ─────────────────────────────────────────────

    public function validateToken(string $accessToken): array
    {
        // Use proper debug_token in production
        if (!app()->isLocal()) {
            return $this->validateTokenWithDebug($accessToken);
        }

        // In local — validate by calling API directly
        return $this->validateTokenDirect($accessToken);
    }

    private function validateTokenWithDebug(string $accessToken): array
    {
        $debugUrl       = $this->providerConfig('debug_url');
        $requiredScopes = $this->providerConfig('required_scopes', ['ads_read']);

        /** @var Response $response */
        $response = Http::get($debugUrl, [
            'input_token'  => $accessToken,
            'access_token' => $this->getAppToken(),
        ]);

        if (!$response->successful()) {
            return ['valid' => false, 'error' => 'Failed to validate token with Facebook.'];
        }

        $data = $response->json('data');

        if (!($data['is_valid'] ?? false)) {
            return ['valid' => false, 'error' => $data['error']['message'] ?? 'Invalid Facebook access token.'];
        }

        $grantedScopes = $data['scopes'] ?? [];
        $missingScopes = array_diff($requiredScopes, $grantedScopes);

        if (!empty($missingScopes)) {
            return [
                'valid' => false,
                'error' => 'Token is missing required scopes: ' . implode(', ', $missingScopes),
            ];
        }

        return [
            'valid'      => true,
            'scopes'     => $grantedScopes,
            'expires_at' => isset($data['expires_at'])
                ? \Carbon\Carbon::createFromTimestamp($data['expires_at'])
                : now()->addDays(60),
        ];
    }

    private function validateTokenDirect(string $accessToken): array
    {
        $baseUrl    = $this->providerConfig('base_url');
        $apiVersion = $this->providerConfig('api_version');

        /** @var Response $response */
        $response = Http::withoutVerifying()
            ->get("{$baseUrl}/{$apiVersion}/me/adaccounts", [
                'access_token' => $accessToken,
                'fields'       => 'id,name',
                'limit'        => 1,
            ]);

        if (!$response->successful()) {
            return [
                'valid' => false,
                'error' => $response->json('error.message') ?? 'Invalid or expired access token.',
            ];
        }

        return [
            'valid'      => true,
            'scopes'     => ['ads_read'],
            'expires_at' => now()->addDays(60),
        ];
    }

    // ─────────────────────────────────────────────
    // Token Refresh
    // ─────────────────────────────────────────────

    public function refreshToken(Integration $integration): array
    {
        try {
            // Read token_url from provider config
            $tokenUrl = $this->providerConfig('token_url');

            /** @var Response $response */
            $response = Http::get($tokenUrl, [
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

                return [
                    'success' => false,
                    'error'   => $response->json('error.message') ?? 'Token refresh failed.',
                ];
            }

            $data = $response->json();

            $integration->update([
                'access_token'      => $data['access_token'],
                'token_expires_at'  => now()->addSeconds($data['expires_in'] ?? 5184000),
                'last_refreshed_at' => now(),
                'status'            => 'active',
            ]);

            return [
                'success'      => true,
                'access_token' => $data['access_token'],
                'expires_at'   => $integration->fresh()->token_expires_at,
            ];
        } catch (\Exception $e) {
            Log::error('Facebook token refresh exception', [
                'integration_id' => $integration->id,
                'error'          => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    // ─────────────────────────────────────────────
    // Fetch Ad Account Details
    // ─────────────────────────────────────────────

    public function fetchAdAccountDetails(string $accessToken, string $adAccountId): array
    {
        try {
            // Build URL from provider config
            $baseUrl    = $this->providerConfig('base_url');
            $apiVersion = $this->providerConfig('api_version');
            $url        = "{$baseUrl}/{$apiVersion}/{$adAccountId}";

            /** @var Response $response */
            $response = Http::get($url, [
                'fields'       => 'id,account_id,name,account_status,currency,timezone_name,amount_spent',
                'access_token' => $accessToken,
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error'   => $response->json('error.message') ?? 'Failed to fetch ad account details.',
                ];
            }

            $data = $response->json();

            // Validate account is active (1 = active)
            if (isset($data['account_status']) && $data['account_status'] !== 1) {
                return [
                    'success' => false,
                    'error'   => 'Ad account is not active. Current status: ' . $this->resolveAccountStatus($data['account_status']),
                ];
            }

            return [
                'success' => true,
                'data'    => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Facebook ad account fetch exception', [
                'ad_account_id' => $adAccountId,
                'error'         => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => 'Unable to fetch ad account details from Facebook.',
            ];
        }
    }

    // ─────────────────────────────────────────────
    // Store Integration
    // ─────────────────────────────────────────────

    private function storeIntegration(
        int    $userId,
        string $accessToken,
        string $adAccountId,
        array  $validation,
        array  $accountDetails,
    ): array {
        try {
            DB::beginTransaction();

            $integration = Integration::updateOrCreate(
                [
                    'user_id'                 => $userId,
                    'ip_id'                   => $this->provider->id,
                    'external_user_id'        => $adAccountId,
                ],
                [
                    'status'                => 'active',
                    'access_token'          => $accessToken,
                    'token_expires_at'      => $validation['expires_at'],
                    'external_account_name' => $accountDetails['name'] ?? $adAccountId,
                    'scopes'                => $validation['scopes'],
                    'meta'                  => [
                        'account_id'     => $accountDetails['account_id'] ?? null,
                        'account_status' => $accountDetails['account_status'] ?? null,
                        'currency'       => $accountDetails['currency'] ?? null,
                        'timezone'       => $accountDetails['timezone_name'] ?? null,
                        'amount_spent'   => $accountDetails['amount_spent'] ?? null,
                    ],
                    'last_used_at'          => now(),
                ],
            );

            DB::commit();

            return [
                'success'     => true,
                'integration' => [
                    'id'              => $integration->id,
                    'provider'        => $this->provider->slug,
                    'account_id'      => $adAccountId,
                    'account_name'    => $integration->external_account_name,
                    'status'          => $integration->status,
                    'scopes'          => $integration->scopes,
                    'currency'        => $integration->meta['currency'] ?? null,
                    'timezone'        => $integration->meta['timezone'] ?? null,
                    'token_expires_at' => $integration->token_expires_at,
                    'connected_at'    => $integration->updated_at,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Facebook integration store failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return $this->error('Failed to store integration. Please try again.', 500);
        }
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function getProvider(): ?IntegrationProvider
    {
        return IntegrationProvider::where('slug', 'facebook')
            ->where('is_active', true)
            ->first();
    }

    // Read any key from provider config JSON
    private function providerConfig(string $key, mixed $default = null): mixed
    {
        $config = $this->provider->config ?? [];
        return $config[$key] ?? $default;
    }

    private function getAppToken(): string
    {
        return config('services.facebook.client_id') . '|' . config('services.facebook.client_secret');
    }

    private function resolveAccountStatus(int $status): string
    {
        return match ($status) {
            2   => 'disabled',
            3   => 'unsettled',
            7   => 'pending_risk_review',
            8   => 'pending_settlement',
            9   => 'in_grace_period',
            100 => 'pending_closure',
            101 => 'closed',
            default => 'unknown',
        };
    }

    private function error(string $message, int $code): array
    {
        return [
            'success' => false,
            'error'   => $message,
            'code'    => $code,
        ];
    }
}
