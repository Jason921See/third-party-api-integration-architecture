<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\IntegrationProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationProvider>
 */
class IntegrationProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $providers = [
            'facebook',
            'google',
            'tiktok',
        ];

        $name = $this->faker->randomElement($providers);

        return [
            'name' => ucfirst($name),
            'slug' => $name,
            'type' => 'ads',
            'scopes' => [
                'read_insights',
                'manage_ads',
            ],
            'config' => [
                'api_version' => 'v1',
            ],
            'is_active' => true,
        ];
    }

    public function facebook(): static
    {
        return $this->state(fn() => [
            'name' => 'Facebook',
            'slug' => 'facebook',
        ]);
    }

    public function google(): static
    {
        return $this->state(fn() => [
            'name' => 'Google',
            'slug' => 'google',
        ]);
    }

    public function tiktok(): static
    {
        return $this->state(fn() => [
            'name' => 'TikTok',
            'slug' => 'tiktok',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'is_active' => false,
        ]);
    }
}
