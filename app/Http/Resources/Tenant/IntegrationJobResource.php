<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'job_type'   => $this->job_type,
            'level'      => $this->level,
            'status'     => $this->status,
            'attempt'    => $this->attempt,

            'records_synced' => $this->records_synced,

            'started_at'     => $this->started_at,
            'completed_at'   => $this->completed_at,
            'duration_seconds' => $this->duration_seconds,

            'error' => $this->when(
                $this->status === 'failed',
                fn() => [
                    'message' => $this->error_message,
                    'context' => $this->error_context,
                ]
            ),

            // 'running_for_seconds' => $this->when(
            //     $this->status === 'running',
            //     fn() => now()->diffInSeconds($this->started_at)
            // ),
        ];
    }
}
