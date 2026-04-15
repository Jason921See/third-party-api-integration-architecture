<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\IntegrationStatusResource;
use App\Models\Tenant\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Integration::with([
                'provider',
                'runningJobs',
                'history',
                'completed',
                'lastFailedJob',
            ])
            ->where('user_id', $request->user()->id);

        if ($provider = $request->query('provider')) {
            $query->whereHas('provider', fn($q) => $q->where('slug', $provider));
        }

        $integrations = $query->get();

        return response()->json([
            'success' => true,
            'data' => IntegrationStatusResource::collection($integrations),
        ]);
    }
}
