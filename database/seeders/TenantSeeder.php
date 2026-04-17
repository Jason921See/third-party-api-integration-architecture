<?php

namespace Database\Seeders;

use App\Models\Central\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Seed the central database with tenant and domain records.
     */
    public function run(): void
    {
        $tenant = Tenant::create([
            'id' => (string) Str::ulid(),
            'tenancy_db_name' => 'tenant_' . Str::random(10),
        ]);

        $tenant->domains()->create(['domain' => 'tenant.central.test']);
    }
}
