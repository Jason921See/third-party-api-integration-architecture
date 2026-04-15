<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class IntegrationProvider extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'scopes',
        'config',
        'is_active',
    ];

    protected $casts = [
        'scopes'    => 'array',
        'config'    => 'array',
        'is_active' => 'boolean',
    ];

    public function integrations()
    {
        return $this->hasMany(Integration::class);
    }
}
