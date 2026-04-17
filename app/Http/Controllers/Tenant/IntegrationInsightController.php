<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\IntegrationInsightIndexRequest;
use App\Http\Resources\Tenant\ApiResponseResource;
use App\Http\Resources\Tenant\IntegrationInsight\IntegrationInsightCollection;
use App\Http\Resources\Tenant\IntegrationInsight\IntegrationInsightResource;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationInsight;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class IntegrationInsightController extends BaseController
{

    public function index(IntegrationInsightIndexRequest $request)
    {
        return ApiResponseResource::success(
            data: new IntegrationInsightCollection(
                $request->getInsights()
            ),
            message: 'Insights retrieved successfully.'
        );
    }

    // public function show(Request $request, int $id)
    // {
    //     $insight = IntegrationInsight::whereHas(
    //         'integration',
    //         fn($q) =>
    //         $q->where('user_id', auth()->id())
    //     )->findOrFail($id);

    //     return new IntegrationInsightResource($insight);
    // }
}
