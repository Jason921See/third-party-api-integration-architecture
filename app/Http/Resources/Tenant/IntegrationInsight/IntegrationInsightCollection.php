<?php

namespace App\Http\Resources\Tenant\IntegrationInsight;

use App\Http\Resources\Tenant\BaseCollection;

class IntegrationInsightCollection extends BaseCollection
{
    public $collects = IntegrationInsightResource::class;
}
