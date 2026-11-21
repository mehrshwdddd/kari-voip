<?php

namespace App\Services\AI;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Enums\CallTypeEnum;
use App\Enums\ContactSourceEnum;
use App\Enums\ContactStatusEnum;
use App\Models\Appointment;
use App\Models\Call;
use App\Models\Contact;
use App\Models\IvrFlow;
use App\Models\IvrSession;
use App\Models\Service;
use App\Models\Staff;
use App\Services\AI\Contracts\AIAdapterInterface;
use App\Services\Appointments\AvailabilityEngine;
use App\Services\Campaign\DNCChecker;
use App\Services\Messaging\SmsManager;
use Carbon\Carbon;

final class IVRFlowEngine
{
    public function __construct(
        private readonly AIAdapterInterface $aiAdapter,
        private readonly IntentDetector $intentDetector,
        private readonly AvailabilityEngine $availabilityEngine,
        private readonly SmsManager $smsManager
    ) {}

    /**
     * Process an inbound AI Receptionist interaction step (Persian speech or DTMF).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function processInteraction(array $input): array
    {
        $callerNumber = DNCChecker::normalizePhone((string) ($input['caller_number'] ?? '09121112233'));
        $utterance = trim((string) ($input['utterance'] ?? ''));
        $dtmf = isset($input['dtmf']) && $input['dtmf'] !== '' ? (string) $input['dtmf'] : null;
        $flowId = isset($input['flow_id']) ? (int) $input['flow_id'] : null;

        $flow = $flowId
            ? IvrFlow::find($flowId)
            : (IvrFlow::where('is_active', true)->latest()->first() ?? IvrFlow::latest()->first());

        // 1. Find or create Lead/Contact in Customer 360
        $contact = Contact::firstOrCreate(
            ['phone_primary' => $callerNumber],
            [
                'first_name' => 'مخاطب هوشمند',
                'last_name' => substr($callerNumber, -4),
                'status' => ContactStatusEnum::ACTIVE,
                'source' => ContactSourceEnum::CALL,
            ]
        );

        // 2. Detect Intent & Entities from Persian Speech / DTMF
        $detected = $this->intentDetector->detect($utterance, $dtmf);
        $aiResult = $this->aiAdapter->detectIntentAndEntities($utterance, ['dtmf' => $dtmf]);
        $intent = $detected['intent'];
        $entities = $detected['entities'];
        $replyText = $aiResult['reply'];
        $actionsTaken = [];

        // Update Contact name if caller introduced themselves in Persian
        if (! empty($entities['customer_name'])) {
            $nameParts = explode(' ', (string) $entities['customer_name'], 2);
            $contact->update([
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? $contact->last_name,
            ]);
            $actionsTaken[] = "به‌روزرسانی نام مخاطب در پرونده ۳۶۰ به «{$entities['customer_name']}»";
        }

        // 3. Create or update Call & IvrSession audit records
        $call = Call::create([
            'contact_id' => $contact->id,
            'branch_id' => $contact->branch_id,
            'caller_number' => $callerNumber,
            'callee_number' => '02188889999',
            'direction' => CallDirectionEnum::INBOUND,
            'type' => CallTypeEnum::IVR,
            'status' => $intent === 'human_transfer' ? CallStatusEnum::TRANSFERRED : CallStatusEnum::ENDED,
            'voip_provider' => 'ai_ivr',
            'voip_call_id' => 'ivr_'.uniqid(),
            'started_at' => now(),
            'answered_at' => now(),
            'ended_at' => now()->addSeconds(22),
            'duration_seconds' => 22,
            'disposition' => "منشی هوشمند: {$detected['label']}",
            'disposition_note' => $utterance !== '' ? "گفتار مشتری: {$utterance}" : "ورودی کلید: {$dtmf}",
            'transfer_to' => $intent === 'human_transfer' ? '101' : null,
            'transferred_at' => $intent === 'human_transfer' ? now() : null,
        ]);

        // 4. Execute Autonomous AI Receptionist Actions
        if ($intent === 'book_appointment') {
            $service = Service::where('is_active', true)->first() ?? Service::create([
                'name' => 'مشاوره و ویزیت تخصصی',
                'duration_minutes' => 30,
                'buffer_minutes' => 10,
                'price' => 500000,
                'is_active' => true,
            ]);

            $staff = Staff::where('is_active', true)->first() ?? Staff::create([
                'name' => 'دکتر نادری',
                'title' => 'متخصص',
                'is_active' => true,
            ]);

            $targetDate = ! empty($entities['date_hint'])
                ? (string) $entities['date_hint']
                : now()->addDay()->toDateString();

            $targetTime = ! empty($entities['time_hint'])
                ? (str_contains((string) $entities['time_hint'], ':') ? (string) $entities['time_hint'] : "{$entities['time_hint']}:00")
                : '10:00';

            $startsAt = Carbon::parse("{$targetDate} {$targetTime}", 'Asia/Tehran');
            $slotCheck = $this->availabilityEngine->checkSlot($staff, $service, $startsAt);

            if (! $slotCheck['available']) {
                $startsAt = $startsAt->addHours(2);
            }

            $appointment = Appointment::create([
                'branch_id' => $contact->branch_id,
                'contact_id' => $contact->id,
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'status' => 'confirmed',
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes($service->duration_minutes),
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
                'total_amount' => $service->price,
                'source' => 'call',
                'call_id' => $call->id,
                'notes' => 'رزرو خودکار توسط منشی هوشمند (AI IVR)',
            ]);

            $smsBody = "{$contact->full_name} عزیز، نوبت «{$service->name}» شما توسط منشی هوشمند برای ساعت {$startsAt->format('H:i')} ثبت شد. کد نوبت: {$appointment->booking_number}";
            $this->smsManager->sendSms($callerNumber, $smsBody, $contact->id, null, [
                'reference_type' => 'appointment',
                'reference_id' => $appointment->id,
            ]);

            $replyText = "نوبت «{$service->name}» با {$staff->name} برای ساعت {$startsAt->format('H:i')} با کد {$appointment->booking_number} ثبت و پیامک تایید برایتان ارسال شد.";
            $actionsTaken[] = "رزرو خودکار نوبت شماره {$appointment->booking_number}";
            $actionsTaken[] = "ارسال پیامک تایید رزرو به {$callerNumber}";
        } elseif ($intent === 'support_ticket') {
            $contact->notesList()->create([
                'user_id' => 1,
                'content' => "تیکت پشتیبانی ثبت‌شده توسط منشی هوشمند: {$utterance}",
                'type' => 'task',
                'is_pinned' => true,
            ]);
            $actionsTaken[] = 'ایجاد تیکت پشتیبانی سنجاق‌شده در پرونده مشتری';
        } elseif ($intent === 'lead_inquiry') {
            $contact->notesList()->create([
                'user_id' => 1,
                'content' => "سرنخ فروش (Lead) دریافت‌شده توسط منشی هوشمند: {$utterance}",
                'type' => 'follow_up',
                'is_pinned' => true,
            ]);
            $actionsTaken[] = 'ثبت سرنخ فروش (Lead) و پیگیری در CRM';
        } elseif ($intent === 'human_transfer') {
            $actionsTaken[] = 'انتقال زنده تماس به صف منشی انسانی (داخلی ۱۰۱)';
        } elseif ($intent === 'billing_inquiry') {
            $debt = number_format((float) $contact->total_debt);
            $credit = number_format((float) $contact->credit_balance);
            $replyText = "مانده بدهی فعلی شما {$debt} تومان و اعتبار کیف پول شما {$credit} تومان می‌باشد.";
            $actionsTaken[] = 'اعلام صوتی وضعیت کیف پول و صورت‌حساب مشتری';
        }

        // 5. Generate Call Summary & Synthesize Persian TTS
        $summary = $this->aiAdapter->summarizeCall($utterance !== '' ? $utterance : $detected['label']);
        $tts = $this->aiAdapter->synthesizeSpeech($replyText);

        $matchedNodeId = $this->resolveTargetNodeId($flow, $intent);

        $ivrSession = IvrSession::create([
            'call_id' => $call->id,
            'flow_id' => $flow?->id,
            'current_node_id' => $matchedNodeId,
            'collected_data' => [
                'utterance' => $utterance,
                'entities' => $entities,
                'actions_taken' => $actionsTaken,
                'summary' => $summary['summary'],
            ],
            'dtmf_input' => $dtmf,
            'intent_detected' => $intent,
            'language' => 'fa',
            'started_at' => now(),
            'ended_at' => now(),
            'status' => 'completed',
        ]);

        $call->update(['ivr_session_id' => $ivrSession->id]);

        return [
            'call_id' => $call->id,
            'session_id' => $ivrSession->id,
            'intent' => $intent,
            'intent_label' => $detected['label'],
            'confidence' => $detected['confidence'],
            'entities' => $entities,
            'reply_text' => $replyText,
            'tts_audio' => $tts,
            'current_node_id' => $matchedNodeId,
            'actions_taken' => $actionsTaken,
            'summary' => $summary,
            'contact' => $contact->fresh(),
        ];
    }

    private function resolveTargetNodeId(?IvrFlow $flow, string $intent): string
    {
        if ($flow && is_array($flow->nodes)) {
            foreach ($flow->nodes as $node) {
                if (($node['data']['intent'] ?? '') === $intent || ($node['type'] ?? '') === $intent) {
                    return (string) ($node['id'] ?? 'node_'.$intent);
                }
            }
        }

        return 'node_'.$intent;
    }
}
