<?php

namespace App\Providers;

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
        Passport::useClientModel(\Laravel\Passport\Client::class);
        Passport::useTokenModel(\Laravel\Passport\Token::class);
        Passport::loadKeysFrom(storage_path());
    }
}
