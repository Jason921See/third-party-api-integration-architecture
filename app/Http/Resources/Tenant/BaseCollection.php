<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BaseCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return $this->collection->map(function ($item) {
            return $this->collects
                ? new $this->collects($item)
                : $item;
        })->values()->toArray();
    }

    // public function with($request): array
    // {
    //     if (!$this->resource instanceof \Illuminate\Pagination\AbstractPaginator) {
    //         return [];
    //     }

    //     return [
    //         'meta' => [
    //             'page'        => $this->currentPage(),
    //             'size'        => $this->perPage(),
    //             'total_pages' => $this->lastPage(),
    //         ],

    //         // 'links' => [
    //         //     'self'  => $this->url($this->currentPage()),
    //         //     'next'  => $this->nextPageUrl(),
    //         //     'prev'  => $this->previousPageUrl(),
    //         //     'first' => $this->url(1),
    //         //     'last'  => $this->url($this->lastPage()),
    //         // ],
    //     ];
    // }

    public function with($request): array
    {
        return []; // prevent duplicate meta
    }
}
