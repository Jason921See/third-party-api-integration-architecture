<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Integration;
use App\Models\Tenant\IntegrationInsight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationInsight>
 */
class IntegrationInsightFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = IntegrationInsight::class;

    public function definition(): array
    {
        $level = $this->faker->randomElement(['account', 'campaign', 'adset', 'ad']);

        $accountId  = $this->fakeAdAccountId();
        $campaignId = $this->fakeNumericId();
        $adsetId    = $this->fakeNumericId();
        $adId       = $this->fakeNumericId();

        $impressions = $this->faker->numberBetween(100, 500_000);
        $clicks      = (int) ($impressions * $this->faker->randomFloat(4, 0.01, 0.10));
        $spend       = $this->faker->randomFloat(4, 0.50, 5000.00);
        $reach       = (int) ($impressions * $this->faker->randomFloat(4, 0.60, 0.95));
        $ctr         = $impressions > 0 ? round(($clicks / $impressions) * 100, 4) : 0;
        $cpc         = $clicks > 0 ? round($spend / $clicks, 4) : 0;
        $cpm         = $impressions > 0 ? round(($spend / $impressions) * 1000, 4) : 0;
        $cpp         = $reach > 0 ? round(($spend / $reach) * 1000, 4) : 0;
        $frequency   = $reach > 0 ? round($impressions / $reach, 4) : 1.0;

        $dateStart = $this->faker->dateTimeBetween('-6 months', '-1 day');
        $dateStop  = (clone $dateStart)->modify('+' . $this->faker->numberBetween(0, 6) . ' days');

        return [
            'integration_id'   => Integration::factory(),
            'level'            => $level,

            'account_id'       => $accountId,
            'campaign_id'      => $level !== 'account' ? $campaignId : null,
            'adset_id'         => in_array($level, ['adset', 'ad']) ? $adsetId : null,
            'ad_id'            => $level === 'ad' ? $adId : null,

            // 'account_name'     => $this->faker->company() . ' Ads',
            // 'campaign_name'    => $level !== 'account' ? $this->fakeCampaignName() : null,
            // 'adset_name'       => in_array($level, ['adset', 'ad']) ? $this->fakeAdsetName() : null,
            // 'ad_name'          => $level === 'ad' ? $this->fakeAdName() : null,

            'date_start'       => $dateStart->format('Y-m-d'),
            'date_stop'        => $dateStop->format('Y-m-d'),

            'account_currency' => $this->faker->randomElement(['USD', 'MYR', 'SGD', 'EUR', 'GBP']),

            'impressions'      => $impressions,
            'clicks'           => $clicks,
            'reach'            => $reach,
            'spend'            => $spend,
            'cpc'              => $cpc,
            'cpm'              => $cpm,
            'ctr'              => $ctr,
            'cpp'              => $cpp,
            'frequency'        => $frequency,

            'actions'          => $this->fakeActions($clicks),
            'action_values'    => $this->fakeActionValues($spend),

            'raw'              => $this->fakeRawPayload(
                $accountId,
                $campaignId,
                $adsetId,
                $adId,
                $impressions,
                $spend,
                $dateStart->format('Y-m-d'),
                $dateStop->format('Y-m-d')
            ),

            'fetched_at'       => now(),
        ];
    }

    // ── Named states ───────────────────────────────────────────

    public function accountLevel(): static
    {
        return $this->state(fn() => [
            'level'         => 'account',
            'campaign_id'   => null,
            'adset_id'      => null,
            'ad_id'         => null,
            'campaign_name' => null,
            'adset_name'    => null,
            'ad_name'       => null,
        ]);
    }

    public function campaignLevel(): static
    {
        return $this->state(fn() => [
            'level'       => 'campaign',
            'adset_id'    => null,
            'ad_id'       => null,
            'adset_name'  => null,
            'ad_name'     => null,
        ]);
    }

    public function adsetLevel(): static
    {
        return $this->state(fn() => [
            'level'  => 'adset',
            'ad_id'  => null,
            'ad_name' => null,
        ]);
    }

    public function adLevel(): static
    {
        return $this->state(fn() => ['level' => 'ad']);
    }

    /** High-spend insight for sort/filter tests */
    public function highSpend(float $spend = 5000.00): static
    {
        return $this->state(fn() => ['spend' => $spend]);
    }

    /** Zero-spend insight (e.g. paused campaigns) */
    public function zeroSpend(): static
    {
        return $this->state(fn() => [
            'spend' => '0.0000',
            'cpc'   => '0.0000',
            'cpm'   => '0.0000',
            'cpp'   => '0.0000',
        ]);
    }

    /** Pin exact date range for date-filter tests */
    public function forDateRange(string $dateStart, string $dateStop): static
    {
        return $this->state(fn() => [
            'date_start' => $dateStart,
            'date_stop'  => $dateStop,
        ]);
    }

    /** Mirror the exact shape Facebook Graph API returns */
    public function fromFacebookPayload(): static
    {
        return $this->state(function () {
            $accountId  = $this->fakeAdAccountId();
            $campaignId = $this->fakeNumericId();
            $adsetId    = $this->fakeNumericId();
            $adId       = $this->fakeNumericId();

            return [
                'level'         => 'ad',
                'account_id'    => $accountId,
                'campaign_id'   => $campaignId,
                'adset_id'      => $adsetId,
                'ad_id'         => $adId,
                'impressions'   => '100',
                'spend'         => '1.4300',
                'date_start'    => '2025-06-23',
                'date_stop'     => '2025-06-23',
                'raw'           => [
                    'account_id'  => $accountId,
                    'campaign_id' => $campaignId,
                    'adset_id'    => $adsetId,
                    'ad_id'       => $adId,
                    'impressions' => '100',
                    'spend'       => '1.43',
                    'date_start'  => '2025-06-23',
                    'date_stop'   => '2025-06-23',
                ],
            ];
        });
    }

    // ── Private helpers ────────────────────────────────────────

    /** Facebook ad account IDs are always prefixed with act_ */
    private function fakeAdAccountId(): string
    {
        return 'act_' . $this->faker->numerify('##############');
    }

    /** Campaign / adset / ad IDs are long numeric strings */
    private function fakeNumericId(): string
    {
        return $this->faker->numerify('##################');
    }

    private function fakeCampaignName(): string
    {
        $objectives = ['Awareness', 'Traffic', 'Engagement', 'Leads', 'Sales', 'Retargeting'];
        return $this->faker->randomElement($objectives) . ' - ' . $this->faker->words(2, true);
    }

    private function fakeAdsetName(): string
    {
        $targets = ['18-24', '25-34', '35-44', 'Lookalike', 'Custom Audience', 'Interest'];
        return $this->faker->randomElement($targets) . ' | ' . $this->faker->country();
    }

    private function fakeAdName(): string
    {
        $formats = ['Static', 'Video', 'Carousel', 'Story', 'Reel'];
        return $this->faker->randomElement($formats) . ' v' . $this->faker->numberBetween(1, 5);
    }

    private function fakeActions(int $clicks): array
    {
        $conversions = (int) ($clicks * $this->faker->randomFloat(2, 0.01, 0.05));

        return [
            ['action_type' => 'link_click',         'value' => (string) $clicks],
            ['action_type' => 'purchase',            'value' => (string) $conversions],
            ['action_type' => 'add_to_cart',         'value' => (string) ($conversions * 3)],
            ['action_type' => 'view_content',        'value' => (string) ($clicks * 2)],
            ['action_type' => 'initiate_checkout',   'value' => (string) ($conversions * 2)],
        ];
    }

    private function fakeActionValues(float $spend): array
    {
        $revenue = $spend * $this->faker->randomFloat(2, 1.5, 6.0);

        return [
            ['action_type' => 'purchase',  'value' => number_format($revenue, 2, '.', '')],
            ['action_type' => 'add_to_cart', 'value' => number_format($revenue * 0.4, 2, '.', '')],
        ];
    }

    private function fakeRawPayload(
        string $accountId,
        string $campaignId,
        string $adsetId,
        string $adId,
        int $impressions,
        float $spend,
        string $dateStart,
        string $dateStop,
    ): array {
        return [
            'account_id'  => $accountId,
            'campaign_id' => $campaignId,
            'adset_id'    => $adsetId,
            'ad_id'       => $adId,
            'date_start'  => $dateStart,
            'date_stop'   => $dateStop,
            'impressions' => (string) $impressions,
            'spend'       => number_format($spend, 2, '.', ''),
        ];
    }
}
