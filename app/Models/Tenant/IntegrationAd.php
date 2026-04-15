<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationAd extends Model
{
    protected $fillable = [
        'integration_id',
        'ad_id',
        'name',
        'adset_id',
        'campaign_id',
        'status',
        'effective_status',
        'creative',
        'tracking_specs',
        'conversion_specs',
        'fb_created_time',
        'fb_updated_time',
        'raw',
        'synced_at',
    ];

    protected $casts = [
        'creative'         => 'array',
        'tracking_specs'   => 'array',
        'conversion_specs' => 'array',
        'raw'              => 'array',
        'fb_created_time'  => 'datetime',
        'fb_updated_time'  => 'datetime',
        'synced_at'        => 'datetime',
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

    public function scopeActive($query)
    {
        return $query->where('effective_status', 'ACTIVE');
    }

    public function scopePaused($query)
    {
        return $query->where('effective_status', 'PAUSED');
    }

    public function scopeCampaignPaused($query)
    {
        return $query->where('effective_status', 'CAMPAIGN_PAUSED');
    }

    // ─────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────

    /**
     * Shortcut to get the Instagram permalink from the creative object.
     */
    public function getInstagramPermalinkAttribute(): ?string
    {
        return $this->creative['instagram_permalink_url'] ?? null;
    }

    /**
     * Shortcut to get the creative ID.
     */
    public function getCreativeIdAttribute(): ?string
    {
        return $this->creative['id'] ?? null;
    }
}
