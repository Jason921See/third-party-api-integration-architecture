<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'name',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array', // Cast value to array for easier handling of complex settings
    ];
}
