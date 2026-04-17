<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Integration\IntegrationStatusResource;
use App\Models\Tenant\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationStatusController extends Controller
{
    // public function index(Request $request): JsonResponse
    // {
    //     $perPage = $request->integer('per_page', 10);

    //     $query = Integration::with([
    //         'provider',
    //         'runningJobs',
    //         'history',
    //         'completed',
    //         'lastFailedJob',
    //     ]);

    //     if ($provider = $request->query('provider')) {
    //         $query->whereHas(
    //             'provider',
    //             fn($q) =>
    //             $q->where('slug', $provider)
    //         );
    //     }

    //     // ❗ IMPORTANT: paginate FIRST
    //     $paginated = $query->paginate($perPage);

    //     // ❗ THEN apply policy on the collection
    //     $filtered = $paginated->getCollection()->filter(
    //         fn($integration) =>
    //         $request->user()->can('view', $integration)
    //     );

    //     // replace paginator collection
    //     $paginated->setCollection($filtered);

    //     return response()->json([
    //         'success' => true,
    //         'data' => IntegrationStatusResource::collection($paginated),

    //         'meta' => [
    //             'current_page' => $paginated->currentPage(),
    //             'per_page'     => $paginated->perPage(),
    //             'total'        => $paginated->total(),
    //             'count'        => $filtered->count(),
    //             'last_page'    => $paginated->lastPage(),
    //         ],
    //     ]);
    // }
}
