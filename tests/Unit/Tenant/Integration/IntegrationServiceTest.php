<?php

namespace Tests\Unit\Tenant\Integration;

use App\Services\Tenant\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_ad_account_id_adds_act_prefix_once(): void
    {
        $service = new IntegrationService();

        $this->assertSame('act_123', $service->normalizeAdAccountId('123'));
        $this->assertSame('act_123', $service->normalizeAdAccountId('act_123'));
    }

    public function test_sync_returns_not_found_when_integration_is_missing(): void
    {
        $service = new IntegrationService();

        $result = $service->sync(userId: 999, provider: 'facebook', validated: [
            'level' => 'campaign',
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Facebook integration not found or not connected.', $result['error']);
        $this->assertSame(404, $result['code']);
    }
}
