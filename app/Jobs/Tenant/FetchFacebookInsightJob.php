<?php

namespace App\Jobs\Tenant;

use App\Integrations\Facebook\FacebookClient;
use App\Integrations\Facebook\FacebookService as FacebookIntegrationService;
use App\Jobs\Concerns\Tenant\TrackIntegrationJob;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationInsight;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchFacebookInsightJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TrackIntegrationJob;

    public int   $tries   = 3;
    public int   $timeout = 120;
    public array $backoff  = [60, 300, 900];

    // Required by TracksIntegrationJob
    public string $provider = 'facebook';
    public string $jobType  = 'insight';

    private const FIELDS_ACCOUNT = [
        'account_id',
        'account_name',
        'account_currency',
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
        'date_start',
        'date_stop',
    ];

    private const FIELDS_CAMPAIGN = [
        'campaign_id',
        'campaign_name',
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
        'date_start',
        'date_stop',
    ];

    private const FIELDS_ADSET = [
        'campaign_id',
        'campaign_name',
        'adset_id',
        'adset_name',
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
        'date_start',
        'date_stop',
    ];

    private const FIELDS_AD = [
        'campaign_id',
        'campaign_name',
        'adset_id',
        'adset_name',
        'ad_id',
        'ad_name',
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
        'date_start',
        'date_stop',
    ];

    public function __construct(
        public readonly int     $integrationId,
        public readonly string  $level,
        public readonly string  $adAccountId,
        public readonly ?string $dateStart  = null,
        public readonly ?string $dateStop   = null,
        public readonly string  $datePreset = 'last_30d',
        public readonly ?array  $fields     = null,
    ) {}

    public function handle(FacebookIntegrationService $facebookService): void
    {
        // ── Start tracking ────────────────────────────────────
        $this->startTracking();

        $integration = Integration::findOrFail($this->integrationId);

        Log::info('FetchFacebookInsightJob started', [
            'integration_id' => $this->integrationId,
            'level'          => $this->level,
            'ad_account_id'  => $this->adAccountId,
        ]);

        // ── Token refresh ─────────────────────────────────────
        if ($integration->isTokenExpired()) {
            $refreshed = $facebookService->refreshToken($integration);

            if (!$refreshed['success']) {
                $this->failTracking('Token refresh failed: ' . $refreshed['error']);
                $this->fail('Token refresh failed: ' . $refreshed['error']);
                return;
            }

            $integration->refresh();
        }

        $client = new FacebookClient($integration);

        $params = [
            'level'          => $this->level,
            'fields'         => implode(',', $this->fields ?? $this->defaultFields()),
            'time_increment' => 'all_days',
            'limit'          => 500,
        ];

        if ($this->dateStart && $this->dateStop) {
            $params['time_range'] = json_encode([
                'since' => $this->dateStart,
                'until' => $this->dateStop,
            ]);
        } else {
            $params['date_preset'] = $this->datePreset;
        }

        // ── Fetch ─────────────────────────────────────────────
        $response = $client->get("{$this->adAccountId}/insights", $params);

        if (!$response->success) {
            $this->failTracking($response->errorMessage, [
                'error_code' => $response->errorCode,
                'level'      => $this->level,
            ]);
            throw new \Exception("Facebook insight fetch failed: {$response->errorMessage}");
        }

        $insights    = $response->data['data'] ?? [];
        $totalSynced = 0;

        if (empty($insights)) {
            Log::info('FetchFacebookInsightJob no data returned', [
                'integration_id' => $this->integrationId,
                'level'          => $this->level,
            ]);
            $this->completeTracking(0);
            return;
        }

        foreach ($insights as $insight) {
            $this->upsertInsight($integration->id, $insight);
            $totalSynced++;
        }

        // ── Paginate ──────────────────────────────────────────
        $after = $response->data['paging']['cursors']['after'] ?? null;

        while ($after) {
            $next = $client->get("{$this->adAccountId}/insights", array_merge($params, [
                'after' => $after,
            ]));

            if (!$next->success) {
                Log::warning('FetchFacebookInsightJob pagination failed', [
                    'integration_id' => $this->integrationId,
                    'error'          => $next->errorMessage,
                ]);
                break;
            }

            $page = $next->data['data'] ?? [];

            foreach ($page as $insight) {
                $this->upsertInsight($integration->id, $insight);
                $totalSynced++;
            }

            $after = !empty($page)
                ? ($next->data['paging']['cursors']['after'] ?? null)
                : null;
        }

        // ── Complete tracking ─────────────────────────────────
        $this->completeTracking($totalSynced);

        Log::info('FetchFacebookInsightJob completed', [
            'integration_id' => $this->integrationId,
            'level'          => $this->level,
            'total_synced'   => $totalSynced,
        ]);
    }

    // ─────────────────────────────────────────────
    // Upsert
    // ─────────────────────────────────────────────

    private function upsertInsight(int $integrationId, array $insight): void
    {
        $objectId = match ($this->level) {
            'ad'       => $insight['ad_id'] ?? null,
            'adset'    => $insight['adset_id'] ?? null,
            'campaign' => $insight['campaign_id'] ?? null,
            default    => $insight['account_id'] ?? null,
        };

        IntegrationInsight::updateOrCreate(
            [
                'integration_id' => $integrationId,
                'level'          => $this->level,
                'date_start'     => $insight['date_start'],
                'date_stop'      => $insight['date_stop'],
            ],
            [
                'object_name'      => $this->resolveObjectName($insight),
                'parent_object_id' => $this->resolveParentId($insight),
                'account_currency' => $insight['account_currency'] ?? null,
                'impressions'      => $insight['impressions'] ?? 0,
                'clicks'           => $insight['clicks'] ?? 0,
                'reach'            => $insight['reach'] ?? 0,
                'spend'            => $insight['spend'] ?? 0,
                'cpc'              => $insight['cpc'] ?? 0,
                'cpm'              => $insight['cpm'] ?? 0,
                'ctr'              => $insight['ctr'] ?? 0,
                'cpp'              => $insight['cpp'] ?? 0,
                'frequency'        => $insight['frequency'] ?? 0,
                'actions'          => $insight['actions'] ?? null,
                'action_values'    => $insight['action_values'] ?? null,
                'raw'              => $insight,
                'fetched_at'       => now(),
            ],
        );
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function defaultFields(): array
    {
        return match ($this->level) {
            'ad'       => self::FIELDS_AD,
            'adset'    => self::FIELDS_ADSET,
            'campaign' => self::FIELDS_CAMPAIGN,
            default    => self::FIELDS_ACCOUNT,
        };
    }

    private function resolveObjectName(array $insight): ?string
    {
        return match ($this->level) {
            'ad'       => $insight['ad_name'] ?? null,
            'adset'    => $insight['adset_name'] ?? null,
            'campaign' => $insight['campaign_name'] ?? null,
            default    => $insight['account_name'] ?? null,
        };
    }

    private function resolveParentId(array $insight): ?string
    {
        return match ($this->level) {
            'ad'    => $insight['adset_id'] ?? null,
            'adset' => $insight['campaign_id'] ?? null,
            default => null,
        };
    }

    // ─────────────────────────────────────────────
    // Permanent failure
    // ─────────────────────────────────────────────

    public function failed(\Throwable $exception): void
    {
        $this->failTracking($exception->getMessage(), [
            'level'         => $this->level,
            'ad_account_id' => $this->adAccountId,
        ]);

        Log::error('FetchFacebookInsightJob permanently failed', [
            'integration_id' => $this->integrationId,
            'level'          => $this->level,
            'error'          => $exception->getMessage(),
        ]);
    }
}
