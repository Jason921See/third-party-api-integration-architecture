<?php

namespace App\Jobs\Tenant;

use App\Integrations\Tenant\ExternalService\Facebook\Contracts\SyncableIntegrationJob;
use App\Integrations\Tenant\ExternalService\Facebook\FacebookClient;
use App\Integrations\Tenant\ExternalService\Facebook\FacebookService;
use App\Jobs\Concerns\Tenant\TrackIntegrationJob;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationAdCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
// use Stancl\Tenancy\Concerns\UsableAsTenantJob;

class FetchFacebookCampaignsJob implements ShouldQueue, SyncableIntegrationJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TrackIntegrationJob;
    // use UsableAsTenantJob; // Serializes tenant ID + re-initializes tenant DB on queue worker pickup

    public int   $tries   = 3;
    public int   $timeout = 120;
    public array $backoff  = [60, 300, 900];

    protected string $provider = 'facebook';
    protected string $jobType  = 'campaign';

    private const FIELDS = [
        'id',
        'name',
        'objective',
        'account_id',
        'buying_type',
        'daily_budget',
        'lifetime_budget',
        'spend_cap',
        'bid_strategy',
        'pacing_type',
        'status',
        'effective_status',
        'promoted_object',
        'recommendations',
        'start_time',
        'stop_time',
        'created_time',
        'updated_time',
        'adlabels',
        'issues_info',
        'special_ad_categories',
        'special_ad_category_country',
        'smart_promotion_type',
        'is_skadnetwork_attribution',
    ];

    public function __construct(
        public readonly int $integrationId,
    ) {}

    public function handle(FacebookService $facebookService): void
    {
        $this->startTracking();

        $integration = Integration::findOrFail($this->integrationId);

        Log::info('FetchFacebookCampaignsJob started', [
            'integration_id' => $this->integrationId,
            'ad_account_id'  => $integration->external_user_id,
        ]);

        // ─────────────────────────────
        // Token refresh
        // ─────────────────────────────
        if ($integration->isTokenExpired()) {
            $refreshed = $facebookService->refreshToken($integration);

            if (!$refreshed['success']) {
                $this->failTracking('Token refresh failed: ' . $refreshed['error']);
                $this->fail('Token refresh failed: ' . $refreshed['error']);
                return;
            }

            $integration->refresh();
        }

        $client      = new FacebookClient($integration);
        $adAccountId = $integration->external_user_id;

        $totalSynced = 0;

        $params = [
            'fields' => implode(',', self::FIELDS),
            'limit'  => 500,
        ];

        // ─────────────────────────────
        // FIRST REQUEST
        // ─────────────────────────────
        $response = $client->get("{$adAccountId}/campaigns", $params);

        if (!$response->success) {
            $this->failTracking($response->errorMessage);
            throw new \Exception("Facebook campaigns fetch failed: {$response->errorMessage}");
        }

        // ─────────────────────────────
        // PROCESS FIRST PAGE (CHUNKED)
        // ─────────────────────────────
        $totalSynced += $this->processChunk($integration->id, $response->data['data'] ?? []);

        $after = $response->data['paging']['cursors']['after'] ?? null;

        // ─────────────────────────────
        // PAGINATION LOOP (SAFE + RATE LIMIT FRIENDLY)
        // ─────────────────────────────
        while ($after) {

            // 🧠 prevent rate limit spikes
            usleep(300000); // 0.3 sec delay (adjust as needed)

            $next = $client->get(
                "{$adAccountId}/campaigns",
                array_merge($params, ['after' => $after])
            );

            if (!$next->success) {
                Log::warning('Pagination failed', [
                    'integration_id' => $this->integrationId,
                    'error'          => $next->errorMessage,
                ]);
                break;
            }

            $pageData = $next->data['data'] ?? [];

            if (empty($pageData)) {
                break;
            }

            // ─────────────────────────────
            // PROCESS CHUNK
            // ─────────────────────────────
            $totalSynced += $this->processChunk($integration->id, $pageData);

            $after = $next->data['paging']['cursors']['after'] ?? null;

            // optional safety break (avoid infinite loop)
            if (count($pageData) < 1) {
                break;
            }
        }

        $this->completeTracking($totalSynced);

        Log::info('FetchFacebookCampaignsJob completed', [
            'integration_id' => $this->integrationId,
            'total_synced'   => $totalSynced,
        ]);
    }

    private function upsertCampaign(int $integrationId, array $campaign): void
    {
        IntegrationAdCampaign::updateOrCreate(
            [
                'integration_id' => $integrationId,
                'campaign_id'    => $campaign['id'],
            ],
            [
                'name'                        => $campaign['name'] ?? null,
                'objective'                   => $campaign['objective'] ?? null,
                'account_id'                  => $campaign['account_id'] ?? null,
                'buying_type'                 => $campaign['buying_type'] ?? null,
                'daily_budget'                => $campaign['daily_budget'] ?? null,
                'lifetime_budget'             => $campaign['lifetime_budget'] ?? null,
                'spend_cap'                   => $campaign['spend_cap'] ?? null,
                'bid_strategy'                => $campaign['bid_strategy'] ?? null,
                'pacing_type'                 => $campaign['pacing_type'] ?? null,
                'status'                      => $campaign['status'] ?? null,
                'effective_status'            => $campaign['effective_status'] ?? null,
                'promoted_object'             => $campaign['promoted_object'] ?? null,
                'recommendations'             => $campaign['recommendations'] ?? null,
                'issues_info'                 => $campaign['issues_info'] ?? null,
                'adlabels'                    => $campaign['adlabels'] ?? null,
                'special_ad_categories'       => $campaign['special_ad_categories'] ?? [],
                'special_ad_category_country' => $campaign['special_ad_category_country'] ?? null,
                'smart_promotion_type'        => $campaign['smart_promotion_type'] ?? null,
                'is_skadnetwork_attribution'  => $campaign['is_skadnetwork_attribution'] ?? false,
                'fb_start_time'               => $campaign['start_time'] ?? null,
                'fb_stop_time'                => $campaign['stop_time'] ?? null,
                'fb_created_time'             => $campaign['created_time'] ?? null,
                'fb_updated_time'             => $campaign['updated_time'] ?? null,
                'raw'                         => $campaign,
                'synced_at'                   => now(),
            ],
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('FetchFacebookCampaignsJob permanently failed', [
            'integration_id' => $this->integrationId,
            'error'          => $exception->getMessage(),
        ]);

        $this->failTracking($exception->getMessage());
    }

    private function processChunk(int $integrationId, array $campaigns): int
    {
        $count = 0;

        foreach ($campaigns as $campaign) {
            $this->upsertCampaign($integrationId, $campaign);
            $count++;

            // for each 100 records, pause briefly to avoid hitting rate limits
            if ($count % 100 === 0) {
                usleep(100000); // micro pause
            }
        }

        // update progress tracking for UI
        $this->integrationJob?->update([
            'records_synced' => $this->integrationJob->records_synced + $count,
        ]);

        return $count;
    }
}
