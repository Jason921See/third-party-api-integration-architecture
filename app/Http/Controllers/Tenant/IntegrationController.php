<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Requests\Tenant\ConnectIntegrationRequest;
use App\Http\Requests\Tenant\SyncIntegrationInsightRequest;
use App\Http\Resources\Tenant\ApiResponseResource;
use App\Http\Resources\Tenant\Integration\IntegrationResource;
use App\Http\Resources\Tenant\Integration\IntegrationStatusCollection;
use App\Models\Tenant\Integration;
use App\Services\Tenant\IntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class IntegrationController extends BaseController
{
    public function __construct(
        private readonly IntegrationService $integrationService,
        // private readonly IntegrationRepository $integrationRepository
    ) {}


    // ─────────────────────────────────────────────
    // POST /api/integrations/connect
    // ─────────────────────────────────────────────

    public function connect(ConnectIntegrationRequest $request)
    {
        $validated = $request->validated();
        // Log::info('ConnectIntegrationRequest validated data', ['validated' => $validated]);
        $result = $this->integrationService->connect(
            userId: Auth::id(),
            provider: $validated['provider'],
            accessToken: $validated['credentials']['access_token'],
            adAccountId: $validated['credentials']['ad_account_id'],
        );

        Log::info('Integration connect result', [
            'result' => $result,
            'validated' => $validated,
        ]);
        if (!$result['success']) {
            return ApiResponseResource::error(
                $result['error'],
                $result['code']
            );
        }

        return ApiResponseResource::success(
            data: [
                'integration' => new IntegrationResource($result['integration']),
            ],
            message: ucfirst($validated['provider']) . ' integration connected successfully.'
        );
    }

    public function sync(SyncIntegrationInsightRequest  $request)
    {
        $validated = $request->validated();

        $integrations = $this->integrationService->findActiveIntegration(Auth::id(), $validated['provider']);

        if ($integrations->isEmpty()) {
            return ApiResponseResource::error(
                'No active integrations found for this provider.',
                404
            );
        }

        foreach ($integrations as $integration) {
            $this->authorize('sync', $integration);
        }

        $result = $this->integrationService->sync(
            integrations: $integrations,
            validated: $validated,
        );

        if (!$result['success']) {
            return ApiResponseResource::error(
                $result['error'],
                $result['code']
            );
        }

        return ApiResponseResource::success(
            data: [
                'provider'  => $result['provider'],
                'level'     => $result['level'],
                'fields'    => $result['fields'] ?? null,
                'date_from' => $result['date_from'],
                'date_to'   => $result['date_to'],
            ],
            message: 'Sync job queued successfully.'
        );
    }

    public function status(Request $request)
    {
        $perPage = $request->integer('per_page', 10);
        $query = Integration::with([
            'provider',
            'runningJobs',
            'history',
            'completed',
            'lastFailedJob',
        ]);

        if ($provider = $request->query('provider')) {
            $query->whereHas(
                'provider',
                fn($q) =>
                $q->where('slug', $provider)
            );
        }

        $paginated = $query->paginate($perPage);

        $filtered = $paginated->getCollection()
            ->filter(
                fn($integration) =>
                $request->user()->can('view', $integration)
            )
            ->values();

        $paginated->setCollection($filtered);
        return ApiResponseResource::success(
            data: new IntegrationStatusCollection($paginated),
            message: 'Integration status retrieved successfully.'
        );
    }
}
