<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Integration;

class IntegrationRepository
{
    public function findActiveByUser(int $userId, ?string $provider = null)
    {
        return Integration::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->when($provider, function ($q) use ($provider) {
                $q->whereHas('provider', fn($q) => $q->where('slug', $provider));
            })
            ->get();
    }
}
