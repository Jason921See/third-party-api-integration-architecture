<?php

namespace App\Jobs\Concerns\Tenant;

use App\Models\Tenant\IntegrationJob;

/**
 * Drop this trait into any sync job to get automatic tracking.
 *
 * The job class must define:
 *   - $this->integrationId  (int)
 *   - $this->jobType        (string)  e.g. 'insight', 'campaign', 'ad'
 *   - $this->provider       (string)  e.g. 'facebook'
 *
 * Optional properties (used if present):
 *   - $this->level          (string)  e.g. 'campaign', 'ad'
 *   - $this->dateStart      (string)
 *   - $this->dateStop       (string)
 *   - $this->datePreset     (string)
 */
trait TrackIntegrationJob
{
    private ?IntegrationJob $integrationJob = null;

    // ─────────────────────────────────────────────
    // Call at the top of handle()
    // ─────────────────────────────────────────────

    protected function startTracking(): IntegrationJob
    {
        $this->integrationJob = IntegrationJob::create([
            'integration_id' => $this->integrationId,
            'provider'       => $this->provider ?? 'unknown',
            'job_type'       => $this->jobType ?? 'unknown',
            'level'          => $this->level ?? null,
            'status'         => IntegrationJob::STATUS_RUNNING,
            'attempt'        => $this->attempts(),
            'date_start'     => $this->dateStart ?? null,
            'date_stop'      => $this->dateStop ?? null,
            'date_preset'    => $this->datePreset ?? null,
            'started_at'     => now(),
        ]);

        return $this->integrationJob;
    }

    // ─────────────────────────────────────────────
    // Call at the bottom of handle()
    // ─────────────────────────────────────────────

    protected function completeTracking(int $recordsSynced = 0): void
    {
        $this->integrationJob?->markCompleted($recordsSynced);
    }

    // ─────────────────────────────────────────────
    // Call inside failed()
    // ─────────────────────────────────────────────

    protected function failTracking(string $message, array $context = []): void
    {
        // Find the last running job for this integration + type
        // in case $this->integrationJob was not set (e.g. job was retried fresh)
        $job = $this->integrationJob ?? IntegrationJob::where('integration_id', $this->integrationId)
            ->where('job_type', $this->jobType ?? 'unknown')
            ->where('status', IntegrationJob::STATUS_RUNNING)
            ->latest()
            ->first();

        $job?->markFailed($message, $context);
    }
}
