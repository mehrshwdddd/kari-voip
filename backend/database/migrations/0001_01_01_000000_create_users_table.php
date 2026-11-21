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
        // 1. Company Profile (Single-Tenant: Exactly 1 company profile per installation)
        Schema::create('company_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('VoiceReach OS');
            $table->string('logo')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('national_id')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('timezone')->default('Asia/Tehran');
            $table->json('working_hours')->nullable();
            $table->string('invoice_prefix')->default('VR-');
            $table->unsignedInteger('invoice_counter')->default(1000);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // 2. Branches
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('timezone')->default('Asia/Tehran');
            $table->json('working_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->string('avatar')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->string('status')->default('active'); // active, inactive, suspended
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->json('settings')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // Add foreign key constraint for branch manager now that users table exists
        Schema::table('branches', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
        });

        // 4. User Sessions
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // 5. Password Reset Tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->index();
            $table->string('type')->default('email'); // email, sms
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 6. Login Attempts
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->index();
            $table->string('ip_address', 45);
            $table->boolean('successful')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        // 7. Laravel web sessions (for Horizon / web if needed)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::dropIfExists('sessions');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('company_profile');
    }
};
