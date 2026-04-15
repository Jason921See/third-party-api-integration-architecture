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
        Schema::create('integration_ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');

            // ── Facebook IDs (stored as strings — Facebook IDs exceed int range) ──
            $table->string('ad_id');
            $table->string('adset_id')->nullable();
            $table->string('campaign_id')->nullable();

            // ── Core fields ──────────────────────────────────────────────────────
            $table->string('name')->nullable();
            $table->string('status')->nullable();               // ACTIVE, PAUSED, ARCHIVED, DELETED
            $table->string('effective_status')->nullable();     // ACTIVE, PAUSED, CAMPAIGN_PAUSED, etc.

            // ── JSON objects ─────────────────────────────────────────────────────
            $table->json('creative')->nullable();               // { id, instagram_permalink_url, effective_instagram_media_id }
            $table->json('tracking_specs')->nullable();         // array of tracking spec objects
            $table->json('conversion_specs')->nullable();       // array of conversion spec objects

            // ── Facebook timestamps ───────────────────────────────────────────────
            $table->timestamp('fb_created_time')->nullable();
            $table->timestamp('fb_updated_time')->nullable();

            // ── Sync metadata ────────────────────────────────────────────────────
            $table->json('raw')->nullable();                    // Full raw API response
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // ── Constraints ───────────────────────────────────────────────────────
            $table->foreign('integration_id')
                ->references('id')
                ->on('integrations')
                ->cascadeOnDelete();

            $table->unique(['integration_id', 'ad_id']);

            $table->index('adset_id');
            $table->index('campaign_id');
            $table->index('status');
            $table->index('effective_status');
            $table->index('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_ads');
    }
};
