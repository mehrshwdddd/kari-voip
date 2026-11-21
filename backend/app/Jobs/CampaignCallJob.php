<?php

namespace App\Jobs;

use App\Enums\CampaignOutcomeEnum;
use App\Enums\CampaignStatusEnum;
use App\Events\CampaignCallCompleted;
use App\Events\CampaignCallStarted;
use App\Models\Campaign;
use App\Models\CampaignCallLog;
use App\Models\CampaignContact;
use App\Services\Campaign\DNCChecker;
use App\Services\Campaign\RetryScheduler;
use App\Services\Telephony\Contracts\TelephonyAdapterInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Connection;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Connection('redis')]
#[Queue('campaigns')]
#[Tries(3)]
#[Backoff([10, 30, 60])]
final class CampaignCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $campaignContactId,
        public readonly ?string $simulatedOutcome = null,
        public readonly ?string $simulatedDtmf = null
    ) {}

    public function handle(
        TelephonyAdapterInterface $telephony,
        DNCChecker $dncChecker,
        RetryScheduler $retryScheduler
    ): void {
        $campaign = Campaign::find($this->campaignId);
        $campaignContact = CampaignContact::find($this->campaignContactId);

        if (! $campaign || ! $campaignContact) {
            return;
        }

        if ($campaign->status !== CampaignStatusEnum::RUNNING) {
            return;
        }

        $attemptNumber = $campaignContact->attempt_count + 1;
        $startedAt = now();

        $campaignContact->update([
            'status' => 'calling',
            'attempt_count' => $attemptNumber,
            'last_attempt_at' => $startedAt,
        ]);

        event(new CampaignCallStarted($campaign, $campaignContact));

        $pbxResult = $telephony->originateCall(
            $campaign->caller_id ?: '02188889999',
            $campaignContact->phone_number,
            ['campaign_id' => $campaign->id]
        );

        $outcomeValue = $this->simulatedOutcome ?? CampaignOutcomeEnum::COMPLETED->value;
        $dtmfInput = $this->simulatedDtmf;
        $agentTransferStatus = null;

        // Check DTMF actions
        if ($dtmfInput === '9') {
            $outcomeValue = CampaignOutcomeEnum::OPTED_OUT->value;
            $dncChecker->optOut(
                $campaignContact->phone_number,
                'dtmf_opt_out',
                "لغو اشتراک با کلید ۹ در کمپین #{$campaign->id}"
            );
        } elseif ($dtmfInput === '1' && $campaign->human_transfer_enabled) {
            $outcomeValue = CampaignOutcomeEnum::HUMAN_TRANSFER_REQUESTED->value;
            $agentTransferStatus = 'transferred_to_queue';
        } elseif ($dtmfInput !== null && $dtmfInput !== '') {
            $outcomeValue = CampaignOutcomeEnum::DTMF_SELECTED->value;
        }

        $outcomeEnum = CampaignOutcomeEnum::tryFrom($outcomeValue) ?? CampaignOutcomeEnum::COMPLETED;
        $isAnswered = $outcomeEnum->isSuccess() || $outcomeEnum === CampaignOutcomeEnum::OPTED_OUT;

        $durationSeconds = $isAnswered ? 28 : 0;
        $listenDuration = $isAnswered ? 25 : 0;
        $listenPercentage = $isAnswered ? ($outcomeEnum === CampaignOutcomeEnum::COMPLETED ? 100 : 85) : 0;

        $callLog = CampaignCallLog::create([
            'campaign_id' => $campaign->id,
            'campaign_contact_id' => $campaignContact->id,
            'contact_id' => $campaignContact->contact_id,
            'phone_number' => $campaignContact->phone_number,
            'attempt_number' => $attemptNumber,
            'voip_call_id' => $pbxResult['voip_call_id'],
            'started_at' => $startedAt,
            'answered_at' => $isAnswered ? $startedAt->copy()->addSeconds(3) : null,
            'ended_at' => $startedAt->copy()->addSeconds(3 + $durationSeconds),
            'duration_seconds' => $durationSeconds,
            'listen_duration_seconds' => $listenDuration,
            'listen_percentage' => $listenPercentage,
            'dtmf_input' => $dtmfInput,
            'outcome' => $outcomeEnum->value,
            'agent_transfer_status' => $agentTransferStatus,
            'recording_path' => $campaign->record_calls ? "recordings/camp_{$pbxResult['voip_call_id']}.wav" : null,
            'retry_count' => max(0, $attemptNumber - 1),
            'error_message' => $outcomeEnum->isRetryable() ? 'عدم پاسخگویی یا مشغولی خط مقصد' : null,
            'created_at' => now(),
        ]);

        // Determine retry vs final completion
        if ($retryScheduler->shouldRetry($outcomeEnum, $attemptNumber, $campaign->max_retry_count)) {
            $nextAttempt = $retryScheduler->calculateNextAttemptAt($campaign, $attemptNumber, $startedAt);
            $campaignContact->update([
                'status' => 'pending',
                'next_attempt_at' => $nextAttempt,
                'final_disposition' => $outcomeEnum->value,
            ]);
        } else {
            $finalStatus = $outcomeEnum === CampaignOutcomeEnum::OPTED_OUT
                ? 'opted_out'
                : ($outcomeEnum->isSuccess() ? 'completed' : 'failed');

            $campaignContact->update([
                'status' => $finalStatus,
                'next_attempt_at' => null,
                'final_disposition' => $outcomeEnum->value,
                'opted_out_at' => $outcomeEnum === CampaignOutcomeEnum::OPTED_OUT ? now() : null,
            ]);

            $campaign->increment('completed_contacts');
            if ($outcomeEnum->isSuccess()) {
                $campaign->increment('success_count');
            } else {
                $campaign->increment('failed_count');
            }

            $campaign->refresh();
            if ($campaign->total_contacts > 0 && $campaign->completed_contacts >= $campaign->total_contacts) {
                $campaign->update(['status' => CampaignStatusEnum::COMPLETED]);
            }
        }

        event(new CampaignCallCompleted($campaign->fresh(), $callLog));
    }
}
