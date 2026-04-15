<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');

            // ── What ran ──────────────────────────────────────────────────────────
            $table->string('provider');                        // facebook, google_ads, tiktok
            $table->string('job_type');                        // insight, campaign, adset, ad
            $table->string('level')->nullable();               // account | campaign | adset | ad

            // ── Status ────────────────────────────────────────────────────────────
            $table->string('status');                          // pending | running | completed | failed
            $table->unsignedInteger('attempt')->default(1);    // which retry attempt

            // ── Date range requested ──────────────────────────────────────────────
            $table->date('date_start')->nullable();
            $table->date('date_stop')->nullable();
            $table->string('date_preset')->nullable();

            // ── Results ───────────────────────────────────────────────────────────
            $table->unsignedInteger('records_synced')->default(0);
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();          // extra debug info

            // ── Timing ────────────────────────────────────────────────────────────
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->timestamps();

            $table->foreign('integration_id')
                ->references('id')
                ->on('integrations')
                ->cascadeOnDelete();

            $table->index(['integration_id', 'status']);
            $table->index(['integration_id', 'job_type']);
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_jobs');
    }
};
