<?php

namespace App\Http\Resources\Tenant\Integration;

use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->ip_id,
            'user_id' => $this->user_id,
            'external_user_id' => $this->external_user_id,
            'status' => $this->status ?? null,
            'created_at' => $this->created_at,
        ];
    }
}
