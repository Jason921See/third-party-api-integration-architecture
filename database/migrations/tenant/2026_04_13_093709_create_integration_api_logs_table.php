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
        Schema::create('integration_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->string('endpoint');               // e.g. "/act_xxx/campaigns"
            $table->string('method')->default('GET'); // GET, POST, DELETE
            $table->integer('http_status')->nullable();
            $table->boolean('success')->default(false);
            $table->integer('attempt')->default(1);   // retry attempt number
            $table->integer('response_time_ms')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_code')->nullable();  // rate_limit, auth_error, etc.
            $table->timestamps();

            $table->index(['integration_id', 'created_at']);
            $table->index(['success']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_api_logs');
    }
};
