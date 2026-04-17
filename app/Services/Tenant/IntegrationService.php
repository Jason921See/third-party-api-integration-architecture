<?php

namespace App\Services\Tenant;

use App\Integrations\Tenant\ExternalService\Facebook\FacebookService;
use App\Jobs\Tenant\FetchFacebookInsightJob;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationProvider;
use Illuminate\Support\Collection;
// use Illuminate\Database\Eloquent\Collection;

class IntegrationService
{
    // ─────────────────────────────────────────────
    // Connect
    // ─────────────────────────────────────────────

    public function connect(int $userId, string $provider, string $accessToken, string $adAccountId): array
    {
        $adAccountId = $this->normalizeAdAccountId($adAccountId);
        $integrationProvider = IntegrationProvider::where('slug', $provider)->first();
        // 🔒 prevent duplicate integration (race-condition safe)
        $existing = Integration::where('ip_id', $integrationProvider->id)
            ->where('external_user_id', $adAccountId)
            ->first();

        if ($existing) {
            // same user → allow update (reconnect)
            if ($existing->user_id === $userId) {
                return [
                    'success' => true,
                    'data' => [
                        'integration' => $existing,
                    ],
                ];
            }

            // different user → block
            return [
                'success' => false,
                'error'   => 'This ad account is already connected by another user.',
                'code'    => 409,
            ];
        }

        return match ($provider) {
            'facebook' => (new FacebookService(new Integration()))
                ->connect($userId, $accessToken, $adAccountId),

            default => $this->unsupported($provider),
        };
    }

    // ─────────────────────────────────────────────
    // Sync
    // ─────────────────────────────────────────────

    public function sync(Collection $integrations, array $validated): array
    {
        if ($integrations->isEmpty()) {
            return [
                'success' => false,
                'error'   => 'No active integrations found or not connected.',
                'code'    => 404,
            ];
        }

        foreach ($integrations as $integration) {
            match ($integration->provider->slug ?? null) {
                'facebook' => $this->dispatchFacebookSync($integration, $validated),
                default    => null,
            };
        }

        return [
            'success'   => true,
            'provider'  => $validated['provider'],
            'level'     => $validated['level'],
            'date_from' => $validated['date_from'],
            'date_to'   => $validated['date_to'],
        ];
    }

    // ─────────────────────────────────────────────
    // Provider Dispatchers
    // ─────────────────────────────────────────────

    private function dispatchFacebookSync(Integration $integration, array $validated): bool
    {
        FetchFacebookInsightJob::dispatch(
            integrationId: $integration->id,
            level: $validated['level'],
            adAccountId: $integration->external_user_id,
            fields: $validated['fields'] ?? null,
            dateStart: $validated['date_from'],
            dateStop: $validated['date_to'],
        );

        return true;
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    public function findActiveIntegration(int $userId, string $providerSlug): ?Collection
    {
        return Integration::where('user_id', $userId)
            ->whereHas('provider', fn($q) => $q->where('slug', $providerSlug))
            ->where('status', 'active')
            ->get();
    }

    public function normalizeAdAccountId(string $adAccountId): string
    {
        $adAccountId = trim($adAccountId);

        return str_starts_with($adAccountId, 'act_')
            ? $adAccountId
            : 'act_' . $adAccountId;
    }
    public function getDefaultFields(): array
    {
        return [
            'impressions',
            'clicks',
            'reach',
            'spend',
            'cpc',
            'cpm',
            'ctr',
            'cpp',
            'frequency',
            'actions',
            'action_values',
        ];
    }

    private function unsupported(string $provider): array
    {
        return [
            'success' => false,
            'error'   => "Provider [{$provider}] not supported.",
            'code'    => 422,
        ];
    }
}
