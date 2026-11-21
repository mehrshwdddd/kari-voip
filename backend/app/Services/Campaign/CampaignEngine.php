<?php

namespace App\Services\Campaign;

use App\Enums\CampaignStatusEnum;
use App\Jobs\CampaignCallJob;
use App\Models\Campaign;
use Carbon\CarbonInterface;

final class CampaignEngine
{
    public function __construct(
        private readonly DNCChecker $dncChecker,
        private readonly CallHoursValidator $hoursValidator
    ) {}

    /**
     * Start or process a batch of campaign calls.
     * Enforces Call Hours window and DNC check BEFORE dispatching any job.
     *
     * @return array{dispatched: int, skipped_dnc: int, outside_hours: bool}
     */
    public function dispatchBatch(Campaign $campaign, ?CarbonInterface $now = null): array
    {
        $checkTime = $now ?? now();

        if (! $this->hoursValidator->isWithinAllowedWindow($campaign, $checkTime)) {
            return [
                'dispatched' => 0,
                'skipped_dnc' => 0,
                'outside_hours' => true,
            ];
        }

        if ($campaign->status !== CampaignStatusEnum::RUNNING) {
            $campaign->update(['status' => CampaignStatusEnum::RUNNING]);
        }

        $limit = max(1, $campaign->max_concurrent_calls ?: 5);

        $pendingContacts = $campaign->campaignContacts()
            ->where('status', 'pending')
            ->where(function ($query) use ($checkTime) {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', $checkTime);
            })
            ->orderBy('id')
            ->limit($limit * 4)
            ->get();

        $dispatched = 0;
        $skippedDnc = 0;

        foreach ($pendingContacts as $campaignContact) {
            // CRITICAL RULE: DNC check happens BEFORE job dispatch, never inside the job
            if ($this->dncChecker->isBlocked($campaignContact->phone_number)) {
                $campaignContact->update([
                    'status' => 'skipped_dnc',
                    'final_disposition' => 'opted_out',
                    'opted_out_at' => now(),
                ]);

                $campaign->increment('completed_contacts');
                $campaign->increment('failed_count');
                $skippedDnc++;

                continue;
            }

            if ($dispatched >= $limit) {
                break;
            }

            $campaignContact->update(['status' => 'queued']);

            try {
                CampaignCallJob::dispatch($campaign->id, $campaignContact->id);
            } catch (\Throwable) {
                // Resilient local fallback if Redis server is not running
                CampaignCallJob::dispatchSync($campaign->id, $campaignContact->id);
            }
            $dispatched++;
        }

        $campaign->refresh();
        if ($campaign->total_contacts > 0 && $campaign->completed_contacts >= $campaign->total_contacts) {
            $campaign->update(['status' => CampaignStatusEnum::COMPLETED]);
        }

        return [
            'dispatched' => $dispatched,
            'skipped_dnc' => $skippedDnc,
            'outside_hours' => false,
        ];
    }
}
