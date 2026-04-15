<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationInsight extends Model
{
    protected $fillable = [
        'integration_id',
        'level',
        'object_id',
        'object_name',
        'parent_object_id',
        'date_start',
        'date_stop',
        'account_currency',
        'impressions',
        'clicks',
        'reach',
        'spend',
        'cpc',
        'cpm',
        'ctr',
        'cpp',
        'frequency',
        'actions',
        'action_values',
        'raw',
        'fetched_at',
    ];

    protected $casts = [
        'date_start'    => 'date',
        'date_stop'     => 'date',
        'spend'         => 'decimal:4',
        'cpc'           => 'decimal:4',
        'cpm'           => 'decimal:4',
        'ctr'           => 'decimal:4',
        'cpp'           => 'decimal:4',
        'frequency'     => 'decimal:4',
        'actions'       => 'array',
        'action_values' => 'array',
        'raw'           => 'array',
        'fetched_at'    => 'datetime',
    ];

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

    public function scopeAccount($query)
    {
        return $query->where('level', 'account');
    }

    public function scopeCampaign($query)
    {
        return $query->where('level', 'campaign');
    }

    public function scopeAdset($query)
    {
        return $query->where('level', 'adset');
    }

    public function scopeAd($query)
    {
        return $query->where('level', 'ad');
    }

    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('date_start', [$from, $to]);
    }

    public function scopeForObject($query, string $objectId)
    {
        return $query->where('object_id', $objectId);
    }

    // ─────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────

    public function getPurchaseValueAttribute(): float
    {
        if (empty($this->action_values)) return 0.0;

        return collect($this->action_values)
            ->where('action_type', 'purchase')
            ->sum(fn($a) => (float) $a['value']);
    }

    public function getPurchaseCountAttribute(): int
    {
        if (empty($this->actions)) return 0;

        return (int) collect($this->actions)
            ->where('action_type', 'purchase')
            ->sum(fn($a) => (float) $a['value']);
    }

    public function getRoasAttribute(): float
    {
        if ((float) $this->spend === 0.0) return 0.0;

        return round($this->purchase_value / (float) $this->spend, 4);
    }
}
