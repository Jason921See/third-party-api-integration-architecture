<?php

namespace Tests;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected static ?string $tenantId = null;
    protected static string $tenantDomain = 'tenant.central.test';
    protected static bool $tenantSeeded = false;

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require dirname(__DIR__) . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTenantExists();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        if (self::$tenantId) {
            $tenant = Tenant::find(self::$tenantId);
            if ($tenant) {
                tenancy()->initialize($tenant);
                $tenant->delete();
                tenancy()->end();
            }

            self::$tenantId = null;
            self::$tenantSeeded = false;
        }

        parent::tearDown();
    }

    private function ensureTenantExists(): void
    {
        $this->cleanupExistingTenant();

        $tenant = Tenant::create(['id' => Str::ulid()]);
        $tenant->domains()->create(['domain' => self::$tenantDomain]);
        self::$tenantId = $tenant->id;

        tenancy()->initialize($tenant);
        self::$tenantSeeded = false;
    }

    private function cleanupExistingTenant(): void
    {
        $existing = Tenant::whereHas(
            'domains',
            fn($q) => $q->where('domain', self::$tenantDomain)
        )->first();

        if ($existing) {
            tenancy()->initialize($existing);
            $existing->delete();
            tenancy()->end();
        }
    }
}
