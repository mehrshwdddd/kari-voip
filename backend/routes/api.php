<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DeveloperController;
use App\Http\Controllers\Api\V1\DncController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\IvrFlowController;
use App\Http\Controllers\Api\V1\MessagingController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SetupController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VoipConfigController;
use App\Http\Controllers\Api\V1\VoipWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - VoiceReach OS (v1)
|--------------------------------------------------------------------------
| All routes prefixed with /api/v1/ via bootstrap/app.php
*/

// Public Authentication Routes (Rate limited)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('login/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
    Route::post('login/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth');
    Route::post('2fa/verify', [AuthController::class, 'verify2fa'])->middleware('throttle:auth');
});

// System Setup & Installation Wizard (Unauthenticated)
Route::prefix('setup')->group(function () {
    Route::get('status', [SetupController::class, 'status']);
    Route::post('install', [SetupController::class, 'install']);
});

// VoIP Webhook (HMAC-SHA256 + Timestamp + Idempotency protected)
Route::post('calls/webhook', [VoipWebhookController::class, 'handle'])->middleware('throttle:webhook');

// Cryptographically Signed Call Recording Stream
Route::get('calls/{call}/recording/stream', [CallController::class, 'streamRecording'])
    ->name('api.v1.calls.recording.stream');

// Authenticated Routes
Route::middleware(['auth:sanctum', 'throttle:api', 'audit'])->group(function () {
    // Auth profile & session
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('2fa/enable', [AuthController::class, 'enable2fa']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // Company Profile
    Route::get('company', [CompanyController::class, 'show']);
    Route::put('company', [CompanyController::class, 'update']);

    // Branches
    Route::apiResource('branches', BranchController::class);

    // Users & Roles
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::apiResource('users', UserController::class);

    Route::get('roles', [RoleController::class, 'roles']);
    Route::get('permissions', [RoleController::class, 'permissions']);

    // Contacts CRM & Wallet
    Route::get('tags', [ContactController::class, 'tags']);
    Route::post('tags', [ContactController::class, 'storeTag']);
    Route::get('contacts/{contact}/timeline', [ContactController::class, 'timeline']);
    Route::post('contacts/{contact}/notes', [ContactController::class, 'addNote']);
    Route::post('contacts/{contact}/wallet/topup', [InvoiceController::class, 'topupWallet']);
    Route::apiResource('contacts', ContactController::class);

    // Telephony Core & Receptionist Workspace
    Route::prefix('calls')->group(function () {
        Route::get('active', [CallController::class, 'active']);
        Route::get('dispositions/list', [CallController::class, 'dispositions']);
        Route::get('queues/list', [CallController::class, 'queues']);
        Route::post('click-to-call', [CallController::class, 'clickToCall']);
        Route::post('simulate-inbound', [CallController::class, 'simulateInbound']);

        Route::get('/', [CallController::class, 'index']);
        Route::get('{call}', [CallController::class, 'show']);
        Route::post('{call}/answer', [CallController::class, 'answer']);
        Route::post('{call}/reject', [CallController::class, 'reject']);
        Route::post('{call}/end', [CallController::class, 'end']);
        Route::post('{call}/hold', [CallController::class, 'hold']);
        Route::post('{call}/mute', [CallController::class, 'mute']);
        Route::post('{call}/transfer', [CallController::class, 'transfer']);
        Route::post('{call}/recording/start', [CallController::class, 'startRecording']);
        Route::post('{call}/recording/stop', [CallController::class, 'stopRecording']);
        Route::post('{call}/disposition', [CallController::class, 'disposition']);
        Route::post('{call}/note', [CallController::class, 'addNote']);
        Route::get('{call}/recording', [CallController::class, 'recordingUrl']);
    });

    // VoIP Connection Wizard & Config
    Route::prefix('voip')->group(function () {
        Route::get('config', [VoipConfigController::class, 'show']);
        Route::put('config', [VoipConfigController::class, 'update']);
        Route::post('test-connection', [VoipConfigController::class, 'testConnection']);
    });

    // Outbound Voice Campaigns & DNC (Phase 3)
    Route::post('contact-lists', [CampaignController::class, 'importContactList']);
    Route::get('dnc', [DncController::class, 'index']);
    Route::post('dnc', [DncController::class, 'store']);
    Route::delete('dnc/{dnc}', [DncController::class, 'destroy']);

    Route::prefix('campaigns')->group(function () {
        Route::post('{campaign}/start', [CampaignController::class, 'start']);
        Route::post('{campaign}/pause', [CampaignController::class, 'pause']);
        Route::post('{campaign}/resume', [CampaignController::class, 'resume']);
        Route::post('{campaign}/cancel', [CampaignController::class, 'cancel']);
        Route::post('{campaign}/test-call', [CampaignController::class, 'testCall']);
        Route::get('{campaign}/contacts', [CampaignController::class, 'contacts']);
        Route::get('{campaign}/logs', [CampaignController::class, 'logs']);
        Route::get('{campaign}/report', [CampaignController::class, 'report']);
        Route::get('{campaign}/export', [CampaignController::class, 'export']);
    });
    Route::apiResource('campaigns', CampaignController::class);

    // Appointments, Services & Staff (Phase 4)
    Route::get('appointments/availability', [AppointmentController::class, 'availability']);
    Route::post('appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
    Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete']);
    Route::post('appointments/{appointment}/no-show', [AppointmentController::class, 'noShow']);
    Route::apiResource('appointments', AppointmentController::class);

    Route::get('services', [AppointmentController::class, 'services']);
    Route::post('services', [AppointmentController::class, 'storeService']);
    Route::get('staff', [AppointmentController::class, 'staff']);
    Route::post('staff', [AppointmentController::class, 'storeStaff']);
    Route::get('staff/{staff}/schedule', [AppointmentController::class, 'staffSchedule']);
    Route::put('staff/{staff}/schedule', [AppointmentController::class, 'updateStaffSchedule']);

    // Billing, Invoices, Payments & Accounting Logs (Phase 4)
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::get('invoices/{invoice}/receipt', [InvoiceController::class, 'receipt']);
    Route::post('invoices/{invoice}/payment-link', [InvoiceController::class, 'paymentLink']);
    Route::apiResource('invoices', InvoiceController::class);

    Route::get('payments', [InvoiceController::class, 'payments']);
    Route::get('accounting-logs', [InvoiceController::class, 'accountingLogs']);

    // SMS & Messaging Automation (Phase 4)
    Route::prefix('messages')->group(function () {
        Route::get('templates', [MessagingController::class, 'templates']);
        Route::post('templates', [MessagingController::class, 'storeTemplate']);
        Route::put('templates/{template}', [MessagingController::class, 'updateTemplate']);
        Route::delete('templates/{template}', [MessagingController::class, 'destroyTemplate']);
        Route::post('send', [MessagingController::class, 'send']);
        Route::get('logs', [MessagingController::class, 'logs']);
        Route::get('providers', [MessagingController::class, 'providers']);
    });

    // Smart AI Receptionist & Visual Flow Builder (Phase 5)
    Route::prefix('ivr')->group(function () {
        Route::get('flows', [IvrFlowController::class, 'index']);
        Route::post('flows', [IvrFlowController::class, 'store']);
        Route::get('flows/{flow}', [IvrFlowController::class, 'show']);
        Route::put('flows/{flow}', [IvrFlowController::class, 'update']);
        Route::delete('flows/{flow}', [IvrFlowController::class, 'destroy']);
        Route::post('flows/{flow}/publish', [IvrFlowController::class, 'publish']);
        Route::put('reception-mode', [IvrFlowController::class, 'updateReceptionMode']);
        Route::post('simulate', [IvrFlowController::class, 'simulate']);
    });

    Route::get('campaign-workflows', [IvrFlowController::class, 'campaignWorkflows']);
    Route::post('campaign-workflows', [IvrFlowController::class, 'storeCampaignWorkflow']);
    Route::put('campaign-workflows/{workflow}', [IvrFlowController::class, 'updateCampaignWorkflow']);

    // Reports & Executive Analytics (Phase 5)
    Route::prefix('reports')->group(function () {
        Route::get('dashboard', [ReportController::class, 'dashboard']);
        Route::get('calls', [ReportController::class, 'calls']);
        Route::get('campaigns', [ReportController::class, 'campaigns']);
        Route::get('appointments', [ReportController::class, 'appointments']);
        Route::get('revenue', [ReportController::class, 'revenue']);
        Route::get('users', [ReportController::class, 'users']);
        Route::get('export', [ReportController::class, 'export']);
    });

    // Developer, Webhooks, API Tokens & Audit Logs (Phase 6)
    Route::prefix('developer')->group(function () {
        Route::get('tokens', [DeveloperController::class, 'tokens']);
        Route::post('tokens', [DeveloperController::class, 'createToken']);
        Route::delete('tokens/{id}', [DeveloperController::class, 'revokeToken']);

        Route::get('webhooks', [DeveloperController::class, 'webhooks']);
        Route::post('webhooks', [DeveloperController::class, 'storeWebhook']);
        Route::get('webhooks/{webhook}', [DeveloperController::class, 'showWebhook']);
        Route::put('webhooks/{webhook}', [DeveloperController::class, 'updateWebhook']);
        Route::delete('webhooks/{webhook}', [DeveloperController::class, 'destroyWebhook']);
        Route::post('webhooks/{webhook}/test', [DeveloperController::class, 'testWebhook']);
        Route::get('webhooks/{webhook}/deliveries', [DeveloperController::class, 'webhookDeliveries']);

        Route::get('audit-logs', [DeveloperController::class, 'auditLogs']);
        Route::get('system-status', [DeveloperController::class, 'systemStatus']);
    });
});
