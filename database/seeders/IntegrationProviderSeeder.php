<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant\IntegrationProvider;

class IntegrationProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name'       => 'Facebook Marketing API',
                'slug'       => 'facebook',
                'type'       => 'oauth2',
                'scopes'     => ([
                    'ads_read',
                    'ads_management',
                    'business_management',
                    'read_insights',
                ]),
                'config'     => ([
                    'api_version'    => 'v19.0',
                    'base_url'       => 'https://graph.facebook.com',
                    'auth_url'       => 'https://www.facebook.com/v19.0/dialog/oauth',
                    'token_url'      => 'https://graph.facebook.com/v19.0/oauth/access_token',
                    'debug_url'      => 'https://graph.facebook.com/debug_token',
                    'required_scopes' => ['ads_read'],
                    'rate_limit'     => [
                        'requests_per_hour' => 200,
                        'retry_after'       => 3600,
                    ],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Google Ads API',
                'slug'       => 'google',
                'type'       => 'oauth2',
                'scopes'     => ([
                    'https://www.googleapis.com/auth/adwords',
                    'https://www.googleapis.com/auth/analytics.readonly',
                ]),
                'config'     => ([
                    'api_version'    => 'v16',
                    'base_url'       => 'https://googleads.googleapis.com',
                    'auth_url'       => 'https://accounts.google.com/o/oauth2/auth',
                    'token_url'      => 'https://oauth2.googleapis.com/token',
                    'required_scopes' => ['https://www.googleapis.com/auth/adwords'],
                    'rate_limit'     => [
                        'requests_per_day' => 15000,
                        'retry_after'      => 86400,
                    ],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'TikTok Marketing API',
                'slug'       => 'tiktok',
                'type'       => 'oauth2',
                'scopes'     => ([
                    'ad.read',
                    'ad.write',
                    'report.read',
                    'account.read',
                ]),
                'config'     => ([
                    'api_version'    => 'v1.3',
                    'base_url'       => 'https://business-api.tiktok.com/open_api',
                    'auth_url'       => 'https://ads.tiktok.com/marketing_api/auth',
                    'token_url'      => 'https://business-api.tiktok.com/open_api/v1.3/oauth2/access_token',
                    'required_scopes' => ['ad.read'],
                    'rate_limit'     => [
                        'requests_per_minute' => 600,
                        'retry_after'         => 60,
                    ],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Twitter / X Ads API',
                'slug'       => 'twitter',
                'type'       => 'oauth2',
                'scopes'     => ([
                    'ads:read',
                    'ads:write',
                    'tweet.read',
                    'users.read',
                ]),
                'config'     => ([
                    'api_version'    => '12',
                    'base_url'       => 'https://ads-api.twitter.com',
                    'auth_url'       => 'https://twitter.com/i/oauth2/authorize',
                    'token_url'      => 'https://api.twitter.com/2/oauth2/token',
                    'required_scopes' => ['ads:read'],
                    'rate_limit'     => [
                        'requests_per_minute' => 300,
                        'retry_after'         => 60,
                    ],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'LinkedIn Marketing API',
                'slug'       => 'linkedin',
                'type'       => 'oauth2',
                'scopes'     => ([
                    'r_ads',
                    'rw_ads',
                    'r_ads_reporting',
                    'r_basicprofile',
                ]),
                'config'     => ([
                    'api_version'    => 'v2',
                    'base_url'       => 'https://api.linkedin.com',
                    'auth_url'       => 'https://www.linkedin.com/oauth/v2/authorization',
                    'token_url'      => 'https://www.linkedin.com/oauth/v2/accessToken',
                    'required_scopes' => ['r_ads'],
                    'rate_limit'     => [
                        'requests_per_day' => 100000,
                        'retry_after'      => 86400,
                    ],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Snapchat Marketing API',
                'slug'       => 'snapchat',
                'type'       => 'oauth2',
                'scopes'     => ([
                    'snapchat-marketing-api',
                    'snapchat-marketing-api.read',
                ]),
                'config'     => ([
                    'api_version'    => 'v1',
                    'base_url'       => 'https://adsapi.snapchat.com',
                    'auth_url'       => 'https://accounts.snapchat.com/login/oauth2/authorize',
                    'token_url'      => 'https://accounts.snapchat.com/login/oauth2/access_token',
                    'required_scopes' => ['snapchat-marketing-api.read'],
                    'rate_limit'     => [
                        'requests_per_minute' => 1000,
                        'retry_after'         => 60,
                    ],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($providers as $provider) {
            IntegrationProvider::updateOrCreate(
                ['slug' => $provider['slug']],
                $provider,
            );
        }

        $this->command->info('Integration providers seeded: ' . count($providers) . ' providers.');
    }
}
