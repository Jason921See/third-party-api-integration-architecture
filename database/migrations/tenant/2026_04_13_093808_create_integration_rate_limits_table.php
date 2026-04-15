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
        Schema::create('integration_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->string('endpoint')->nullable();          // specific endpoint or null for global
            $table->integer('requests_made')->default(0);
            $table->integer('requests_limit')->nullable();   // max allowed
            $table->integer('retry_after')->nullable();      // seconds to wait
            $table->timestamp('window_resets_at')->nullable(); // when limit resets
            $table->timestamp('blocked_until')->nullable();  // backoff block until
            $table->timestamps();

            $table->unique(['integration_id', 'endpoint']);
            $table->index('blocked_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_rate_limits');
    }
};
