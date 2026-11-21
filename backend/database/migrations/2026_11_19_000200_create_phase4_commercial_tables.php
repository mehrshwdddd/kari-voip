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
        // =====================================================================
        // A. APPOINTMENT BOOKING ECOSYSTEM
        // =====================================================================

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->unsignedInteger('buffer_minutes')->default(10);
            $table->decimal('price', 14, 2)->default(0);
            $table->string('color')->default('#3B82F6');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('day_of_week'); // saturday..friday
            $table->string('start_time')->default('09:00');
            $table->string('end_time')->default('17:00');
            $table->boolean('is_working')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('day_of_week');
            $table->string('start_time');
            $table->string('end_time');
            $table->string('label')->nullable();
        });

        Schema::create('staff_time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date')->index();
            $table->boolean('is_recurring')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('booking_number')->unique();
            $table->string('status')->default('pending')->index(); // pending, confirmed, completed, cancelled, no_show, rescheduled
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('payment_status')->default('unpaid'); // unpaid, partial, paid
            $table->string('source')->default('manual'); // call, online, manual, campaign
            $table->foreignId('call_id')->nullable()->constrained('calls')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('recurring_rule')->nullable();
            $table->unsignedBigInteger('parent_appointment_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('channel')->default('sms'); // sms, whatsapp, email
            $table->string('type')->default('24h'); // 24h, 1h, custom
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->text('message')->nullable();
        });

        // =====================================================================
        // B. BILLING, INVOICES & ACCOUNTING LOGS ECOSYSTEM
        // =====================================================================

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('status')->default('draft')->index(); // draft, sent, partial, paid, overdue, cancelled, refunded
            $table->string('type')->default('service'); // service, product, campaign, subscription, custom
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('remaining_amount', 14, 2)->default(0);
            $table->string('currency')->default('IRR');
            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(9);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method')->default('card'); // cash, card, transfer, cheque, credit, gateway
            $table->string('gateway_name')->nullable();
            $table->string('gateway_ref')->nullable();
            $table->text('gateway_response')->nullable(); // encrypted:array
            $table->string('status')->default('completed'); // pending, completed, failed, refunded
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('cheque_number');
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('due_date');
            $table->string('status')->default('pending'); // pending, deposited, cleared, bounced, returned
            $table->timestamp('deposited_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('reference_type'); // invoice, payment, refund, campaign
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('balance_after', 14, 2)->default(0);
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('color_scheme')->default('#1E40AF');
            $table->boolean('show_tax')->default(true);
            $table->boolean('show_discount')->default(true);
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('reason')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('applied_to_invoice_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // =====================================================================
        // C. SMS & MESSAGING AUTOMATION ECOSYSTEM
        // =====================================================================

        Schema::create('sms_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('adapter_class'); // kavenegar, ghasedak
            $table->text('credentials')->nullable(); // encrypted:array
            $table->string('sender_number');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(1);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel')->default('sms'); // sms, whatsapp, email, push
            $table->string('category')->default('custom'); // appointment, invoice, campaign, custom
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('channel')->default('sms');
            $table->string('to_address')->index();
            $table->string('from_address')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status')->default('sent')->index(); // pending, sent, delivered, failed, read
            $table->string('provider')->default('kavenegar');
            $table->string('provider_ref')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('sms_providers');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoice_templates');
        Schema::dropIfExists('accounting_logs');
        Schema::dropIfExists('cheques');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('appointment_reminders');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('staff_time_offs');
        Schema::dropIfExists('staff_breaks');
        Schema::dropIfExists('staff_schedules');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('services');
    }
};
