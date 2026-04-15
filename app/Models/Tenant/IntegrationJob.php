<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationJob extends Model
{
    protected $fillable = [
        'integration_id',
        'provider',
        'job_type',
        'level',
        'status',
        'attempt',
        'date_start',
        'date_stop',
        'date_preset',
        'records_synced',
        'error_message',
        'error_context',
        'started_at',
        'completed_at',
        'duration_seconds',
    ];

    protected $casts = [
        'error_context'  => 'array',
        'date_start'     => 'date',
        'date_stop'      => 'date',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
    ];

    const STATUS_PENDING   = 'pending';
    const STATUS_RUNNING   = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED    = 'failed';

    // ─────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    // ─────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────

    public function scopeForIntegration($query, int $integrationId)
    {
        return $query->where('integration_id', $integrationId);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    // ─────────────────────────────────────────────
    // Lifecycle helpers — called from jobs
    // ─────────────────────────────────────────────

    public function markRunning(): void
    {
        $this->update([
            'status'     => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(int $recordsSynced = 0): void
    {
        $duration = abs(now()->floatDiffInSeconds($this->started_at));
        // $startedAt = $this->started_at ?? now();

        $this->update([
            'status'           => self::STATUS_COMPLETED,
            'records_synced'   => $recordsSynced,
            'completed_at'     => now(),
            'duration_seconds' => $duration,
            'error_message'    => null,
            'error_context'    => null,
        ]);
    }

    public function markFailed(string $message, array $context = []): void
    {
        $startedAt = $this->started_at ?? now();

        $this->update([
            'status'           => self::STATUS_FAILED,
            'error_message'    => $message,
            'error_context'    => $context,
            'completed_at'     => now(),
            'duration_seconds' => now()->diffInSeconds($startedAt),
        ]);
    }
}
