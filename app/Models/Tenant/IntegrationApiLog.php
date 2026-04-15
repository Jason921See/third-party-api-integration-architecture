<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class IntegrationApiLog extends Model
{
    protected $fillable = [
        'integration_id',
        'endpoint',
        'method',
        'http_status',
        'success',
        'attempt',
        'response_time_ms',
        'request_payload',
        'response_payload',
        'error_message',
        'error_code',
    ];

    protected $casts = [
        'success'          => 'boolean',
        'attempt'          => 'integer',
        'http_status'      => 'integer',
        'response_time_ms' => 'integer',
        'request_payload'  => 'array',
        'response_payload' => 'array',
    ];

    // Relationships
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    public function scopeForEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', $endpoint);
    }

    public function scopeRateLimited($query)
    {
        return $query->where('error_code', 'rate_limit');
    }

    // Helpers
    public function isRateLimited(): bool
    {
        return $this->error_code === 'rate_limit';
    }

    public function isAuthError(): bool
    {
        return $this->error_code === 'auth_error';
    }
}
