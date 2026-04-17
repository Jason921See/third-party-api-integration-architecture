<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AuthController as TenantAuthController;
use App\Http\Controllers\Tenant\IntegrationController;
use App\Http\Controllers\Tenant\IntegrationInsightController;
use App\Http\Controllers\Tenant\IntegrationStatusController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

// Route::post('/login', [TenantAuthController::class, 'login']);
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/integrations/connect', [IntegrationController::class, 'connect']);
//     Route::post('/integrations/sync', [IntegrationController::class, 'sync']);

//     Route::get('/insights',     [IntegrationInsightController::class, 'index']);
//     // Route::get('/insights/{id}', [IntegrationInsightController::class, 'show']);

//     Route::get('/integrations/status',      [IntegrationStatusController::class, 'index']);
//     // Route::get('/integrations/{id}/status', [IntegrationStatusController::class, 'show']);

//     Route::post('/logout', [TenantAuthController::class, 'logout']);
//     Route::get('/me', [TenantAuthController::class, 'me']);
// });

// Route::middleware([
//     'web',
//     InitializeTenancyByDomain::class,
//     PreventAccessFromCentralDomains::class,
// ])->group(function () {
//     Route::get('/', function () {
//         return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
//     });
// });


Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    Route::post('/login', [TenantAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/integrations/connect', [IntegrationController::class, 'connect']);
        Route::post('/integrations/sync', [IntegrationController::class, 'sync']);

        Route::get('/insights', [IntegrationInsightController::class, 'index']);
        Route::get('/integrations/status', [IntegrationController::class, 'status']);

        Route::post('/logout', [TenantAuthController::class, 'logout']);
        Route::get('/me', [TenantAuthController::class, 'me']);
    });
});
