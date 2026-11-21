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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->text('secret');
            $table->json('events'); // array of event keys, e.g. ['call.started', 'invoice.paid', '*']
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('retry_count')->default(3);
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('webhooks')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
