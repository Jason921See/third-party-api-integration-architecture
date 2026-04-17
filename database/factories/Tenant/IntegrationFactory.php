<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'ip_id' => 1,
            'status' => 'connected',
            'external_user_id' => Str::ulid(),
            'external_account_name' => fake()->company(),
            'access_token' => Str::random(32),
            'refresh_token' => Str::random(32),
            'token_expires_at' => now()->addDays(30),
            'scopes' => ['ads_read'],
            'meta' => [],
            'last_used_at' => now(),
            'last_refreshed_at' => now(),
        ];
    }
}
