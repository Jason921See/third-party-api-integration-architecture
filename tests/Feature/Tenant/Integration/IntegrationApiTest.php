<?php

namespace Tests\Feature\Tenant\Integration;

use App\Jobs\Tenant\FetchFacebookInsightJob;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationProvider;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class IntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!config('app.key')) {
            config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        }
    }

    public function test_connect_endpoint_creates_facebook_integration(): void
    {
        $user = User::create([
            'name' => 'Tenant Tester',
            'email' => 'tenant@example.com',
            'password' => bcrypt('password'),
            'global_id' => Str::ulid(),
        ]);

        IntegrationProvider::create([
            'name' => 'Facebook Marketing API',
            'slug' => 'facebook',
            'type' => 'oauth2',
            'scopes' => ['ads_read'],
            'config' => [
                'base_url' => 'https://graph.facebook.com',
                'api_version' => 'v17.0',
                'debug_url' => 'https://graph.facebook.com/debug_token',
                'token_url' => 'https://graph.facebook.com/oauth/access_token',
            ],
            'is_active' => true,
        ]);

        Http::fake([
            'https://graph.facebook.com/v17.0/me/adaccounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'act_123',
                        'name' => 'Tenant Test Account',
                    ],
                ],
            ], 200),
            'https://graph.facebook.com/v17.0/act_123*' => Http::response([
                'id' => 'act_123',
                'account_id' => '123',
                'name' => 'Tenant Test Account',
                'account_status' => 1,
                'currency' => 'USD',
                'timezone_name' => 'America/Los_Angeles',
                'amount_spent' => 100,
            ], 200),
        ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ])->postJson('/api/integrations/connect', [
            'provider' => 'facebook',
            'credentials' => [
                'access_token' => 'test-access-token',
                'ad_account_id' => '123',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Facebook integration connected successfully.',
                'integration' => [
                    'provider' => 'facebook',
                    'account_id' => 'act_123',
                ],
            ]);

        $this->assertDatabaseHas('integrations', [
            'user_id' => $user->id,
            'status' => 'active',
            'external_user_id' => 'act_123',
        ]);
    }

    public function test_sync_endpoint_queues_facebook_sync_job(): void
    {
        $user = User::create([
            'name' => 'Tenant Sync User',
            'email' => 'sync@example.com',
            'password' => bcrypt('password'),
            'global_id' => Str::ulid(),
        ]);

        $provider = IntegrationProvider::create([
            'name' => 'Facebook Marketing API',
            'slug' => 'facebook',
            'type' => 'oauth2',
            'scopes' => ['ads_read'],
            'config' => [
                'base_url' => 'https://graph.facebook.com',
                'api_version' => 'v17.0',
                'debug_url' => 'https://graph.facebook.com/debug_token',
                'token_url' => 'https://graph.facebook.com/oauth/access_token',
            ],
            'is_active' => true,
        ]);

        $integration = Integration::create([
            'user_id' => $user->id,
            'ip_id' => $provider->id,
            'status' => 'active',
            'external_user_id' => 'act_123',
            'external_account_name' => 'Tenant Sync Account',
            'access_token' => 'existing-token',
            'token_expires_at' => now()->addDays(30),
            'scopes' => ['ads_read'],
            'meta' => ['currency' => 'USD'],
        ]);

        Queue::fake();

        Sanctum::actingAs($user, ['*']);
        $response = $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ])->postJson('/api/integrations/sync', [
            'provider' => 'facebook',
            'level' => 'campaign',
            'fields' => ['impressions', 'clicks'],
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'message' => 'Sync job queued successfully.',
                'provider' => 'facebook',
                'level' => 'campaign',
                'fields' => ['impressions', 'clicks'],
            ]);

        Queue::assertPushed(FetchFacebookInsightJob::class, fn (FetchFacebookInsightJob $job) =>
            $job->integrationId === $integration->id &&
            $job->level === 'campaign' &&
            $job->adAccountId === $integration->external_user_id
        );
    }
}
