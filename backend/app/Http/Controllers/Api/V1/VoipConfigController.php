<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CallQueue;
use App\Models\Integration;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VoipConfigController extends Controller
{
    public function __construct(
        private readonly TelephonyManager $telephonyManager
    ) {}

    public function show(): JsonResponse
    {
        $integration = Integration::where('type', 'voip')->latest()->first();
        $queues = CallQueue::with('branch:id,name')->get();

        return response()->json([
            'integration' => $integration ?? [
                'name' => 'Asterisk PBX',
                'type' => 'voip',
                'adapter_class' => 'asterisk',
                'config' => [
                    'host' => '127.0.0.1',
                    'port' => 8088,
                    'context' => 'from-internal',
                    'reception_mode' => 'human_only', // human_only, ai_only, hybrid
                ],
                'status' => 'active',
            ],
            'queues' => $queues,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'adapter_class' => ['required', 'string', 'in:asterisk,issabel,sip_webrtc'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:100'],
            'secret' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'string', 'max:100'],
            'reception_mode' => ['nullable', 'string', 'in:human_only,ai_only,hybrid'],
        ]);

        $integration = Integration::where('type', 'voip')->first() ?? new Integration(['type' => 'voip']);

        $existingCreds = $integration->credentials ?? [];
        $credentials = [
            'ari_user' => $validated['username'] ?? ($existingCreds['ari_user'] ?? 'admin'),
            'ari_secret' => ! empty($validated['secret'])
                ? $validated['secret']
                : ($existingCreds['ari_secret'] ?? ''),
        ];

        $integration->fill([
            'name' => $validated['name'],
            'type' => 'voip',
            'adapter_class' => $validated['adapter_class'],
            'credentials' => $credentials,
            'config' => [
                'host' => $validated['host'],
                'port' => $validated['port'],
                'context' => $validated['context'] ?? 'from-internal',
                'reception_mode' => $validated['reception_mode'] ?? 'human_only',
            ],
            'status' => 'active',
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'تنظیمات مرکز تلفن (VoIP) با موفقیت ذخیره شد.',
            'integration' => $integration,
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'adapter_class' => ['required', 'string', 'in:asterisk,issabel,sip_webrtc'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer'],
        ]);

        $adapter = $this->telephonyManager->adapter($validated['adapter_class']);
        $result = $adapter->testConnection([
            'host' => $validated['host'],
            'port' => $validated['port'] ?? 8088,
        ]);

        $integration = Integration::where('type', 'voip')->first();
        if ($integration) {
            $integration->update([
                'last_tested_at' => now(),
                'last_error' => $result['connected'] ? null : $result['message'],
            ]);
        }

        return response()->json($result);
    }
}
