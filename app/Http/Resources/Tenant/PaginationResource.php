<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'meta' => [
                'page'        => $this->currentPage(),
                'size'        => $this->perPage(),
                'total_pages' => $this->lastPage(),
            ],

            'links' => [
                'self'  => $this->url($this->currentPage()),
                'next'  => $this->nextPageUrl(),
                'prev'  => $this->previousPageUrl(),
                'first' => $this->url(1),
                'last'  => $this->url($this->lastPage()),
            ],
        ];
    }
}
