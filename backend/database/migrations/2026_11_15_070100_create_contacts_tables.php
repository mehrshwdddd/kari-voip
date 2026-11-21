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
        // 1. Contact Tags
        Schema::create('contact_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->default('#3B82F6');
            $table->timestamp('created_at')->useCurrent();
        });

        // 2. Contacts
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('national_id')->nullable();
            $table->string('phone_primary')->index();
            $table->json('phone_secondary')->nullable();
            $table->string('email')->nullable();
            $table->string('gender')->nullable(); // male, female, other
            $table->date('birth_date')->nullable();
            $table->string('avatar')->nullable();
            $table->string('status')->default('active'); // active, vip, blocked, inactive
            $table->string('source')->default('walk_in'); // call, walk_in, referral, campaign, online
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->decimal('credit_balance', 12, 2)->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->decimal('total_debt', 12, 2)->default(0);
            $table->timestamp('last_contact_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Contact Tag Pivot
        Schema::create('contact_tag_pivot', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('contact_tags')->cascadeOnDelete();
            $table->primary(['contact_id', 'tag_id']);
        });

        // 4. Contact Notes
        Schema::create('contact_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->string('type')->default('note'); // note, task, follow_up
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 5. Contact Files
        Schema::create('contact_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at')->useCurrent();
        });

        // 6. Contact Merges
        Schema::create('contact_merges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('primary_contact_id');
            $table->unsignedBigInteger('merged_contact_id');
            $table->foreignId('merged_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('merged_at')->useCurrent();

            $table->foreign('primary_contact_id')->references('id')->on('contacts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_merges');
        Schema::dropIfExists('contact_files');
        Schema::dropIfExists('contact_notes');
        Schema::dropIfExists('contact_tag_pivot');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_tags');
    }
};
