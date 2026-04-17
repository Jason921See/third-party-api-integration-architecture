<?php

namespace App\Console\Commands\Tenant;

use App\Integrations\Tenant\ExternalService\IntegrationSyncRegistry;
use App\Models\Central\Tenant;
use App\Models\Tenant\Integration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncIntegrationsCommand extends Command
{
    protected $signature = 'integrations:sync
                                {--tenant=      : Limit to a specific tenant ID}
                                {--provider=    : Limit to a specific provider slug (e.g. facebook)}
                                {--type=        : Limit to a specific object type (e.g. campaign)}
                                {--integration= : Limit to a specific integration ID within the tenant}
                                {--dry-run      : Preview what would be dispatched without running}';

    protected $description = 'Dispatch integration sync jobs for every active integration across all tenants';

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');
            return self::SUCCESS;
        }

        $this->info("Processing {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $this->line("\n<fg=cyan>Tenant: {$tenant->id}</>");

            // ── Switch into tenant DB context ─────────────────
            tenancy()->initialize($tenant);

            try {
                $this->syncForCurrentTenant($tenant->id);
            } catch (\Throwable $e) {
                $this->error("  Error processing tenant [{$tenant->id}]: {$e->getMessage()}");

                Log::error('SyncIntegrationsCommand tenant error', [
                    'tenant_id' => $tenant->id,
                    'error'     => $e->getMessage(),
                ]);
            } finally {
                // ── Always revert to central context ──────────
                tenancy()->end();
            }
        }

        $this->info("\nAll tenants processed.");

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────
    // Per-tenant dispatch logic
    // (runs inside tenant DB context)
    // ─────────────────────────────────────────────

    private function syncForCurrentTenant(string $tenantId): void
    {
        $filterProvider    = $this->option('provider');
        $filterType        = $this->option('type');
        $filterIntegration = $this->option('integration');
        $isDryRun          = $this->option('dry-run');

        $providers = $filterProvider
            ? [$filterProvider]
            : IntegrationSyncRegistry::providers();

        foreach ($providers as $providerSlug) {
            if (!IntegrationSyncRegistry::hasProvider($providerSlug)) {
                continue;
            }

            $integrations = $this->loadIntegrations($providerSlug, $filterIntegration);

            if ($integrations->isEmpty()) {
                $this->line("  [{$providerSlug}] No active integrations.");
                continue;
            }

            $objectTypes = $filterType
                ? [$filterType]
                : IntegrationSyncRegistry::objectTypesFor($providerSlug);

            foreach ($integrations as $integration) {
                foreach ($objectTypes as $objectType) {
                    $jobClass = IntegrationSyncRegistry::jobFor($providerSlug, $objectType);

                    if (!$jobClass) {
                        continue;
                    }

                    if ($isDryRun) {
                        $this->line("  [dry-run] tenant:{$tenantId} {$providerSlug}:{$objectType} → integration #{$integration->id} → {$jobClass}");
                        continue;
                    }

                    // Dispatch into the tenant's queue context.
                    // stancl/tenancy will carry the tenant context into the
                    // queued job automatically via TenantAwareJob middleware
                    // as long as your jobs use the trait or middleware is set up.
                    $jobClass::dispatch($integration->id);

                    $this->line("  ✓ tenant:{$tenantId} [{$providerSlug}:{$objectType}] integration #{$integration->id}");

                    Log::info('SyncIntegrationsCommand dispatched', [
                        'tenant_id'      => $tenantId,
                        'provider'       => $providerSlug,
                        'object_type'    => $objectType,
                        'integration_id' => $integration->id,
                        'job_class'      => $jobClass,
                    ]);
                }
            }
        }
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function resolveTenants()
    {
        $query = Tenant::query();

        if ($tenantId = $this->option('tenant')) {
            $query->where('id', $tenantId);
        }

        return $query->get();
    }

    private function loadIntegrations(string $providerSlug, ?string $integrationId)
    {
        $query = Integration::query()
            ->whereHas('provider', fn($q) => $q->where('slug', $providerSlug))
            ->where('status', 'active');

        if ($integrationId) {
            $query->where('id', $integrationId);
        }

        return $query->get();
    }
}
