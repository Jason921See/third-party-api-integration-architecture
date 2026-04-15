<?php

namespace Tests\Unit\Tenant\Integration;

use App\Integrations\Tenant\ExternalService\Facebook\FacebookService;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!config('app.key')) {
            config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        }
    }

    private function createFacebookProvider(): IntegrationProvider
    {
        return IntegrationProvider::create([
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
    }

    public function test_connect_validates_token_and_stores_integration(): void
    {
        $provider = $this->createFacebookProvider();

        Http::fake([
            'https://graph.facebook.com/v17.0/me/adaccounts*' => Http::response([
                'data' => [
                    ['id' => 'act_123', 'name' => 'Tenant Test Account'],
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

        $service = new FacebookService(new Integration());
        $result = $service->connect(userId: 101, accessToken: 'valid-token', adAccountId: '123');

        $this->assertTrue($result['success']);
        $this->assertSame('facebook', $result['integration']['provider']);
        $this->assertDatabaseHas('integrations', [
            'user_id' => 101,
            'external_user_id' => 'act_123',
            'status' => 'active',
        ]);
    }

    public function test_refresh_token_updates_access_token_and_expiration(): void
    {
        $provider = $this->createFacebookProvider();

        $integration = Integration::create([
            'user_id' => 101,
            'ip_id' => $provider->id,
            'status' => 'active',
            'external_user_id' => 'act_123',
            'access_token' => 'old-token',
            'token_expires_at' => now()->subMinutes(5),
            'scopes' => ['ads_read'],
            'meta' => ['currency' => 'USD'],
        ]);

        Http::fake([
            'https://graph.facebook.com/oauth/access_token*' => Http::response([
                'access_token' => 'new-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $service = new FacebookService($integration);
        $response = $service->refreshToken($integration);

        $this->assertTrue($response['success']);
        $this->assertSame('new-token', $response['access_token']);
        $this->assertSame('new-token', $integration->fresh()->access_token);
    }
}
