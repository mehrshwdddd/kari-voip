<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Enums\CallTypeEnum;
use App\Events\CallAnswered;
use App\Events\CallEnded;
use App\Events\CallMissed;
use App\Events\CallRecorded;
use App\Events\CallStarted;
use App\Events\DtmfReceived;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class VoipWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $signature = (string) $request->header('X-VoIP-Signature', '');
        $timestamp = (string) $request->header('X-VoIP-Timestamp', '');
        $deliveryId = (string) $request->header('X-VoIP-Delivery-Id', '');

        if ($signature === '' || $timestamp === '') {
            return response()->json([
                'message' => 'Missing webhook signature or timestamp.',
            ], 401);
        }

        // Replay attack protection: 5-minute window (300 seconds)
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return response()->json([
                'message' => 'Webhook timestamp expired or invalid.',
            ], 401);
        }

        $secret = (string) config('services.voip.webhook_secret', env('VOIP_WEBHOOK_SECRET', 'voicereach_voip_secret'));
        $rawPayload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'message' => 'Invalid webhook HMAC signature.',
            ], 401);
        }

        // Idempotency protection
        if ($deliveryId !== '') {
            $cacheKey = "voip_webhook_delivery_{$deliveryId}";
            if (Cache::has($cacheKey)) {
                return response()->json([
                    'success' => true,
                    'duplicate_ignored' => true,
                ]);
            }
            Cache::put($cacheKey, true, now()->addMinutes(10));
        }

        $validated = $request->validate([
            'event' => ['required', 'string'],
            'voip_call_id' => ['required', 'string'],
            'caller_number' => ['nullable', 'string'],
            'callee_number' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:inbound,outbound'],
            'duration_seconds' => ['nullable', 'integer'],
            'recording_path' => ['nullable', 'string'],
            'dtmf' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $voipCallId = $validated['voip_call_id'];
        $eventType = $validated['event'];

        $call = Call::where('voip_call_id', $voipCallId)->first();

        if (! $call) {
            $caller = $validated['caller_number'] ?? 'unknown';
            $callee = $validated['callee_number'] ?? '02188889999';
            $direction = $validated['direction'] ?? 'inbound';
            $lookupPhone = $direction === 'inbound' ? $caller : $callee;

            $contact = Contact::where('phone_primary', $lookupPhone)->first();

            $call = Call::create([
                'branch_id' => $validated['branch_id'] ?? $contact?->branch_id,
                'contact_id' => $contact?->id,
                'caller_number' => $caller,
                'callee_number' => $callee,
                'direction' => CallDirectionEnum::tryFrom($direction) ?? CallDirectionEnum::INBOUND,
                'type' => CallTypeEnum::MANUAL,
                'status' => CallStatusEnum::RINGING,
                'voip_provider' => 'asterisk',
                'voip_call_id' => $voipCallId,
                'started_at' => now(),
            ]);
        }

        $call->events()->create([
            'event_type' => $eventType,
            'payload' => $request->all(),
            'occurred_at' => now(),
        ]);

        switch ($eventType) {
            case 'call.started':
            case 'call.ringing':
                event(new CallStarted($call));
                break;

            case 'call.answered':
                $call->update([
                    'status' => CallStatusEnum::ACTIVE,
                    'answered_at' => now(),
                ]);
                event(new CallAnswered($call));
                break;

            case 'call.ended':
                $call->update([
                    'status' => CallStatusEnum::ENDED,
                    'ended_at' => now(),
                    'duration_seconds' => $validated['duration_seconds'] ?? $call->duration_seconds,
                ]);
                event(new CallEnded($call));
                break;

            case 'call.missed':
                $call->update([
                    'status' => CallStatusEnum::MISSED,
                    'ended_at' => now(),
                ]);
                event(new CallMissed($call));
                break;

            case 'call.recorded':
                if (! empty($validated['recording_path'])) {
                    $call->update([
                        'recording_path' => $validated['recording_path'],
                        'is_recording' => false,
                    ]);
                    event(new CallRecorded($call));
                }
                break;

            case 'dtmf.received':
                $digit = (string) ($validated['dtmf'] ?? '');
                if ($digit !== '') {
                    event(new DtmfReceived($call, $digit));
                }
                break;
        }

        return response()->json([
            'success' => true,
            'call_id' => $call->id,
            'status' => $call->status->value,
        ]);
    }
}
