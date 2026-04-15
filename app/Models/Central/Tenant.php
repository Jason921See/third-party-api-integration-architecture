<?php

namespace App\Models\Central;

use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant  implements TenantWithDatabase
{
    use HasDatabase, HasDomains;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'created_at',
        'updated_at',
        'tenancy_db_name',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }
}
