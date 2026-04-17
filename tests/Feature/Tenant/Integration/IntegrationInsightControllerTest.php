<?php

namespace Tests\Feature\Tenant\Integration;

use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationInsight;
use App\Models\Tenant\IntegrationProvider;
use App\Models\Tenant\User;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntegrationInsightControllerTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private IntegrationProvider $facebookProvider;
    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        if (!static::$tenantSeeded) {
            $this->seed(TenantDatabaseSeeder::class);
            static::$tenantSeeded = true;
        }

        $this->user = User::factory()->create();
        $this->facebookProvider = IntegrationProvider::where('slug', 'facebook')->firstOrFail();

        $this->integration = Integration::factory()->create([
            'user_id'     => $this->user->id,
            'ip_id' => $this->facebookProvider->id,
            'status'      => 'active',
        ]);
    }

    private function authLogin(): void
    {
        $this->actingAs($this->user, 'sanctum');
    }

    protected function tenantGetJson(string $uri, array $data = [])
    {
        return $this->withServerVariables([
            'HTTP_HOST' => 'tenant.central.test',
        ])->getJson("http://tenant.central.test{$uri}", $data);
    }
    // ══════════════════════════════════════════════════════════
    // GET /api/integrations/insights
    // ══════════════════════════════════════════════════════════

    #[Test]
    public function index_returns_paginated_insights_for_authenticated_user(): void
    {
        IntegrationInsight::factory()->count(5)->create([
            'integration_id' => $this->integration->id,
        ]);

        // Noise: another user's insights must NOT appear
        $otherUser = User::factory()->create();
        $otherIntegration = Integration::factory()->create([
            'user_id'     => $otherUser->id,
            'ip_id' => $this->facebookProvider->id,
            'status'      => 'active',
        ]);
        IntegrationInsight::factory()->count(3)->create([
            'integration_id' => $otherIntegration->id,
        ]);

        $this->authLogin();
        $response = $this->tenantGetJson('/api/insights');

        // dump([
        //     'status' => $response->status(),
        //     'content' => $response->getContent(),
        // ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    [
                        'id',
                        'integration_id',
                        'level',
                        'object_name',
                        'date_start',
                        'date_stop',
                        'metrics',
                    ],
                ],
                'error',
                'meta' => [
                    'page',
                    'size',
                    'total',
                    'total_pages',
                ],
                'links',
            ])
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.size', 20);
    }

    #[Test]
    public function index_returns_empty_when_user_has_no_active_integrations(): void
    {
        $this->integration->update(['status' => 'inactive']);

        IntegrationInsight::factory()->count(3)->create([
            'integration_id' => $this->integration->id,
        ]);

        $this->authLogin();
        $response = $this->tenantGetJson('/api/insights');

        $response->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function index_filters_by_provider(): void
    {
        $googleProvider = IntegrationProvider::where('slug', 'google')->firstOrFail();
        $googleIntegration = Integration::factory()->create([
            'user_id'     => $this->user->id,
            'ip_id' => $googleProvider->id,
            'status'      => 'active',
        ]);

        IntegrationInsight::factory()->count(2)->create(['integration_id' => $this->integration->id]);
        IntegrationInsight::factory()->count(4)->create(['integration_id' => $googleIntegration->id]);

        $this->authLogin();
        $response = $this->tenantGetJson('/api/insights?provider=google');

        $response->assertOk()
            ->assertJsonPath('meta.total', 4);
    }

    #[Test]
    public function index_filters_by_level(): void
    {
        IntegrationInsight::factory()->count(3)->create([
            'integration_id' => $this->integration->id,
            'level'          => 'campaign',
        ]);
        IntegrationInsight::factory()->count(2)->create([
            'integration_id' => $this->integration->id,
            'level'          => 'ad',
        ]);

        $this->authLogin();
        $response = $this->tenantGetJson('/api/insights?level=campaign');

        $response->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function index_filters_by_date_range(): void
    {
        IntegrationInsight::factory()->create([
            'integration_id' => $this->integration->id,
            'date_start'     => '2024-01-01',
            'date_stop'      => '2024-01-07',
        ]);
        IntegrationInsight::factory()->create([
            'integration_id' => $this->integration->id,
            'date_start'     => '2024-03-01',
            'date_stop'      => '2024-03-07',
        ]);

        $this->authLogin();
        $response = $this->tenantGetJson('/api/insights?date_from=2024-01-01&date_to=2024-01-31');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    public function index_respects_per_page_param(): void
    {
        IntegrationInsight::factory()->count(15)->create([
            'integration_id' => $this->integration->id,
        ]);

        $this->authLogin();
        $response = $this->tenantGetJson('/api/insights?per_page=5');

        $response->assertOk()
            ->assertJsonPath('meta.total_pages', 3)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonCount(5, 'data');
    }

    #[Test]
    public function index_sorts_by_spend_descending(): void
    {
        IntegrationInsight::factory()->create([
            'integration_id' => $this->integration->id,
            'spend'          => 100.00,
        ]);
        IntegrationInsight::factory()->create([
            'integration_id' => $this->integration->id,
            'spend'          => 500.00,
        ]);
        IntegrationInsight::factory()->create([
            'integration_id' => $this->integration->id,
            'spend'          => 250.00,
        ]);

        $this->authLogin();
        $response = $this->tenantGetJson('/api/insights?sort_by=spend&sort_direction=desc');

        $response->assertOk();

        $spends = collect($response->json('data'))
            ->pluck('metrics.spend')
            ->values();

        $this->assertEquals([500.00, 250.00, 100.00], $spends->toArray());
    }



    // #[Test]
    public function index_returns_403_when_user_lacks_policy_permission(): void
    {
        $restrictedUser = User::factory()->create(); // no viewAny permission
        $this->actingAs($restrictedUser, 'sanctum');

        // Assuming policy denies by default without explicit grant:
        $this->tenantGetJson('/api/insights')
            ->assertForbidden();
    }

    #[Test]
    public function index_returns_401_for_unauthenticated_requests(): void
    {
        $this->tenantGetJson('/api/insights')
            ->assertUnauthorized();
    }

    /** @return array<string, array<string, mixed>> */
    public static function invalidFilterProvider(): array
    {
        return [
            'invalid provider'       => [['provider' => 'snapchat'], ['provider']],
            'invalid level'          => [['level' => 'region'], ['level']],
            'invalid sort_by'        => [['sort_by' => 'revenue'], ['sort_by']],
            'invalid sort_direction' => [['sort_direction' => 'random'], ['sort_direction']],
            'per_page below min'     => [['per_page' => 0], ['per_page']],
            'per_page above max'     => [['per_page' => 101], ['per_page']],
            'date_to before from'    => [['date_from' => '2024-03-01', 'date_to' => '2024-01-01'], ['date_to']],
        ];
    }

    // #[Test]
    #[DataProvider('invalidFilterProvider')]
    public function index_fails_validation_with_invalid_filters(array $params, array $errors): void
    {
        $this->authLogin();

        $this->tenantGetJson('/api/integrations/insights?' . http_build_query($params))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errors);
    }
}
