<?php

namespace App\Providers;

use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationInsight;
use App\Policies\Tenant\IntegrationInsightPolicy;
use App\Policies\Tenant\IntegrationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(IntegrationInsight::class, IntegrationInsightPolicy::class);
        Gate::policy(Integration::class, IntegrationPolicy::class);
        // Passport::useClientModel(\Laravel\Passport\Client::class);
        // Passport::useTokenModel(\Laravel\Passport\Token::class);
        // Passport::loadKeysFrom(storage_path());
    }
}
