<?php

namespace App\Jobs\Tenant;

use App\Integrations\Tenant\ExternalService\Facebook\FacebookClient;
use App\Integrations\Tenant\ExternalService\Facebook\FacebookService as FacebookIntegrationService;
use App\Jobs\Concerns\Tenant\TrackIntegrationJob;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationInsight;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class FetchFacebookInsightJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TrackIntegrationJob;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [60, 300, 900];

    public string $provider = 'facebook';
    public string $jobType = 'insight';

    private const RATE_LIMIT = 5; // requests per minute

    private const BASE_FIELDS = [
        'account_id',
        'account_name',
        'campaign_id',
        'campaign_name',
        'adset_id',
        'adset_name',
        'ad_id',
        'ad_name',

    ];

    private const EXTENDED_FIELDS = [
        'ctr',
        'cpp',
        'frequency',
        'actions',
        'action_values',
        'date_start',
        'date_stop',
        'impressions',
        'clicks',
        'reach',
        'spend',
        'cpc',
        'cpm',
    ];

    public function __construct(
        public readonly int $integrationId,
        public readonly string $level,
        public readonly string $adAccountId,
        public readonly ?string $dateStart = null,
        public readonly ?string $dateStop = null,
        public readonly string $datePreset = 'last_30d',
        public readonly ?array $fields = null,
    ) {}

    public function handle(FacebookIntegrationService $facebookService): void
    {
        $this->startTracking();

        $integration = Integration::findOrFail($this->integrationId);

        Log::info('FetchFacebookInsightJob started', [
            'integration_id' => $this->integrationId,
            'level' => $this->level,
            'ad_account_id' => $this->adAccountId,
        ]);

        // ── Token refresh ─────────────────────────────
        if ($integration->isTokenExpired()) {
            $refreshed = $facebookService->refreshToken($integration);

            if (!$refreshed['success']) {
                $this->failTracking('Token refresh failed: ' . $refreshed['error']);
                $this->fail('Token refresh failed');
                return;
            }

            $integration->refresh();
        }

        $client = new FacebookClient($integration);

        $params = [
            'level' => $this->level,
            'fields'         => implode(',', $this->resolveFields()),
            'time_increment' => '1',
            'limit' => 500,
        ];
        Log::info(json_encode($params));
        if ($this->dateStart && $this->dateStop) {
            $params['time_range'] = json_encode([
                'since' => $this->dateStart,
                'until' => $this->dateStop,
            ]);
        } else {
            $params['date_preset'] = $this->datePreset;
        }

        $rateKey = "facebook-api-global";

        // ── FIRST REQUEST ─────────────────────────────
        $this->applyRateLimit($rateKey);

        $response = $client->get("{$this->adAccountId}/insights", $params);

        if (!$response->success) {
            $this->failTracking($response->errorMessage);
            throw new \Exception($response->errorMessage);
        }
        Log::info(json_encode($response->data['data'] ?? []));
        $totalSynced = $this->processInsights($integration, $response->data['data'] ?? []);

        // ── PAGINATION ────────────────────────────────
        $after = $response->data['paging']['cursors']['after'] ?? null;

        while ($after) {

            $this->applyRateLimit($rateKey);

            $next = $client->get("{$this->adAccountId}/insights", array_merge($params, [
                'after' => $after,
            ]));

            if (!$next->success) {
                Log::warning('Pagination failed', [
                    'error' => $next->errorMessage,
                ]);
                break;
            }

            $page = $next->data['data'] ?? [];

            $totalSynced += $this->processInsights($integration, $page);

            $after = !empty($page)
                ? ($next->data['paging']['cursors']['after'] ?? null)
                : null;
        }

        $this->completeTracking($totalSynced);

        Log::info('FetchFacebookInsightJob completed', [
            'total_synced' => $totalSynced,
        ]);
    }

    // ─────────────────────────────────────────────
    // RATE LIMITER
    // ─────────────────────────────────────────────
    private function applyRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT)) {
            $this->release(RateLimiter::availableIn($key));

            Log::warning('Rate limited, releasing job', [
                'key' => $key,
            ]);

            return;
        }

        RateLimiter::hit($key, 60);
    }

    // ─────────────────────────────────────────────
    // PROCESS DATA
    // ─────────────────────────────────────────────
    private function processInsights($integration, array $insights): int
    {
        $count = 0;

        foreach ($insights as $insight) {
            $this->upsertInsight($integration->id, $insight);
            $count++;
        }

        return $count;
    }

    // ─────────────────────────────────────────────
    // UPSERT
    // ─────────────────────────────────────────────
    private function upsertInsight(int $integrationId, array $insight): void
    {
        IntegrationInsight::updateOrCreate(
            [
                'integration_id' => $integrationId,
                'date_start' => $insight['date_start'],
                'date_stop' => $insight['date_stop'],
                'account_id'     => $insight['account_id'] ?? null,
                'ad_id'          => $insight['ad_id'] ?? null,
                'adset_id'       => $insight['adset_id'] ?? null,
                'campaign_id'    => $insight['campaign_id'] ?? null,
            ],
            [
                'level'     => $this->level,
                'object_name' => 'account',
                'account_id' => $insight['account_id'] ?? null,

                'campaign_id' => $insight['campaign_id'] ?? null,

                'adset_id' => $insight['adset_id'] ?? null,

                'ad_id' => $insight['ad_id'] ?? null,

                'impressions' => $insight['impressions'] ?? 0,
                'clicks' => $insight['clicks'] ?? 0,
                'reach' => $insight['reach'] ?? 0,
                'spend' => $insight['spend'] ?? 0,
                'cpc' => $insight['cpc'] ?? 0,
                'cpm' => $insight['cpm'] ?? 0,
                'ctr' => $insight['ctr'] ?? 0,
                'cpp' => $insight['cpp'] ?? 0,
                'frequency' => $insight['frequency'] ?? 0,

                'actions' => $insight['actions'] ?? null,
                'action_values' => $insight['action_values'] ?? null,

                'raw' => $insight,
                'fetched_at' => now(),
            ],
        );
    }

    // ─────────────────────────────────────────────
    // DEFAULT FIELDS
    // ─────────────────────────────────────────────
    private function defaultFields(): array
    {
        return self::BASE_FIELDS;
    }

    // ─────────────────────────────────────────────
    // MERGE BASE AND EXTENDED FIELDS
    // ─────────────────────────────────────────────

    private function resolveFields(): array
    {
        if ($this->fields === null) {
            return self::BASE_FIELDS;
        }

        // User-provided fields: only allow known extended metrics to be added
        $extras = array_intersect($this->fields, self::EXTENDED_FIELDS);

        return array_unique(array_merge(self::BASE_FIELDS, $extras));
    }


    // ─────────────────────────────────────────────
    // FAIL HANDLER
    // ─────────────────────────────────────────────
    public function failed(\Throwable $exception): void
    {
        $this->failTracking($exception->getMessage());

        Log::error('FetchFacebookInsightJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
