<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampaignOutcomeEnum;
use App\Enums\CampaignStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignCallLog;
use App\Models\CampaignContact;
use App\Models\Contact;
use App\Services\Campaign\CampaignEngine;
use App\Services\Campaign\ContactListImporter;
use App\Services\Campaign\DNCChecker;
use App\Services\Telephony\Contracts\TelephonyAdapterInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignEngine $campaignEngine,
        private readonly ContactListImporter $importer,
        private readonly DNCChecker $dncChecker,
        private readonly TelephonyAdapterInterface $telephony
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        $user = $request->user();
        $query = Campaign::with(['branch:id,name', 'contactList:id,name,valid_count', 'creator:id,name']);

        if ($user->branch_id && ! $user->hasRole('owner')) {
            $query->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)->orWhereNull('branch_id');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $campaigns = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:audio,tts,ivr'],
            'audio_path' => ['nullable', 'string'],
            'tts_text' => ['nullable', 'string'],
            'tts_voice' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date'],
            'allowed_hours_start' => ['nullable', 'string', 'regex:/^[0-2][0-9]:[0-5][0-9]$/'],
            'allowed_hours_end' => ['nullable', 'string', 'regex:/^[0-2][0-9]:[0-5][0-9]$/'],
            'allowed_days' => ['nullable', 'array'],
            'max_concurrent_calls' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_retry_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'retry_interval_minutes' => ['nullable', 'array'],
            'dtmf_options' => ['nullable', 'array'],
            'human_transfer_enabled' => ['boolean'],
            'record_calls' => ['boolean'],
            'caller_id' => ['nullable', 'string', 'max:30'],
            'phone_numbers' => ['nullable', 'array'],
            'csv_content' => ['nullable', 'string'],
            'tag_id' => ['nullable', 'exists:contact_tags,id'],
            'use_all_crm_contacts' => ['boolean'],
        ]);

        $user = $request->user();
        $branchId = $validated['branch_id'] ?? $user->branch_id;

        // Collect target phone numbers from provided source
        $targetPhones = [];
        $contactListId = null;

        if (! empty($validated['csv_content'])) {
            $importResult = $this->importer->importFromCsvContent(
                "لیست مخاطبین {$validated['name']}",
                $validated['csv_content'],
                $user->id,
                $branchId
            );
            $targetPhones = $importResult['valid_phones'];
            $contactListId = $importResult['contact_list']->id;
        } elseif (! empty($validated['phone_numbers'])) {
            $importResult = $this->importer->importRows(
                "لیست دستی {$validated['name']}",
                $validated['phone_numbers'],
                'manual',
                $user->id,
                $branchId
            );
            $targetPhones = $importResult['valid_phones'];
            $contactListId = $importResult['contact_list']->id;
        } else {
            $contactQuery = Contact::where('status', '!=', 'blocked');
            if (! empty($validated['tag_id'])) {
                $tagId = (int) $validated['tag_id'];
                $contactQuery->whereHas('tagPivots', fn ($q) => $q->where('tag_id', $tagId));
            }
            $targetPhones = $contactQuery->pluck('phone_primary')->unique()->values()->all();
        }

        $campaign = Campaign::create([
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'audio_path' => $validated['audio_path'] ?? 'campaigns/default_welcome.wav',
            'tts_text' => $validated['tts_text'] ?? null,
            'tts_voice' => $validated['tts_voice'] ?? 'fa-IR-DilaraNeural',
            'status' => ! empty($validated['scheduled_start_at'])
                ? CampaignStatusEnum::SCHEDULED
                : CampaignStatusEnum::DRAFT,
            'contact_list_id' => $contactListId,
            'scheduled_start_at' => $validated['scheduled_start_at'] ?? null,
            'scheduled_end_at' => $validated['scheduled_end_at'] ?? null,
            'allowed_hours_start' => $validated['allowed_hours_start'] ?? '09:00',
            'allowed_hours_end' => $validated['allowed_hours_end'] ?? '20:00',
            'allowed_days' => $validated['allowed_days'] ?? ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'max_concurrent_calls' => $validated['max_concurrent_calls'] ?? 5,
            'max_retry_count' => $validated['max_retry_count'] ?? 2,
            'retry_interval_minutes' => $validated['retry_interval_minutes'] ?? [15, 60, 180],
            'dtmf_options' => $validated['dtmf_options'] ?? ['1' => 'transfer_agent', '9' => 'opt_out'],
            'human_transfer_enabled' => $validated['human_transfer_enabled'] ?? true,
            'record_calls' => $validated['record_calls'] ?? false,
            'caller_id' => $validated['caller_id'] ?? '02188889999',
            'total_contacts' => count($targetPhones),
            'created_by' => $user->id,
        ]);

        foreach ($targetPhones as $phone) {
            $normalized = DNCChecker::normalizePhone((string) $phone);
            $contact = Contact::where('phone_primary', $normalized)->first();

            CampaignContact::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact?->id,
                'phone_number' => $normalized,
                'status' => 'pending',
                'attempt_count' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'کمپین صوتی جدید با موفقیت ایجاد شد.',
            'campaign' => $campaign->load(['branch:id,name', 'contactList']),
        ], 201);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $campaign->load(['branch:id,name', 'contactList', 'creator:id,name']);

        return response()->json([
            'campaign' => $campaign,
        ]);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'allowed_hours_start' => ['nullable', 'string'],
            'allowed_hours_end' => ['nullable', 'string'],
            'allowed_days' => ['nullable', 'array'],
            'max_concurrent_calls' => ['nullable', 'integer', 'min:1'],
            'max_retry_count' => ['nullable', 'integer', 'min:0'],
            'retry_interval_minutes' => ['nullable', 'array'],
            'tts_text' => ['nullable', 'string'],
        ]);

        $campaign->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تنظیمات کمپین به‌روزرسانی شد.',
            'campaign' => $campaign,
        ]);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'کمپین صوتی با موفقیت حذف شد.',
        ]);
    }

    public function start(Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $result = $this->campaignEngine->dispatchBatch($campaign);

        if ($result['outside_hours']) {
            return response()->json([
                'success' => false,
                'outside_hours' => true,
                'message' => 'زمان فعلی خارج از بازه ساعات یا روزهای مجاز تماس این کمپین است.',
                'campaign' => $campaign->fresh(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "کمپین آغاز شد ({$result['dispatched']} تماس در صف، {$result['skipped_dnc']} شماره در لیست سیاه DNC رد شد).",
            'dispatch_result' => $result,
            'campaign' => $campaign->fresh(),
        ]);
    }

    public function pause(Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $campaign->update(['status' => CampaignStatusEnum::PAUSED]);

        return response()->json([
            'success' => true,
            'message' => 'کمپین موقتاً متوقف شد.',
            'campaign' => $campaign,
        ]);
    }

    public function resume(Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $campaign->update(['status' => CampaignStatusEnum::RUNNING]);
        $result = $this->campaignEngine->dispatchBatch($campaign);

        return response()->json([
            'success' => true,
            'message' => 'اجرای کمپین از سر گرفته شد.',
            'dispatch_result' => $result,
            'campaign' => $campaign->fresh(),
        ]);
    }

    public function cancel(Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $campaign->update(['status' => CampaignStatusEnum::CANCELLED]);

        return response()->json([
            'success' => true,
            'message' => 'کمپین لغو گردید.',
            'campaign' => $campaign,
        ]);
    }

    public function testCall(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
        ]);

        $phone = DNCChecker::normalizePhone($validated['phone_number']);

        if ($this->dncChecker->isBlocked($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'این شماره در لیست سیاه عدم تماس (DNC) قرار دارد و امکان تماس تستی با آن وجود ندارد.',
            ], 422);
        }

        $pbxResult = $this->telephony->originateCall(
            $campaign->caller_id ?: '02188889999',
            $phone,
            ['campaign_test' => $campaign->id]
        );

        return response()->json([
            'success' => true,
            'message' => "تماس تستی با شماره {$phone} برقرار شد.",
            'voip_call_id' => $pbxResult['voip_call_id'],
        ]);
    }

    public function contacts(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $contacts = $campaign->campaignContacts()
            ->with('contact:id,first_name,last_name,phone_primary')
            ->paginate($request->integer('per_page', 30));

        return response()->json($contacts);
    }

    public function logs(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $query = $campaign->callLogs()->with('contact:id,first_name,last_name');

        if ($request->filled('outcome')) {
            $query->where('outcome', $request->input('outcome'));
        }

        $logs = $query->paginate($request->integer('per_page', 30));

        return response()->json($logs);
    }

    public function report(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $logs = CampaignCallLog::where('campaign_id', $campaign->id)->get();

        $outcomeCounts = [];
        foreach (CampaignOutcomeEnum::cases() as $case) {
            $count = $logs->where('outcome', $case->value)->count();
            if ($count > 0 || in_array($case->value, ['completed', 'answered', 'no_answer', 'busy', 'opted_out'], true)) {
                $outcomeCounts[] = [
                    'outcome' => $case->value,
                    'label' => $case->label(),
                    'count' => $count,
                ];
            }
        }

        $skippedDncCount = $campaign->campaignContacts()->where('status', 'skipped_dnc')->count();
        $avgListenPercent = (int) round((float) $logs->avg('listen_percentage'));
        $avgDuration = (int) round((float) $logs->avg('duration_seconds'));
        $dtmfPressedCount = $logs->whereNotNull('dtmf_input')->count();
        $transfersCount = $logs->where('outcome', CampaignOutcomeEnum::HUMAN_TRANSFER_REQUESTED->value)->count();

        return response()->json([
            'campaign' => $campaign,
            'summary' => [
                'total_contacts' => $campaign->total_contacts,
                'completed_contacts' => $campaign->completed_contacts,
                'success_count' => $campaign->success_count,
                'failed_count' => $campaign->failed_count,
                'skipped_dnc_count' => $skippedDncCount,
                'avg_listen_percentage' => $avgListenPercent,
                'avg_duration_seconds' => $avgDuration,
                'dtmf_interactions' => $dtmfPressedCount,
                'human_transfers' => $transfersCount,
            ],
            'outcomes_chart' => $outcomeCounts,
        ]);
    }

    public function export(Campaign $campaign): Response
    {
        $this->authorize('view', $campaign);

        $logs = CampaignCallLog::where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->get();

        $csvLines = [
            implode(',', [
                'ID',
                'Phone Number',
                'Attempt',
                'Outcome',
                'Duration (s)',
                'Listen (%)',
                'DTMF Input',
                'Transfer Status',
                'Started At',
            ]),
        ];

        foreach ($logs as $log) {
            $csvLines[] = implode(',', [
                $log->id,
                $log->phone_number,
                $log->attempt_number,
                $log->outcome,
                $log->duration_seconds,
                $log->listen_percentage,
                $log->dtmf_input ?? '',
                $log->agent_transfer_status ?? '',
                $log->started_at?->toDateTimeString() ?? '',
            ]);
        }

        // Add UTF-8 BOM for Persian Excel compatibility
        $content = "\xEF\xBB\xBF".implode("\n", $csvLines);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="campaign-'.$campaign->id.'-report.csv"',
        ]);
    }

    public function importContactList(Request $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'csv_content' => ['nullable', 'string'],
            'phone_numbers' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        if (! empty($validated['csv_content'])) {
            $result = $this->importer->importFromCsvContent(
                $validated['name'],
                $validated['csv_content'],
                $user->id,
                $user->branch_id
            );
        } else {
            $result = $this->importer->importRows(
                $validated['name'],
                $validated['phone_numbers'] ?? [],
                'manual',
                $user->id,
                $user->branch_id
            );
        }

        return response()->json([
            'success' => true,
            'message' => "لیست مخاطبین پردازش شد ({$result['contact_list']->valid_count} شماره معتبر، {$result['duplicate_count']} تکراری، {$result['invalid_count']} نامعتبر).",
            'contact_list' => $result['contact_list'],
            'valid_phones' => $result['valid_phones'],
            'invalid_count' => $result['invalid_count'],
            'duplicate_count' => $result['duplicate_count'],
        ], 201);
    }
}
