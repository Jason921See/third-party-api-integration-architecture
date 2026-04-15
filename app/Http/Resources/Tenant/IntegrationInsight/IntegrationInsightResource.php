<?php

namespace App\Http\Resources\Tenant\IntegrationInsight;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationInsightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'integration_id' => $this->integration_id,
            'level'          => $this->level,
            'object'         => [
                'id'        => $this->object_id,
                'name'      => $this->object_name,
                'parent_id' => $this->parent_object_id,
            ],
            'date'           => [
                'start' => $this->date_start?->toDateString(),
                'stop'  => $this->date_stop?->toDateString(),
            ],
            'metrics'        => [
                'impressions' => (int) $this->impressions,
                'clicks'      => (int) $this->clicks,
                'reach'       => (int) $this->reach,
                'spend'       => (float) $this->spend,
                'cpc'         => (float) $this->cpc,
                'cpm'         => (float) $this->cpm,
                'ctr'         => (float) $this->ctr,
                'cpp'         => (float) $this->cpp,
                'frequency'   => (float) $this->frequency,
            ],
            'actions'        => $this->actions,
            'action_values'  => $this->action_values,
            'currency'       => $this->account_currency,
            'fetched_at'     => $this->fetched_at?->toDateTimeString(),
        ];
    }
}
