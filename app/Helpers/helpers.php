<?php

namespace App\Helpers;

class IntegrationHelper
{
    public static function normalizeAdAccountId(?string $id): ?string
    {
        if (!$id) return null;

        return str_starts_with($id, 'act_') ? $id : "act_{$id}";
    }
}
