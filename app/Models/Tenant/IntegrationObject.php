<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class IntegrationObject extends Model
{
    protected $fillable = [
        'integration_id',
        'object_type',
        'object_id',
        'object_name',
        'status',
        'effective_status',
        'parent_object_id',
        'parent_object_type',
        'meta',
        'synced_at',
    ];

    protected $casts = [
        'meta'      => 'array',
        'synced_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    public function insights()
    {
        return $this->hasMany(IntegrationInsight::class);
    }

    public function parent()
    {
        return $this->belongsTo(IntegrationObject::class, 'parent_object_id', 'object_id')
            ->where('object_type', $this->parent_object_type);
    }

    public function children()
    {
        return $this->hasMany(IntegrationObject::class, 'parent_object_id', 'object_id');
    }

    // ─────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────

    public function scopeOfType($query, string $type)
    {
        return $query->where('object_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeCampaigns($query)
    {
        return $query->where('object_type', 'campaign');
    }

    public function scopeAdSets($query)
    {
        return $query->where('object_type', 'adset');
    }

    public function scopeAds($query)
    {
        return $query->where('object_type', 'ad');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isCampaign(): bool
    {
        return $this->object_type === 'campaign';
    }

    public function isAdSet(): bool
    {
        return $this->object_type === 'adset';
    }

    public function isAd(): bool
    {
        return $this->object_type === 'ad';
    }
}
