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
        // 1. Call Queues
        Schema::create('call_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('strategy')->default('ringall'); // ringall, leastrecent, fewestcalls, random, rrmemory
            $table->unsignedInteger('max_wait_seconds')->default(120);
            $table->string('music_on_hold_path')->nullable();
            $table->json('agents')->nullable();
            $table->string('status')->default('active'); // active, paused
            $table->timestamps();
        });

        // 2. Call Dispositions
        Schema::create('call_dispositions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#3B82F6');
            $table->boolean('requires_note')->default(false);
            $table->timestamps();
        });

        // 3. IVR Flows
        Schema::create('ivr_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('nodes')->nullable();
            $table->json('edges')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // 4. Calls
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('caller_number')->index();
            $table->string('callee_number')->index();
            $table->string('direction')->default('inbound'); // inbound, outbound
            $table->string('type')->default('manual'); // manual, campaign, ivr
            $table->string('status')->default('ringing')->index(); // ringing, active, on_hold, transferred, ended, missed, failed
            $table->boolean('is_muted')->default(false);
            $table->boolean('is_recording')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('queue_id')->nullable()->constrained('call_queues')->nullOnDelete();
            $table->string('voip_provider')->default('asterisk');
            $table->string('voip_call_id')->nullable()->index();
            $table->string('voip_channel')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('ring_duration')->default(0);
            $table->string('recording_path')->nullable();
            $table->string('disposition')->nullable();
            $table->text('disposition_note')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('ivr_session_id')->nullable();
            $table->string('transfer_to')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 5. Call Events
        Schema::create('call_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('calls')->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
        });

        // 6. IVR Sessions
        Schema::create('ivr_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('calls')->cascadeOnDelete();
            $table->foreignId('flow_id')->nullable()->constrained('ivr_flows')->nullOnDelete();
            $table->string('current_node_id')->nullable();
            $table->json('collected_data')->nullable();
            $table->string('dtmf_input')->nullable();
            $table->string('intent_detected')->nullable();
            $table->string('language')->default('fa');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('status')->default('active');
        });

        // 7. External Integrations (VoIP, SMS, Payment, AI)
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->index(); // voip, sms, payment, ai
            $table->string('adapter_class');
            $table->text('credentials')->nullable(); // encrypted json
            $table->json('config')->nullable();
            $table->string('status')->default('active'); // active, inactive, error
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('ivr_sessions');
        Schema::dropIfExists('call_events');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('ivr_flows');
        Schema::dropIfExists('call_dispositions');
        Schema::dropIfExists('call_queues');
    }
};
