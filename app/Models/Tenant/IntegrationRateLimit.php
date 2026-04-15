<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class IntegrationRateLimit extends Model
{
    protected $fillable = [
        'integration_id',
        'endpoint',
        'requests_made',
        'requests_limit',
        'retry_after',
        'window_resets_at',
        'blocked_until',
    ];

    protected $casts = [
        'requests_made'   => 'integer',
        'requests_limit'  => 'integer',
        'retry_after'     => 'integer',
        'window_resets_at' => 'datetime',
        'blocked_until'   => 'datetime',
    ];

    // Relationships
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    // Helpers
    public function isBlocked(): bool
    {
        return $this->blocked_until && $this->blocked_until->isFuture();
    }

    public function isWindowExpired(): bool
    {
        return $this->window_resets_at && $this->window_resets_at->isPast();
    }

    public function isLimitReached(): bool
    {
        if (!$this->requests_limit) {
            return false;
        }

        return $this->requests_made >= $this->requests_limit;
    }

    public function remainingRequests(): int
    {
        if (!$this->requests_limit) {
            return PHP_INT_MAX;
        }

        return max(0, $this->requests_limit - $this->requests_made);
    }

    public function secondsUntilUnblocked(): int
    {
        if (!$this->isBlocked()) {
            return 0;
        }

        return (int) now()->diffInSeconds($this->blocked_until);
    }

    public function resetWindow(): void
    {
        $this->update([
            'requests_made'    => 0,
            'blocked_until'    => null,
            'window_resets_at' => null,
            'retry_after'      => null,
        ]);
    }

    public function incrementRequests(): void
    {
        $this->increment('requests_made');
    }

    public function applyBlock(int $retryAfterSeconds): void
    {
        $this->update([
            'retry_after'   => $retryAfterSeconds,
            'blocked_until' => now()->addSeconds($retryAfterSeconds),
        ]);
    }
}
