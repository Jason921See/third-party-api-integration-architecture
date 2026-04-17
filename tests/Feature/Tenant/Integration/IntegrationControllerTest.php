<?php

namespace Tests\Feature\Tenant\Integration;

use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationProvider;
use App\Models\Tenant\User;
use App\Services\Tenant\IntegrationService;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationControllerTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TenantDatabaseSeeder::class);
        $this->user = User::factory()->create();
    }

    protected function authLogin(User $user = null): void
    {
        $user = $user ?? $this->user;

        $this->actingAs($user, 'sanctum');
    }

    // ══════════════════════════════════════════════════════════
    // POST /api/integrations/connect
    // ══════════════════════════════════════════════════════════

    protected function tenantPostJson(string $uri, array $data = [])
    {
        return $this->withServerVariables([
            'HTTP_HOST' => 'tenant.central.test',
        ])->postJson("http://tenant.central.test{$uri}", $data);
    }

    protected function tenantGetJson(string $uri, array $data = [])
    {
        return $this->withServerVariables([
            'HTTP_HOST' => 'tenant.central.test',
        ])->getJson("http://tenant.central.test{$uri}", $data);
    }

    #[Test]
    public function connect_returns_success_with_valid_payload(): void
    {
        $this->mock(IntegrationService::class)
            ->shouldReceive('connect')
            ->once()
            ->with(
                $this->user->id,
                'facebook',
                'valid-token-123',
                'act_123456789',
            )
            ->andReturn([
                'success'     => true,
                'integration' => new Integration([
                    'id' => 1,
                    'provider_id' => 1,
                    'user_id' => $this->user->id,
                    'external_user_id' => 'act_123',
                    'status' => 'active',
                ]),
            ]);

        $this->authLogin();
        $response = $this->tenantPostJson('/api/integrations/connect', [
            'provider'    => 'facebook',
            'credentials' => [
                'access_token'  => 'valid-token-123',
                'ad_account_id' => 'act_123456789',
            ],
        ]);

        // dump([
        //     'status' => $response->status(),
        //     'content' => $response->getContent(),
        // ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'integration' => [
                        'id',
                        'provider_id',
                        'user_id',
                        'external_user_id',
                        'status',
                        'created_at',
                    ],
                ],
                'error',
            ]);
    }

    #[Test]
    public function connect_fails_validation_when_provider_is_missing(): void
    {
        $this->authLogin();
        $response = $this->tenantPostJson('/api/integrations/connect', [
            'credentials' => [
                'access_token'  => 'token',
                'ad_account_id' => 'act_123',
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider']);
    }

    #[Test]
    public function connect_fails_validation_when_credentials_are_missing(): void
    {
        $this->authLogin();
        $response = $this->tenantPostJson('/api/integrations/connect', [
            'provider' => 'facebook',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['credentials.access_token', 'credentials.ad_account_id']);
    }

    #[Test]
    public function connect_requires_authentication(): void
    {
        $response = $this->tenantPostJson('/api/integrations/connect', [
            'provider' => 'facebook',
            'credentials' => [
                'access_token' => 'token',
                'ad_account_id' => 'act_123',
            ],
        ]);

        $response->assertUnauthorized();
    }

    // // ══════════════════════════════════════════════════════════
    // // POST /api/integrations/sync
    // // ══════════════════════════════════════════════════════════

    #[Test]
    public function sync_queues_job_for_active_integration(): void
    {
        Queue::fake();

        $integration = Integration::factory()->create(['user_id' => $this->user->id]);

        $serviceMock = $this->mock(IntegrationService::class);

        $serviceMock->shouldReceive('findActiveIntegration')
            ->once()
            ->with($this->user->id, 'facebook')
            ->andReturn(collect([$integration]));

        $serviceMock->shouldReceive('sync')
            ->once()
            ->andReturn([
                'success'   => true,
                'provider'  => 'facebook',
                'level'     => 'campaign',
                'fields'    => ['impressions', 'clicks'],
                'date_from' => '2024-01-01',
                'date_to'   => '2024-01-31',
            ]);

        $this->authLogin();
        $response = $this->tenantPostJson('/api/integrations/sync', [
            'provider'  => 'facebook',
            'level'     => 'campaign',
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sync job queued successfully.')
            ->assertJsonPath('data.provider', 'facebook')
            ->assertJsonPath('data.level', 'campaign');
    }

    // #[Test]
    public function sync_returns_404_when_no_active_integration_found(): void
    {
        $this->mock(IntegrationService::class)
            ->shouldReceive('findActiveIntegration')
            ->once()
            ->andReturn(collect([]));

        $this->authLogin();
        $response = $this->tenantPostJson('/api/integrations/sync', [
            'provider'  => 'facebook',
            'level'     => 'campaign',
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ]);

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No active integrations found for this provider.');
    }

    // #[Test]
    // public function sync_returns_error_when_service_fails(): void
    // {
    //     $integration = Integration::factory()->create(['user_id' => $this->user->id]);

    //     $serviceMock = $this->mock(IntegrationService::class);

    //     $serviceMock->shouldReceive('findActiveIntegration')
    //         ->andReturn(collect([$integration]));

    //     $serviceMock->shouldReceive('sync')
    //         ->andReturn([
    //             'success' => false,
    //             'error'   => 'Sync dispatch failed.',
    //             'code'    => 500,
    //         ]);

    //     $this->authLogin();
    //     $response = $this->tenantPostJson('/api/integrations/sync', [
    //         'provider'  => 'facebook',
    //         'level'     => 'campaign',
    //         'date_from' => '2024-01-01',
    //         'date_to'   => '2024-01-31',
    //     ]);

    //     $response->assertStatus(500)
    //         ->assertJsonPath('success', false);
    // }

    #[Test]
    public function sync_enforces_authorization_policy(): void
    {
        $otherUser   = User::factory()->create();
        $integration = Integration::factory()->create(['user_id' => $otherUser->id]); // belongs to someone else

        $this->mock(IntegrationService::class)
            ->shouldReceive('findActiveIntegration')
            ->andReturn(collect([$integration]));

        $this->authLogin();
        $response = $this->tenantPostJson('/api/integrations/sync', [
            'provider'  => 'facebook',
            'level'     => 'campaign',
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ]);

        $response->assertForbidden();
    }

    // // ══════════════════════════════════════════════════════════
    // // GET /api/integrations/status
    // // ══════════════════════════════════════════════════════════

    #[Test]
    public function status_returns_paginated_integrations_for_authenticated_user(): void
    {
        Integration::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $this->authLogin();

        $response = $this->tenantGetJson('/api/integrations/status');
        dump([
            'status' => $response->status(),
            'content' => $response->getContent(),
        ]);
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'provider',
                        'account_id',
                        'account_name',
                        'status',
                        'connected',
                        'token_expires_at',
                    ],
                ],
                'meta' => [
                    'page',
                    'size',
                    'total',
                    'total_pages',
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
            ]);
    }

    #[Test]
    public function status_filters_by_provider_slug(): void
    {
        Integration::factory()->create([
            'user_id' => $this->user->id,
            'ip_id' => 1
        ]);

        Integration::factory()->create([
            'user_id' => $this->user->id,
            'ip_id' => 2
        ]);

        $this->authLogin();

        $response = $this->tenantGetJson('/api/integrations/status?provider=facebook');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('facebook', $data[0]['provider']);
    }

    #[Test]
    public function status_respects_per_page_query_param(): void
    {
        Integration::factory()->count(15)->create([
            'user_id' => $this->user->id
        ]);

        $this->authLogin();

        $response = $this->tenantGetJson('/api/integrations/status?per_page=5');

        $response->assertOk()
            ->assertJsonPath('meta.total_pages', 3)
            ->assertJsonCount(5, 'data');
    }

    #[Test]
    public function status_excludes_integrations_user_cannot_view(): void
    {
        $otherUser = User::factory()->create();

        Integration::factory()->count(2)->create(['user_id' => $this->user->id]);
        Integration::factory()->count(3)->create(['user_id' => $otherUser->id]); // should be filtered out

        $this->authLogin();
        $response = $this->tenantGetJson('/api/integrations/status');

        $response->assertOk();

        // Only this user's integrations are present
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function status_returns_empty_data_when_no_integrations_exist(): void
    {
        $this->authLogin();
        $response = $this->tenantGetJson('/api/integrations/status');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }
}
