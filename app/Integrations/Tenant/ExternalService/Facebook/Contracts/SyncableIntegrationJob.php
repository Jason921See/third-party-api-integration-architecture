<?php

namespace App\Integrations\Tenant\ExternalService\Facebook\Contracts;

/**
 * All integration sync jobs must implement this interface.
 *
 * The command dispatches jobs by calling:
 *   $jobClass::dispatch($integrationId)
 *
 * So every registered job must accept integrationId as its
 * first (and only required) constructor argument.
 */
interface SyncableIntegrationJob
{
    public function __construct(int $integrationId);
}
