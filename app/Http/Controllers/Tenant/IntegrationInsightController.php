<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\IntegrationInsight\IntegrationInsightResource;
use App\Http\Resources\Tenant\IntegrationInsight\IntegrationInsightCollection;
use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationInsight;
use Illuminate\Http\Request;

class IntegrationInsightController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'provider'       => 'nullable|string|in:facebook,google,tiktok',
            'level'          => 'nullable|string|in:account,campaign,adset,ad',
            'object_id'      => 'nullable|string',
            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date|after_or_equal:date_from',
            'per_page'       => 'nullable|integer|min:1|max:100',
            'sort_by'        => 'nullable|string|in:spend,clicks,impressions,reach,ctr,cpc,cpm,date_start',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ]);

        // Resolve integration IDs for this user filtered by provider
        $integrationIds = Integration::where('user_id', auth()->id())
            ->when($validated['provider'] ?? null, function ($q, $provider) {
                $q->whereHas('provider', fn($q) => $q->where('slug', $provider));
            })
            ->where('status', 'active')
            ->pluck('id');

        if ($integrationIds->isEmpty()) {
            return response()->json([
                'message' => 'No active integrations found.',
                'data'    => [],
                'summary' => [],
            ], 404);
        }

        $insights = IntegrationInsight::whereIn('integration_id', $integrationIds)
            ->when($validated['level'] ?? null,     fn($q, $v) => $q->where('level', $v))
            ->when($validated['object_id'] ?? null, fn($q, $v) => $q->where('object_id', $v))
            ->when($validated['date_from'] ?? null, fn($q, $v) => $q->whereDate('date_start', '>=', $v))
            ->when($validated['date_to'] ?? null,   fn($q, $v) => $q->whereDate('date_stop', '<=', $v))
            ->orderBy(
                $validated['sort_by'] ?? 'date_start',
                $validated['sort_direction'] ?? 'desc',
            )
            ->paginate($validated['per_page'] ?? 20);

        return (new IntegrationInsightCollection($insights))
            ->additional([
                'filters' => [
                    'provider'  => $validated['provider'] ?? null,
                    'level'     => $validated['level'] ?? null,
                    'object_id' => $validated['object_id'] ?? null,
                    'date_from' => $validated['date_from'] ?? null,
                    'date_to'   => $validated['date_to'] ?? null,
                ],
            ]);
    }

    public function show(Request $request, int $id)
    {
        $insight = IntegrationInsight::whereHas(
            'integration',
            fn($q) =>
            $q->where('user_id', auth()->id())
        )->findOrFail($id);

        return new IntegrationInsightResource($insight);
    }
}
