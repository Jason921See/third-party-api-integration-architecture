<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationAdCampaign extends Model
{
    protected $fillable = [
        'integration_id',
        'campaign_id',
        'account_id',
        'name',
        'objective',
        'buying_type',
        'daily_budget',
        'lifetime_budget',
        'spend_cap',
        'bid_strategy',
        'pacing_type',
        'status',
        'effective_status',
        'promoted_object',
        'recommendations',
        'issues_info',
        'adlabels',
        'special_ad_categories',
        'special_ad_category_country',
        'smart_promotion_type',
        'is_skadnetwork_attribution',
        'fb_start_time',
        'fb_stop_time',
        'fb_created_time',
        'fb_updated_time',
        'raw',
        'synced_at',
    ];

    protected $casts = [
        'pacing_type'                 => 'array',
        'promoted_object'             => 'array',
        'recommendations'             => 'array',
        'issues_info'                 => 'array',
        'adlabels'                    => 'array',
        'special_ad_categories'       => 'array',
        'special_ad_category_country' => 'array',
        'raw'                         => 'array',
        'is_skadnetwork_attribution'  => 'boolean',
        'fb_start_time'               => 'datetime',
        'fb_stop_time'                => 'datetime',
        'fb_created_time'             => 'datetime',
        'fb_updated_time'             => 'datetime',
        'synced_at'                   => 'datetime',
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
}
