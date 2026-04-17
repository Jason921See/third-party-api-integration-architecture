<?php

namespace Tests\Unit\Tenant\Integration;

use App\Jobs\Tenant\FetchFacebookInsightJob;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationProvider;
use App\Models\Tenant\User;
use App\Services\Tenant\IntegrationService;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private IntegrationService $service;
    private User $user;
    private IntegrationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        if (!static::$tenantSeeded) {
            $this->seed(TenantDatabaseSeeder::class);
            static::$tenantSeeded = true;
        }

        $this->service  = app(IntegrationService::class);
        $this->user     = User::factory()->create();
        $this->provider = IntegrationProvider::where('slug', 'facebook')->firstOrFail();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function activeIntegration(array $overrides = []): Integration
    {
        return Integration::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'ip_id'   => $this->provider->id,
            'status'  => 'active',
        ], $overrides));
    }

    private function syncPayload(array $overrides = []): array
    {
        return array_merge([
            'provider'  => 'facebook',
            'level'     => 'ad',
            'fields'    => ['impressions', 'clicks'],
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ], $overrides);
    }

    // ── normalizeAdAccountId() ────────────────────────────────────────────────

    #[Test]
    public function normalize_prepends_act_prefix_when_missing(): void
    {
        $this->assertSame('act_123456', $this->service->normalizeAdAccountId('123456'));
    }

    #[Test]
    public function normalize_keeps_act_prefix_when_already_present(): void
    {
        $this->assertSame('act_123456', $this->service->normalizeAdAccountId('act_123456'));
    }

    #[Test]
    public function normalize_trims_whitespace(): void
    {
        $this->assertSame('act_123456', $this->service->normalizeAdAccountId('  123456  '));
    }

    #[Test]
    public function normalize_trims_whitespace_and_keeps_prefix(): void
    {
        $this->assertSame('act_123456', $this->service->normalizeAdAccountId('  act_123456  '));
    }

    // ── getDefaultFields() ────────────────────────────────────────────────────

    #[Test]
    public function get_default_fields_returns_expected_fields(): void
    {
        $fields = $this->service->getDefaultFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        foreach (['impressions', 'clicks', 'reach', 'spend', 'cpc', 'cpm', 'ctr'] as $expected) {
            $this->assertContains($expected, $fields, "Missing expected field: {$expected}");
        }
    }

    // ── findActiveIntegration() ───────────────────────────────────────────────

    #[Test]
    public function find_active_integration_returns_collection_of_active_integrations(): void
    {
        $this->activeIntegration(['external_user_id' => 'act_111']);
        $this->activeIntegration(['external_user_id' => 'act_222']);

        $result = $this->service->findActiveIntegration($this->user->id, 'facebook');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(2, $result->count());
        $result->each(fn($i) => $this->assertSame('active', $i->status));
    }

    #[Test]
    public function find_active_integration_excludes_inactive_integrations(): void
    {
        $this->activeIntegration(['status' => 'inactive', 'external_user_id' => 'act_999']);

        $result = $this->service->findActiveIntegration($this->user->id, 'facebook');

        $this->assertTrue(
            $result->where('external_user_id', 'act_999')->isEmpty()
        );
    }

    #[Test]
    public function find_active_integration_returns_empty_collection_for_unknown_provider(): void
    {
        $result = $this->service->findActiveIntegration($this->user->id, 'nonexistent_provider');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function find_active_integration_returns_empty_collection_for_different_user(): void
    {
        $otherUser = User::factory()->create();
        $this->activeIntegration(['user_id' => $otherUser->id]);

        $result = $this->service->findActiveIntegration($this->user->id, 'facebook');

        $this->assertTrue(
            $result->where('user_id', $otherUser->id)->isEmpty()
        );
    }

    // ── connect() ─────────────────────────────────────────────────────────────

    #[Test]
    public function connect_returns_conflict_when_ad_account_belongs_to_another_user(): void
    {
        $otherUser = User::factory()->create();

        $this->activeIntegration([
            'user_id'          => $otherUser->id,
            'external_user_id' => 'act_duplicate',
        ]);

        $result = $this->service->connect(
            userId: $this->user->id,
            provider: 'facebook',
            accessToken: 'token_abc',
            adAccountId: 'act_duplicate',
        );

        $this->assertFalse($result['success']);
        $this->assertSame(409, $result['code']);
        $this->assertStringContainsString('another user', $result['error']);
    }

    #[Test]
    public function connect_allows_reconnect_for_same_user(): void
    {
        $existing = $this->activeIntegration(['external_user_id' => 'act_reconnect']);

        $result = $this->service->connect(
            userId: $this->user->id,
            provider: 'facebook',
            accessToken: 'new_token',
            adAccountId: 'act_reconnect',
        );

        $this->assertTrue($result['success']);
        $this->assertSame($existing->id, $result['data']['integration']->id);
    }

    #[Test]
    public function connect_returns_error_for_unsupported_provider(): void
    {
        $result = $this->service->connect(
            userId: $this->user->id,
            provider: 'tiktok',
            accessToken: 'token',
            adAccountId: 'act_123',
        );

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['code']);
        $this->assertStringContainsString('tiktok', $result['error']);
    }

    #[Test]
    public function connect_normalizes_ad_account_id_before_duplicate_check(): void
    {
        $otherUser = User::factory()->create();

        // Stored without prefix
        $this->activeIntegration([
            'user_id'          => $otherUser->id,
            'external_user_id' => 'act_777',
        ]);

        // Passed in without prefix — should still detect the conflict after normalization
        $result = $this->service->connect(
            userId: $this->user->id,
            provider: 'facebook',
            accessToken: 'token',
            adAccountId: '777',      // no act_ prefix
        );

        $this->assertFalse($result['success']);
        $this->assertSame(409, $result['code']);
    }

    // ── sync() ────────────────────────────────────────────────────────────────

    #[Test]
    public function sync_dispatches_job_for_each_facebook_integration(): void
    {
        Queue::fake();

        $integrations = new Collection([
            $this->activeIntegration(['external_user_id' => 'act_aaa']),
            $this->activeIntegration(['external_user_id' => 'act_bbb']),
        ]);

        $result = $this->service->sync($integrations, $this->syncPayload());

        $this->assertTrue($result['success']);
        Queue::assertPushed(FetchFacebookInsightJob::class, 2);
    }

    #[Test]
    public function sync_dispatches_job_with_correct_parameters(): void
    {
        Queue::fake();

        $integration = $this->activeIntegration(['external_user_id' => 'act_check']);
        $payload     = $this->syncPayload([
            'level'     => 'campaign',
            'fields'    => ['spend', 'reach'],
            'date_from' => '2024-03-01',
            'date_to'   => '2024-03-31',
        ]);

        $this->service->sync(new Collection([$integration]), $payload);

        Queue::assertPushed(FetchFacebookInsightJob::class, function (FetchFacebookInsightJob $job) use ($integration, $payload) {
            return $job->integrationId === $integration->id
                && $job->adAccountId   === 'act_check'
                && $job->level         === $payload['level']
                && $job->dateStart     === $payload['date_from']
                && $job->dateStop      === $payload['date_to'];
        });
    }

    #[Test]
    public function sync_returns_error_when_collection_is_empty(): void
    {
        Queue::fake();

        $result = $this->service->sync(new Collection(), $this->syncPayload());

        $this->assertFalse($result['success']);
        $this->assertSame(404, $result['code']);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function sync_returns_expected_response_shape(): void
    {
        Queue::fake();

        $integration = $this->activeIntegration();
        $payload     = $this->syncPayload();

        $result = $this->service->sync(new Collection([$integration]), $payload);

        $this->assertTrue($result['success']);
        $this->assertSame($payload['provider'],  $result['provider']);
        $this->assertSame($payload['level'],     $result['level']);
        $this->assertSame($payload['date_from'], $result['date_from']);
        $this->assertSame($payload['date_to'],   $result['date_to']);
    }

    #[Test]
    public function sync_does_not_dispatch_job_for_unsupported_provider(): void
    {
        Queue::fake();

        // Simulate an integration whose provider slug is not 'facebook'
        $unsupportedProvider = IntegrationProvider::factory()->create(['slug' => 'twitter_x']);
        $integration = Integration::factory()->create([
            'user_id' => $this->user->id,
            'ip_id'   => $unsupportedProvider->id,
            'status'  => 'active',
        ]);

        $this->service->sync(new Collection([$integration]), $this->syncPayload(['provider' => 'twitter_x']));

        Queue::assertNothingPushed();
    }
}
