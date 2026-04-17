<?php

namespace App\Http\Resources\Tenant\Integration;

use App\Http\Resources\Tenant\IntegrationJobResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->meta['status'] ?? $this->status;

        return [
            'id'           => $this->id,
            'provider'     => $this->provider->slug ?? null,
            'account_id'   => $this->external_user_id,
            'account_name' => $this->external_account_name,

            'status'       => $status,
            'connected'    => $status === 'active',

            'token_expires_at' => $this->token_expires_at,
            'token_expired'    => $this->isTokenExpired(),

            'last_sync' => $this->whenLoaded('completed', function () {
                return [
                    'job_type'       => $this->completed?->job_type,
                    'level'          => $this->completed?->level,
                    'records_synced' => $this->completed?->records_synced,
                    'completed_at'   => $this->completed?->completed_at,
                    'duration_seconds' => $this->completed?->duration_seconds,
                ];
            }),

            'last_error' => $this->whenLoaded('lastFailedJob', function () {
                return [
                    'job_type'   => $this->lastFailedJob?->job_type,
                    'level'      => $this->lastFailedJob?->level,
                    'message'    => $this->lastFailedJob?->error_message,
                    'failed_at'  => $this->lastFailedJob?->completed_at,
                    'attempt'    => $this->lastFailedJob?->attempt,
                ];
            }),

            'running_jobs' => IntegrationJobResource::collection(
                $this->whenLoaded('runningJobs')
            ),

            'history' => IntegrationJobResource::collection(
                $this->whenLoaded('history')
            ),
        ];
    }
}
