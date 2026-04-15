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
        Schema::create('integration_ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');
            $table->string('campaign_id');
            $table->string('account_id')->nullable();
            $table->string('name')->nullable();
            $table->string('objective')->nullable();
            $table->string('buying_type')->nullable();
            $table->unsignedBigInteger('daily_budget')->nullable();
            $table->unsignedBigInteger('lifetime_budget')->nullable();
            $table->unsignedBigInteger('spend_cap')->nullable();
            $table->string('bid_strategy')->nullable();
            $table->json('pacing_type')->nullable();
            $table->string('status')->nullable();
            $table->string('effective_status')->nullable();
            $table->json('promoted_object')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('issues_info')->nullable();
            $table->json('adlabels')->nullable();
            $table->json('special_ad_categories')->nullable();
            $table->json('special_ad_category_country')->nullable();
            $table->string('smart_promotion_type')->nullable();
            $table->boolean('is_skadnetwork_attribution')->default(false);
            $table->timestamp('fb_start_time')->nullable();
            $table->timestamp('fb_stop_time')->nullable();
            $table->timestamp('fb_created_time')->nullable();
            $table->timestamp('fb_updated_time')->nullable();
            $table->json('raw')->nullable();                    // Full raw API response
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('integration_id')
                ->references('id')
                ->on('integrations')
                ->cascadeOnDelete();

            // One campaign row per integration
            $table->unique(['integration_id', 'campaign_id']);

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
        Schema::dropIfExists('integration_ad_campaigns');
    }
};
