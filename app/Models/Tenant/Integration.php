<?php

namespace App\Models\Tenant;

use App\Models\Tenant\IntegrationJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Integration extends Model
{
    protected $fillable = [
        'user_id',
        'ip_id',
        'status',
        'external_user_id',
        'external_account_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'meta',
        'last_used_at',
        'last_refreshed_at',
    ];

    protected $casts = [
        'scopes'            => 'array',
        'meta'              => 'array',
        'token_expires_at'  => 'datetime',
        'last_used_at'      => 'datetime',
        'last_refreshed_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    // Relationships
    public function provider()
    {
        return $this->belongsTo(IntegrationProvider::class, 'ip_id');
    }

    public function apiLogs()
    {
        return $this->hasMany(IntegrationApiLog::class);
    }

    public function rateLimit()
    {
        return $this->hasOne(IntegrationRateLimit::class);
    }

    // Token encryption
    public function setAccessTokenAttribute(string $value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function objects()
    {
        return $this->hasMany(IntegrationObject::class);
    }

    public function campaigns()
    {
        return $this->hasMany(IntegrationObject::class)->where('object_type', 'campaign');
    }

    public function adsets()
    {
        return $this->hasMany(IntegrationObject::class)->where('object_type', 'adset');
    }

    public function ads()
    {
        return $this->hasMany(IntegrationObject::class)->where('object_type', 'ad');
    }

    public function insights()
    {
        return $this->hasMany(IntegrationInsight::class);
    }

    public function integrationJobs()
    {
        return $this->hasMany(IntegrationJob::class);
    }

    public function runningJobs()
    {
        return $this->hasMany(IntegrationJob::class)->running();
    }

    public function history()
    {
        return $this->hasMany(IntegrationJob::class)
            ->where('status', '!=', IntegrationJob::STATUS_RUNNING)
            ->latest('completed_at');
    }

    public function completed()
    {
        return $this->hasOne(IntegrationJob::class)
            ->completed()
            ->latest('completed_at');
    }

    public function lastFailedJob()
    {
        return $this->hasOne(IntegrationJob::class)
            ->failed()
            ->latest('completed_at');
    }
}
