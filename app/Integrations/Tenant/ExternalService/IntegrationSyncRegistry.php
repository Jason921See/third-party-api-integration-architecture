<?php

namespace App\Integrations\Tenant\ExternalService;

use InvalidArgumentException;

class IntegrationSyncRegistry
{
    /**
     * Map of provider slug => object type => job class.
     *
     * To add a new integration or object type, just add an entry here.
     * The job class must implement SyncableIntegrationJob.
     *
     * ─────────────────────────────────────────────────────────────────
     *  'provider_slug' => [
     *      'object_type' => JobClass::class,
     *  ]
     * ─────────────────────────────────────────────────────────────────
     */
    private static array $registry = [
        'facebook' => [
            'campaign' => \App\Jobs\Tenant\FetchFacebookCampaignsJob::class,
            // 'adset'    => \App\Jobs\Tenant\FetchFacebookAdSetsJob::class,
            // 'ad'       => \App\Jobs\Tenant\FetchFacebookAdsJob::class,
        ],

        // Add new providers here — no other files need to change
        // 'google_ads' => [
        //     'campaign' => \App\Jobs\Tenant\FetchGoogleAdsCampaignsJob::class,
        //     'adset'    => \App\Jobs\Tenant\FetchGoogleAdsAdGroupsJob::class,
        //     'ad'       => \App\Jobs\Tenant\FetchGoogleAdsAdsJob::class,
        // ],
        //
        // 'tiktok' => [
        //     'campaign' => \App\Jobs\Tenant\FetchTikTokCampaignsJob::class,
        //     'ad'       => \App\Jobs\Tenant\FetchTikTokAdsJob::class,
        // ],
    ];

    /**
     * Get all object types registered for a provider.
     */
    public static function objectTypesFor(string $providerSlug): array
    {
        return array_keys(self::$registry[$providerSlug] ?? []);
    }

    /**
     * Get the job class for a given provider + object type.
     * Returns null if not registered (soft — caller decides whether to warn/skip).
     */
    public static function jobFor(string $providerSlug, string $objectType): ?string
    {
        return self::$registry[$providerSlug][$objectType] ?? null;
    }

    /**
     * Get all registered provider slugs.
     */
    public static function providers(): array
    {
        return array_keys(self::$registry);
    }

    /**
     * Check if a provider is registered.
     */
    public static function hasProvider(string $providerSlug): bool
    {
        return isset(self::$registry[$providerSlug]);
    }

    /**
     * Register a new provider/object_type → job mapping at runtime.
     * Useful for package-based or plugin integrations.
     */
    public static function register(string $providerSlug, string $objectType, string $jobClass): void
    {
        if (!class_exists($jobClass)) {
            throw new InvalidArgumentException("Job class [{$jobClass}] does not exist.");
        }

        self::$registry[$providerSlug][$objectType] = $jobClass;
    }
}
