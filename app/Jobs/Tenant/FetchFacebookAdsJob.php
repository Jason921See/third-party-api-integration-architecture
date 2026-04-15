<?php

namespace App\Jobs\Tenant;

use App\Integrations\Facebook\Contracts\SyncableIntegrationJob;
use App\Integrations\Facebook\FacebookClient;
use App\Integrations\Facebook\FacebookService;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationAd;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchFacebookAdsJob implements ShouldQueue, SyncableIntegrationJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries   = 3;
    public int   $timeout = 120;
    public array $backoff  = [60, 300, 900];

    private const FIELDS = [
        'id',
        'name',
        'status',
        'effective_status',
        'adset_id',
        'campaign_id',
        'creative',
        'tracking_specs',
        'conversion_specs',
        'created_time',
        'updated_time',
    ];

    public function __construct(
        public readonly int $integrationId,
    ) {}

    public function handle(FacebookService $facebookService): void
    {
        $integration  = Integration::findOrFail($this->integrationId);
        $adAccountId  = $integration->external_user_id;

        Log::info('FetchFacebookAdsJob started', [
            'integration_id' => $this->integrationId,
            'ad_account_id'  => $adAccountId,
        ]);

        if ($integration->isTokenExpired()) {
            $refreshed = $facebookService->refreshToken($integration);

            if (!$refreshed['success']) {
                $this->fail('Token refresh failed: ' . $refreshed['error']);
                return;
            }

            $integration->refresh();
        }

        $client = new FacebookClient($integration);
        $params = [
            'fields' => implode(',', self::FIELDS),
            'limit'  => 500,
        ];

        $totalSynced = 0;
        $response    = $client->get("{$adAccountId}/ads", $params);

        if (!$response->success) {
            throw new \Exception("Facebook ads fetch failed: {$response->errorMessage}");
        }

        foreach ($response->data['data'] ?? [] as $ad) {
            $this->upsertAd($integration->id, $ad);
            $totalSynced++;
        }

        $after = $response->data['paging']['cursors']['after'] ?? null;

        while ($after) {
            $next = $client->get("{$adAccountId}/ads", array_merge($params, ['after' => $after]));

            if (!$next->success) break;

            $page = $next->data['data'] ?? [];

            foreach ($page as $ad) {
                $this->upsertAd($integration->id, $ad);
                $totalSynced++;
            }

            $after = !empty($page) ? ($next->data['paging']['cursors']['after'] ?? null) : null;
        }

        Log::info('FetchFacebookAdsJob completed', [
            'integration_id' => $this->integrationId,
            'total_synced'   => $totalSynced,
        ]);
    }

    private function upsertAd(int $integrationId, array $ad): void
    {
        IntegrationAd::updateOrCreate(
            [
                'integration_id' => $integrationId,
                'ad_id'          => $ad['id'],
            ],
            [
                'name'             => $ad['name'] ?? null,
                'status'           => $ad['status'] ?? null,
                'effective_status' => $ad['effective_status'] ?? null,
                'adset_id'         => $ad['adset_id'] ?? null,
                'campaign_id'      => $ad['campaign_id'] ?? null,
                'creative'         => $ad['creative'] ?? null,
                'tracking_specs'   => $ad['tracking_specs'] ?? null,
                'conversion_specs' => $ad['conversion_specs'] ?? null,
                'fb_created_time'  => $ad['created_time'] ?? null,
                'fb_updated_time'  => $ad['updated_time'] ?? null,
                'raw'              => $ad,
                'synced_at'        => now(),
            ],
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('FetchFacebookAdsJob permanently failed', [
            'integration_id' => $this->integrationId,
            'error'          => $exception->getMessage(),
        ]);
    }
}
