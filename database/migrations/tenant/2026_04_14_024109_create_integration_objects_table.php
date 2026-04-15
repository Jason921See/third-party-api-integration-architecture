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
        Schema::create('integration_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->string('object_type');              // campaign, adset, ad, account
            $table->string('object_id');                // Facebook's ID
            $table->string('object_name')->nullable();
            $table->string('status')->nullable();       // ACTIVE, PAUSED, ARCHIVED
            $table->string('effective_status')->nullable(); // WITH_ISSUES, ACTIVE etc
            $table->string('parent_object_id')->nullable(); // campaign_id for adset, adset_id for ad
            $table->string('parent_object_type')->nullable(); // campaign, adset
            $table->json('meta')->nullable();           // extra fields per object type
            $table->timestamp('synced_at')->nullable(); // last time synced from Facebook
            $table->timestamps();

            $table->unique(['integration_id', 'object_type', 'object_id'], 'unique_object');
            $table->index(['integration_id', 'object_type']);
            $table->index(['parent_object_id', 'parent_object_type']);
            $table->index('object_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_objects');
    }
};
