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
        Schema::create('integration_insights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');

            // ── Level + object identity ───────────────────────────────────────────
            $table->string('level');                          // account | campaign | adset | ad
            $table->string('account_id', 30)->nullable();
            $table->string('campaign_id', 30)->nullable();
            $table->string('adset_id', 30)->nullable();
            $table->string('ad_id', 30)->nullable();
            $table->string('object_name')->nullable();

            // ── Date range ────────────────────────────────────────────────────────
            $table->date('date_start');
            $table->date('date_stop');

            // ── Account meta ──────────────────────────────────────────────────────
            $table->string('account_currency', 10)->nullable();

            // ── Core metrics ──────────────────────────────────────────────────────
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->decimal('spend', 12, 4)->default(0);

            // ── Rate metrics ──────────────────────────────────────────────────────
            $table->decimal('cpc', 10, 4)->default(0);
            $table->decimal('cpm', 10, 4)->default(0);
            $table->decimal('ctr', 10, 4)->default(0);
            $table->decimal('cpp', 10, 4)->default(0);
            $table->decimal('frequency', 10, 4)->default(0);

            // ── Action arrays ─────────────────────────────────────────────────────
            $table->json('actions')->nullable();
            $table->json('action_values')->nullable();

            // ── Raw + sync ────────────────────────────────────────────────────────
            $table->json('raw')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            // ── Constraints ───────────────────────────────────────────────────────
            $table->foreign('integration_id')
                ->references('id')
                ->on('integrations')
                ->cascadeOnDelete();

            // One row per integration + level + object + date range
            // $table->unique(['integration_id', 'level', 'object_id', 'date_start', 'date_stop'], 'insights_unique');
            $table->unique(['integration_id', 'date_start', 'date_stop', 'account_id', 'campaign_id', 'adset_id', 'ad_id'], 'insights_unique');

            $table->index('level');
            $table->index('date_start');
            $table->index('date_stop');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_insights');
    }
};
