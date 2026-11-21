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
        // 1. Contact Lists
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source')->default('csv'); // csv, excel, manual, tags
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('valid_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->string('status')->default('ready'); // processing, ready, failed
            $table->string('file_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // 2. Campaign Workflows
        Schema::create('campaign_workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('name');
            $table->json('nodes')->nullable();
            $table->json('edges')->nullable();
            $table->boolean('is_template')->default(false);
            $table->timestamps();
        });

        // 3. Campaigns
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('audio'); // audio, tts, ivr
            $table->string('audio_path')->nullable();
            $table->text('tts_text')->nullable();
            $table->string('tts_voice')->default('fa-IR-DilaraNeural');
            $table->string('status')->default('draft')->index(); // draft, scheduled, running, paused, completed, cancelled
            $table->foreignId('contact_list_id')->nullable()->constrained('contact_lists')->nullOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained('campaign_workflows')->nullOnDelete();
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->string('allowed_hours_start')->default('09:00');
            $table->string('allowed_hours_end')->default('20:00');
            $table->json('allowed_days')->nullable();
            $table->unsignedInteger('max_concurrent_calls')->default(5);
            $table->unsignedInteger('max_retry_count')->default(2);
            $table->json('retry_interval_minutes')->nullable();
            $table->json('dtmf_options')->nullable();
            $table->boolean('human_transfer_enabled')->default(true);
            $table->boolean('record_calls')->default(false);
            $table->string('caller_id')->default('02188889999');
            $table->unsignedInteger('total_contacts')->default(0);
            $table->unsignedInteger('completed_contacts')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Campaign Contacts
        Schema::create('campaign_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('phone_number')->index();
            $table->string('status')->default('pending')->index(); // pending, queued, calling, completed, failed, skipped_dnc, opted_out
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('final_disposition')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamps();
        });

        // 5. Campaign Call Logs
        Schema::create('campaign_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('campaign_contact_id')->constrained('campaign_contacts')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('phone_number')->index();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->foreignId('call_id')->nullable()->constrained('calls')->nullOnDelete();
            $table->string('voip_call_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('listen_duration_seconds')->default(0);
            $table->unsignedInteger('listen_percentage')->default(0);
            $table->string('dtmf_input')->nullable();
            $table->string('outcome')->default('completed')->index();
            $table->string('agent_transfer_status')->nullable();
            $table->string('recording_path')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 6. Do Not Call List (DNC)
        Schema::create('do_not_call_list', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->string('reason')->nullable();
            $table->string('source')->default('manual'); // manual, dtmf_opt_out, complaint, import
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('do_not_call_list');
        Schema::dropIfExists('campaign_call_logs');
        Schema::dropIfExists('campaign_contacts');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('campaign_workflows');
        Schema::dropIfExists('contact_lists');
    }
};
