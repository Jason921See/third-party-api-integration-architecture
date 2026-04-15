<?php

namespace App\Services\Tenant;

use App\Integrations\Tenant\ExternalService\Facebook\FacebookService;
use App\Jobs\Tenant\FetchFacebookInsightJob;
use App\Models\Tenant\Integration;

class IntegrationService
{
    // ─────────────────────────────────────────────
    // Connect
    // ─────────────────────────────────────────────

    public function connect(int $userId, string $provider, string $accessToken, string $adAccountId): array
    {
        $adAccountId = $this->normalizeAdAccountId($adAccountId);

        // 🔒 prevent duplicate integration (race-condition safe)
        $existing = Integration::where('provider', $provider)
            ->where('external_user_id', $adAccountId)
            ->first();

        if ($existing) {
            // same user → allow update (reconnect)
            if ($existing->user_id === $userId) {
                return [
                    'success' => true,
                    'integration' => $existing,
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

    public function sync(int $userId, string $provider, array $validated): array
    {
        $integration = $this->findActiveIntegration($userId, $provider);

        if (!$integration) {
            return [
                'success' => false,
                'error'   => ucfirst($provider) . ' integration not found or not connected.',
                'code'    => 404,
            ];
        }

        $dispatched = match ($provider) {
            'facebook' => $this->dispatchFacebookSync($integration, $validated),
            default    => false,
        };

        if (!$dispatched) {
            return [
                'success' => false,
                'error'   => "Provider [{$provider}] sync not supported.",
                'code'    => 422,
            ];
        }

        return [
            'success'   => true,
            'provider'  => $provider,
            'level'     => $validated['level'],
            'fields'    => $validated['fields'] ?? $this->getDefaultFields(),
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
            fields: $validated['fields'] ?? $this->getDefaultFields(),
            dateStart: $validated['date_from'],
            dateStop: $validated['date_to'],
        );

        return true;
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    public function findActiveIntegration(int $userId, string $providerSlug): ?Integration
    {
        return Integration::where('user_id', $userId)
            ->whereHas('provider', fn($q) => $q->where('slug', $providerSlug))
            ->where('status', 'active')
            ->first();
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
