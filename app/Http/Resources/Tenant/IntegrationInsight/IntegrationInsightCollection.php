<?php

namespace App\Http\Resources\Tenant\IntegrationInsight;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class IntegrationInsightCollection extends ResourceCollection
{

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        // Summary totals across the result set
        $items = $this->collection;

        return [
            'summary' => [
                'total_records' => $items->count(),
                'total_spend'   => round($items->sum(fn($r) => $r->spend), 2),
                'total_clicks'  => $items->sum(fn($r) => $r->clicks),
                'total_reach'   => $items->sum(fn($r) => $r->reach),
                'total_impressions' => $items->sum(fn($r) => $r->impressions),
                'avg_ctr'       => round($items->avg(fn($r) => $r->ctr), 4),
                'avg_cpc'       => round($items->avg(fn($r) => $r->cpc), 4),
                'avg_cpm'       => round($items->avg(fn($r) => $r->cpm), 4),
            ],
        ];
    }
}
