<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiResponseResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource['data'] ?? null;

        $response = [
            'success' => $this->resource['success'] ?? true,
            'message' => $this->resource['message'] ?? null,
            'data'    => $this->extractData($data),
            'error'   => $this->resource['error'] ?? null,
        ];

        // ✅ Handle pagination (important)
        if ($paginator = $this->extractPaginator($data)) {
            $response['meta'] = [
                'page'        => $paginator->currentPage(),
                'size'        => $paginator->perPage(),
                'total'       => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ];

            $response['links'] = [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ];
        }

        return $response;
    }

    /**
     * Extract actual data from resource/collection
     */
    protected function extractData($data)
    {
        if ($data instanceof ResourceCollection) {
            return $data->collection;
        }

        return $data;
    }

    /**
     * Extract paginator from different cases
     */
    protected function extractPaginator($data): ?LengthAwarePaginator
    {
        // Case 1: Direct paginator
        if ($data instanceof LengthAwarePaginator) {
            return $data;
        }

        // Case 2: ResourceCollection wrapping paginator
        if (
            $data instanceof ResourceCollection &&
            $data->resource instanceof LengthAwarePaginator
        ) {
            return $data->resource;
        }

        return null;
    }

    public static function success($data = null, string $message = 'Success'): self
    {
        return new self([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    public static function error(string $message, int $status = 400, $errors = null): self
    {
        return new self([
            'success' => false,
            'message' => $message,
            'error'   => $errors,
        ]);
    }
}
