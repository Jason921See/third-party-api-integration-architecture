<?php

namespace App\Http\Controllers\Tenant;

use App\Services\Tenant\IntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class IntegrationController extends BaseController
{
    public function __construct(
        private readonly IntegrationService $integrationService,
    ) {}


    // ─────────────────────────────────────────────
    // POST /api/integrations/connect
    // ─────────────────────────────────────────────

    public function connect(Request $request)
    {
        // ✅ normalize BEFORE validation
        $request->merge([
            'credentials' => array_merge(
                $request->input('credentials', []),
                [
                    'ad_account_id' => $this->integrationService->normalizeAdAccountId(
                        $request->input('credentials.ad_account_id')
                    ),
                ]
            )
        ]);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:facebook,google,tiktok'],

            'credentials' => ['required', 'array'],

            'credentials.access_token' => ['required', 'string'],

            'credentials.ad_account_id' => [
                'required',
                'string',
                // ✅ ensure uniqueness per provider
                Rule::unique('integrations', 'external_user_id')
                    ->where(fn($q) => $q->where('provider', $request->provider)),
            ],
        ]);

        $result = $this->integrationService->connect(
            userId: auth()->id(),
            provider: $validated['provider'],
            accessToken: $validated['credentials']['access_token'],
            adAccountId: $validated['credentials']['ad_account_id'],
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error']
            ], $result['code']);
        }

        return response()->json([
            'message' => ucfirst($validated['provider']) . ' integration connected successfully.',
            'integration' => $result['integration'],
        ], 201);
    }

    public function sync(Request $request)
    {
        $validated = $request->validate([
            'provider'  => 'required|string|in:facebook,google,tiktok',
            'level'     => 'required|string|in:campaign,adset,ad',
            'fields'    => 'nullable|array',
            'fields.*'  => 'string|in:impressions,clicks,reach,spend,cpc,cpm,ctr,cpp,frequency,actions,action_values',
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $result = $this->integrationService->sync(
            userId: auth()->id(),
            provider: $validated['provider'],
            validated: $validated,
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['code']);
        }

        return response()->json([
            'message'   => 'Sync job queued successfully.',
            'provider'  => $result['provider'],
            'level'     => $result['level'],
            'fields'    => $result['fields'],
            'date_from' => $result['date_from'],
            'date_to'   => $result['date_to'],
        ], 202);
    }
}
