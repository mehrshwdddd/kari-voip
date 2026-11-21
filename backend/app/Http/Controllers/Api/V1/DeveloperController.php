<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Webhook;
use App\Services\WebhookDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DeveloperController extends Controller
{
    public function __construct(
        private readonly WebhookDispatcherService $webhookDispatcher
    ) {}

    /**
     * List all API tokens for the current user.
     */
    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->select(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $tokens,
        ]);
    }

    /**
     * Create a new API token with specified abilities.
     */
    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null;

        $tokenResult = $request->user()->createToken(
            $validated['name'],
            $abilities,
            $expiresAt
        );

        return response()->json([
            'status' => 'success',
            'message' => 'کلید دسترسی با موفقیت ایجاد گردید.',
            'data' => [
                'token' => $tokenResult->accessToken,
                'plain_text_token' => $tokenResult->plainTextToken,
            ],
        ], 201);
    }

    /**
     * Revoke (delete) an API token.
     */
    public function revokeToken(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->tokens()->find($id);

        if (! $token) {
            return response()->json([
                'status' => 'error',
                'message' => 'کلید دسترسی یافت نشد یا مجاز به حذف آن نیستید.',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'کلید دسترسی موردنظر با موفقیت باطل شد.',
        ]);
    }

    /**
     * List all webhooks.
     */
    public function webhooks(Request $request): JsonResponse
    {
        $webhooks = Webhook::query()
            ->withCount('deliveries')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $webhooks,
        ]);
    }

    /**
     * Create a new webhook.
     */
    public function storeWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'secret' => ['nullable', 'string', 'min:8'],
            'events' => ['required', 'array'],
            'events.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $webhook = Webhook::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $validated['secret'] ?? Str::random(32),
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'وبهوک جدید با موفقیت ثبت شد.',
            'data' => $webhook,
        ], 201);
    }

    /**
     * Show webhook details.
     */
    public function showWebhook(Webhook $webhook): JsonResponse
    {
        $webhook->load(['deliveries' => function ($query) {
            $query->latest()->limit(20);
        }]);

        return response()->json([
            'status' => 'success',
            'data' => $webhook,
        ]);
    }

    /**
     * Update a webhook.
     */
    public function updateWebhook(Request $request, Webhook $webhook): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'url'],
            'secret' => ['nullable', 'string', 'min:8'],
            'events' => ['sometimes', 'required', 'array'],
            'events.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $webhook->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'وبهوک با موفقیت به‌روزرسانی شد.',
            'data' => $webhook,
        ]);
    }

    /**
     * Delete a webhook.
     */
    public function destroyWebhook(Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'وبهوک با موفقیت حذف شد.',
        ]);
    }

    /**
     * Test webhook by sending a ping payload.
     */
    public function testWebhook(Webhook $webhook): JsonResponse
    {
        $delivery = $this->webhookDispatcher->test($webhook);

        return response()->json([
            'status' => 'success',
            'message' => 'درخواست آزمایشی با موفقیت ارسال شد.',
            'data' => $delivery,
        ]);
    }

    /**
     * List webhook deliveries.
     */
    public function webhookDeliveries(Request $request, Webhook $webhook): JsonResponse
    {
        $deliveries = $webhook->deliveries()
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $deliveries,
        ]);
    }

    /**
     * List audit logs with filters.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user:id,name,email');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->string('event_type'));
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', 'like', '%'.$request->string('model_type').'%');
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->string('date_to'));
        }

        $logs = $query->latest('id')->paginate($request->integer('per_page', 25));

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }

    /**
     * System health check and runtime status.
     */
    public function systemStatus(): JsonResponse
    {
        $dbConnected = false;
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Exception $e) {
            $dbConnected = false;
        }

        $storageWritable = is_writable(storage_path());

        return response()->json([
            'status' => 'success',
            'data' => [
                'app_name' => 'VoiceReach OS',
                'version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => config('app.env'),
                'debug_mode' => config('app.debug'),
                'timezone' => config('app.timezone', 'Asia/Tehran'),
                'database' => [
                    'connection' => config('database.default'),
                    'connected' => $dbConnected,
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                ],
                'queue' => [
                    'driver' => config('queue.default'),
                ],
                'storage' => [
                    'writable' => $storageWritable,
                    'path' => storage_path(),
                ],
                'system_healthy' => $dbConnected && $storageWritable,
            ],
        ]);
    }
}
