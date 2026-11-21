<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Enums\CallTypeEnum;
use App\Events\CallAnswered;
use App\Events\CallEnded;
use App\Events\CallRecorded;
use App\Events\CallStarted;
use App\Events\CallTransferred;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallDisposition;
use App\Models\CallQueue;
use App\Models\Contact;
use App\Services\Telephony\Contracts\TelephonyAdapterInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final class CallController extends Controller
{
    public function __construct(
        private readonly TelephonyAdapterInterface $telephony
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Call::class);

        $user = $request->user();
        $query = Call::with([
            'contact:id,first_name,last_name,phone_primary,status,is_vip',
            'user:id,name',
            'branch:id,name',
            'queue:id,name',
        ]);

        // Branch BOLA protection
        if ($user->branch_id && ! $user->hasRole('owner')) {
            $query->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->integer('contact_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('caller_number', 'like', "%{$search}%")
                    ->orWhere('callee_number', 'like', "%{$search}%")
                    ->orWhere('disposition', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($cq) use ($search) {
                        $cq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $calls = $query->latest('started_at')->paginate($request->integer('per_page', 20));

        // Summary metrics
        $statsQuery = Call::query();
        if ($user->branch_id && ! $user->hasRole('owner')) {
            $statsQuery->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)->orWhereNull('branch_id');
            });
        }

        $stats = [
            'total_calls' => (clone $statsQuery)->count(),
            'active_calls' => (clone $statsQuery)->whereIn('status', ['ringing', 'active', 'on_hold'])->count(),
            'missed_calls' => (clone $statsQuery)->where('status', 'missed')->count(),
            'avg_duration' => (int) round((float) (clone $statsQuery)->where('duration_seconds', '>', 0)->avg('duration_seconds')),
        ];

        return response()->json([
            'data' => $calls->items(),
            'current_page' => $calls->currentPage(),
            'last_page' => $calls->lastPage(),
            'per_page' => $calls->perPage(),
            'total' => $calls->total(),
            'stats' => $stats,
        ]);
    }

    /**
     * Get currently active / ringing calls for Receptionist Workspace & Screen Pop.
     */
    public function active(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Call::class);

        $user = $request->user();
        $query = Call::with([
            'contact.tagPivots',
            'contact.branch:id,name',
            'contact.assignedUser:id,name',
            'contact.notesList' => fn ($q) => $q->with('user:id,name')->limit(5),
            'contact.calls' => fn ($q) => $q->where('status', 'ended')->limit(5),
            'user:id,name',
            'branch:id,name',
            'queue:id,name',
            'events',
        ])->whereIn('status', [
            CallStatusEnum::RINGING->value,
            CallStatusEnum::ACTIVE->value,
            CallStatusEnum::ON_HOLD->value,
        ]);

        if ($user->branch_id && ! $user->hasRole('owner')) {
            $query->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        $activeCalls = $query->orderByDesc('started_at')->get();

        return response()->json([
            'active_calls' => $activeCalls,
        ]);
    }

    public function show(Call $call): JsonResponse
    {
        $this->authorize('view', $call);

        $call->load([
            'contact.tagPivots',
            'contact.branch:id,name',
            'contact.assignedUser:id,name',
            'contact.notesList.user:id,name',
            'user:id,name',
            'branch:id,name',
            'queue:id,name',
            'events',
        ]);

        return response()->json([
            'call' => $call,
        ]);
    }

    /**
     * Initiate an outbound Click-to-Call.
     */
    public function clickToCall(Request $request): JsonResponse
    {
        $this->authorize('create', Call::class);

        $validated = $request->validate([
            'callee_number' => ['required', 'string', 'max:30'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'caller_extension' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $calleeNumber = trim($validated['callee_number']);
        $callerExtension = $validated['caller_extension'] ?? '101';

        $contact = null;
        if (! empty($validated['contact_id'])) {
            $contact = Contact::find($validated['contact_id']);
        } else {
            $contact = Contact::where('phone_primary', $calleeNumber)->first();
        }

        $pbxResult = $this->telephony->originateCall($callerExtension, $calleeNumber);

        $call = Call::create([
            'branch_id' => $user->branch_id ?? $contact?->branch_id,
            'contact_id' => $contact?->id,
            'caller_number' => $callerExtension,
            'callee_number' => $calleeNumber,
            'direction' => CallDirectionEnum::OUTBOUND,
            'type' => CallTypeEnum::MANUAL,
            'status' => CallStatusEnum::ACTIVE,
            'user_id' => $user->id,
            'voip_provider' => $this->telephony->providerName(),
            'voip_call_id' => $pbxResult['voip_call_id'],
            'voip_channel' => $pbxResult['channel'],
            'started_at' => now(),
            'answered_at' => now(),
        ]);

        $call->events()->create([
            'event_type' => 'call.originated',
            'payload' => ['initiated_by' => $user->id, 'channel' => $pbxResult['channel']],
            'occurred_at' => now(),
        ]);

        if ($contact) {
            $contact->update(['last_contact_at' => now()]);
        }

        event(new CallStarted($call));

        return response()->json([
            'success' => true,
            'message' => 'تماس خروجی برقرار شد.',
            'call' => $call->load([
                'contact.tagPivots',
                'contact.notesList.user:id,name',
                'user:id,name',
                'events',
            ]),
        ], 201);
    }

    /**
     * Simulate an incoming call to test Receptionist Screen Pop.
     */
    public function simulateInbound(Request $request): JsonResponse
    {
        $this->authorize('create', Call::class);

        $validated = $request->validate([
            'caller_number' => ['nullable', 'string', 'max:30'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
        ]);

        $user = $request->user();
        $contact = null;

        if (! empty($validated['contact_id'])) {
            $contact = Contact::find($validated['contact_id']);
        } elseif (! empty($validated['caller_number'])) {
            $contact = Contact::where('phone_primary', $validated['caller_number'])->first();
        } else {
            $contact = Contact::latest()->first();
        }

        $callerNumber = $validated['caller_number'] ?? ($contact?->phone_primary ?? '09121112233');
        $queue = CallQueue::first();

        $call = Call::create([
            'branch_id' => $user->branch_id ?? $contact?->branch_id,
            'contact_id' => $contact?->id,
            'caller_number' => $callerNumber,
            'callee_number' => '02188889999',
            'direction' => CallDirectionEnum::INBOUND,
            'type' => CallTypeEnum::MANUAL,
            'status' => CallStatusEnum::RINGING,
            'user_id' => $user->id,
            'queue_id' => $queue?->id,
            'voip_provider' => $this->telephony->providerName(),
            'voip_call_id' => 'sim_'.uniqid(),
            'voip_channel' => "PJSIP/{$callerNumber}",
            'started_at' => now(),
        ]);

        $call->events()->create([
            'event_type' => 'call.ringing',
            'payload' => ['caller' => $callerNumber, 'queue' => $queue?->name],
            'occurred_at' => now(),
        ]);

        event(new CallStarted($call));

        return response()->json([
            'success' => true,
            'message' => 'تماس ورودی در حال زنگ خوردن است (Screen Pop فعال شد).',
            'call' => $call->load([
                'contact.tagPivots',
                'contact.notesList.user:id,name',
                'user:id,name',
                'queue:id,name',
                'events',
            ]),
        ], 201);
    }

    public function answer(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        if ($call->voip_call_id) {
            $this->telephony->answerCall($call->voip_call_id);
        }

        $ringDuration = $call->started_at ? (int) abs(now()->diffInSeconds($call->started_at)) : 3;

        $call->update([
            'status' => CallStatusEnum::ACTIVE,
            'answered_at' => now(),
            'ring_duration' => $ringDuration,
            'user_id' => $request->user()->id,
        ]);

        $call->events()->create([
            'event_type' => 'call.answered',
            'payload' => ['answered_by' => $request->user()->name],
            'occurred_at' => now(),
        ]);

        event(new CallAnswered($call));

        return response()->json([
            'success' => true,
            'message' => 'تماس پاسخ داده شد.',
            'call' => $call->fresh(['contact.tagPivots', 'contact.notesList.user:id,name', 'user:id,name', 'events']),
        ]);
    }

    public function reject(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        if ($call->voip_call_id) {
            $this->telephony->rejectCall($call->voip_call_id);
        }

        $call->update([
            'status' => CallStatusEnum::MISSED,
            'ended_at' => now(),
        ]);

        $call->events()->create([
            'event_type' => 'call.rejected',
            'payload' => ['rejected_by' => $request->user()->name],
            'occurred_at' => now(),
        ]);

        event(new CallEnded($call));

        return response()->json([
            'success' => true,
            'message' => 'تماس رد شد.',
            'call' => $call,
        ]);
    }

    public function end(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        if ($call->voip_call_id) {
            $this->telephony->hangupCall($call->voip_call_id);
        }

        $referenceTime = $call->answered_at ?? $call->started_at ?? now();
        $duration = max(1, (int) abs(now()->diffInSeconds($referenceTime)));

        $call->update([
            'status' => CallStatusEnum::ENDED,
            'ended_at' => now(),
            'duration_seconds' => $duration,
            'is_recording' => false,
        ]);

        $call->events()->create([
            'event_type' => 'call.ended',
            'payload' => ['duration_seconds' => $duration],
            'occurred_at' => now(),
        ]);

        event(new CallEnded($call));

        return response()->json([
            'success' => true,
            'message' => 'مکالمه پایان یافت.',
            'call' => $call->fresh(['contact.tagPivots', 'user:id,name', 'events']),
        ]);
    }

    public function hold(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        $shouldHold = $call->status !== CallStatusEnum::ON_HOLD;

        if ($call->voip_call_id) {
            $this->telephony->holdCall($call->voip_call_id, $shouldHold);
        }

        $call->update([
            'status' => $shouldHold ? CallStatusEnum::ON_HOLD : CallStatusEnum::ACTIVE,
        ]);

        $call->events()->create([
            'event_type' => $shouldHold ? 'call.hold.started' : 'call.hold.ended',
            'payload' => ['by' => $request->user()->name],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $shouldHold ? 'تماس در حالت انتظار (Hold) قرار گرفت.' : 'تماس از حالت انتظار خارج شد.',
            'call' => $call->fresh(['contact.tagPivots', 'user:id,name', 'events']),
        ]);
    }

    public function mute(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        $nextMute = ! $call->is_muted;

        if ($call->voip_call_id) {
            $this->telephony->muteCall($call->voip_call_id, $nextMute);
        }

        $call->update([
            'is_muted' => $nextMute,
        ]);

        $call->events()->create([
            'event_type' => $nextMute ? 'call.muted' : 'call.unmuted',
            'payload' => ['by' => $request->user()->name],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $nextMute ? 'میکروفون بی‌صدا شد.' : 'میکروفون وصل شد.',
            'call' => $call->fresh(['contact.tagPivots', 'user:id,name', 'events']),
        ]);
    }

    public function transfer(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        $validated = $request->validate([
            'target' => ['required', 'string', 'max:50'],
        ]);

        if ($call->voip_call_id) {
            $this->telephony->transferCall($call->voip_call_id, $validated['target']);
        }

        $call->update([
            'status' => CallStatusEnum::TRANSFERRED,
            'transfer_to' => $validated['target'],
            'transferred_at' => now(),
            'ended_at' => now(),
        ]);

        $call->events()->create([
            'event_type' => 'call.transferred',
            'payload' => ['target' => $validated['target'], 'by' => $request->user()->name],
            'occurred_at' => now(),
        ]);

        event(new CallTransferred($call));

        return response()->json([
            'success' => true,
            'message' => "تماس به داخلی/اپراتور {$validated['target']} منتقل شد.",
            'call' => $call,
        ]);
    }

    public function startRecording(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        $recordingPath = $this->telephony->startRecording($call->voip_call_id ?? (string) $call->id);

        // Ensure placeholder wav file exists in storage for local audit/playback
        if (! Storage::disk('local')->exists($recordingPath)) {
            Storage::disk('local')->put($recordingPath, $this->generateSilentWavBytes());
        }

        $call->update([
            'is_recording' => true,
            'recording_path' => $recordingPath,
        ]);

        $call->events()->create([
            'event_type' => 'call.recording.started',
            'payload' => ['by' => $request->user()->name],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ضبط مکالمه آغاز شد.',
            'call' => $call->fresh(['contact.tagPivots', 'user:id,name', 'events']),
        ]);
    }

    public function stopRecording(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        if ($call->voip_call_id) {
            $this->telephony->stopRecording($call->voip_call_id);
        }

        $call->update([
            'is_recording' => false,
        ]);

        $call->events()->create([
            'event_type' => 'call.recording.stopped',
            'payload' => ['by' => $request->user()->name],
            'occurred_at' => now(),
        ]);

        event(new CallRecorded($call));

        return response()->json([
            'success' => true,
            'message' => 'ضبط مکالمه ذخیره و متوقف شد.',
            'call' => $call->fresh(['contact.tagPivots', 'user:id,name', 'events']),
        ]);
    }

    public function disposition(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        $validated = $request->validate([
            'disposition' => ['required', 'string', 'max:100'],
            'disposition_note' => ['nullable', 'string'],
        ]);

        $call->update([
            'disposition' => $validated['disposition'],
            'disposition_note' => $validated['disposition_note'] ?? $call->disposition_note,
        ]);

        $call->events()->create([
            'event_type' => 'call.disposition.set',
            'payload' => [
                'disposition' => $validated['disposition'],
                'by' => $request->user()->name,
            ],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'نتیجه تماس با موفقیت ثبت شد.',
            'call' => $call,
        ]);
    }

    public function addNote(Request $request, Call $call): JsonResponse
    {
        $this->authorize('control', $call);

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:note,task,follow_up'],
        ]);

        $call->update([
            'disposition_note' => $validated['content'],
        ]);

        if ($call->contact_id) {
            $call->contact?->notesList()->create([
                'user_id' => $request->user()->id,
                'content' => $validated['content'],
                'type' => $validated['type'] ?? 'note',
                'is_pinned' => false,
            ]);
        }

        $call->events()->create([
            'event_type' => 'call.note.added',
            'payload' => ['content' => $validated['content']],
            'occurred_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'یادداشت تماس ثبت و به پرونده مشتری اضافه شد.',
            'call' => $call->fresh(['contact.notesList.user:id,name', 'events']),
        ], 201);
    }

    /**
     * Issue a temporary Signed URL for call recording playback.
     */
    public function recordingUrl(Call $call): JsonResponse
    {
        $this->authorize('listenRecording', $call);

        if (empty($call->recording_path)) {
            return response()->json([
                'message' => 'فایل ضبط‌شده‌ای برای این تماس وجود ندارد.',
            ], 404);
        }

        $signedUrl = URL::temporarySignedRoute(
            'api.v1.calls.recording.stream',
            now()->addMinutes(15),
            ['call' => $call->id]
        );

        return response()->json([
            'recording_url' => $signedUrl,
            'expires_in_seconds' => 900,
        ]);
    }

    /**
     * Stream the recording file after verifying the cryptographic signature.
     */
    public function streamRecording(Request $request, Call $call): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'لینک پخش فایل صوتی منقضی یا نامعتبر است.');
        }

        if (empty($call->recording_path)) {
            abort(404);
        }

        $bytes = Storage::disk('local')->exists($call->recording_path)
            ? Storage::disk('local')->get($call->recording_path)
            : $this->generateSilentWavBytes();

        return response($bytes, 200, [
            'Content-Type' => 'audio/wav',
            'Content-Disposition' => 'inline; filename="call-'.$call->id.'.wav"',
        ]);
    }

    public function dispositions(): JsonResponse
    {
        return response()->json([
            'dispositions' => CallDisposition::orderBy('id')->get(),
        ]);
    }

    public function queues(): JsonResponse
    {
        return response()->json([
            'queues' => CallQueue::with('branch:id,name')->orderBy('id')->get(),
        ]);
    }

    /**
     * Generate a tiny valid 1-second PCM WAV header + silence for local playback.
     */
    private function generateSilentWavBytes(): string
    {
        $sampleRate = 8000;
        $numSamples = 8000;
        $dataSize = $numSamples;
        $fileSize = 36 + $dataSize;

        $header = pack(
            'a4Va4a4VvvVVvva4V',
            'RIFF',
            $fileSize,
            'WAVE',
            'fmt ',
            16,
            1, // PCM
            1, // Mono
            $sampleRate,
            $sampleRate,
            1,
            8, // 8-bit
            'data',
            $dataSize
        );

        return $header.str_repeat(chr(128), $numSamples);
    }
}
