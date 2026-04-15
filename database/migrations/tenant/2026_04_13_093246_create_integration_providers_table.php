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
        Schema::create('integration_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // e.g. "Facebook Marketing API"
            $table->string('slug')->unique();          // e.g. "facebook", "google", "tiktok"
            $table->string('type');                    // e.g. "oauth2", "api_key"
            $table->json('scopes')->nullable();        // required OAuth scopes
            $table->json('config')->nullable();        // provider-specific config
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_providers');
    }
};
