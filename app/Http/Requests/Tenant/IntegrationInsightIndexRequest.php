<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationInsight;
use Illuminate\Foundation\Http\FormRequest;

class IntegrationInsightIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', IntegrationInsight::class);
    }

    public function rules(): array
    {
        return [
            'provider'       => 'nullable|string|in:facebook,google,tiktok',
            'level'          => 'nullable|string|in:account,campaign,adset,ad',
            'object_id'      => 'nullable|string',
            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date|after_or_equal:date_from',
            'per_page'       => 'nullable|integer|min:1|max:100',
            'sort_by'        => 'nullable|string|in:spend,clicks,impressions,reach,ctr,cpc,cpm,date_start',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ];
    }

    public function getInsights()
    {
        $validated = $this->validated();

        $integrationIds = Integration::where('user_id', $this->user()->id)
            ->when($validated['provider'] ?? null, function ($q, $provider) {
                $q->whereHas('provider', fn($q) => $q->where('slug', $provider));
            })
            ->where('status', 'active')
            ->pluck('id');

        if ($integrationIds->isEmpty()) {
            return IntegrationInsight::whereRaw('1 = 0')->paginate();
        }

        return IntegrationInsight::whereIn('integration_id', $integrationIds)
            ->when($validated['level'] ?? null, fn($q, $v) => $q->where('level', $v))
            ->when($validated['object_id'] ?? null, fn($q, $v) => $q->where('object_id', $v))
            ->when($validated['date_from'] ?? null, fn($q, $v) => $q->whereDate('date_start', '>=', $v))
            ->when($validated['date_to'] ?? null, fn($q, $v) => $q->whereDate('date_stop', '<=', $v))
            ->orderBy(
                $validated['sort_by'] ?? 'date_start',
                $validated['sort_direction'] ?? 'desc'
            )
            ->paginate($validated['per_page'] ?? 20);
    }
}
