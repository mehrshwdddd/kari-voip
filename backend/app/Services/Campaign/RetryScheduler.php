<?php

namespace App\Services\Campaign;

use App\Enums\CampaignOutcomeEnum;
use App\Models\Campaign;
use Carbon\CarbonInterface;

final class RetryScheduler
{
    /**
     * Determine if a call with the given outcome and attempt number should be retried.
     */
    public function shouldRetry(
        string|CampaignOutcomeEnum $outcome,
        int $currentAttemptCount,
        int $maxRetryCount
    ): bool {
        if ($currentAttemptCount > $maxRetryCount) {
            return false;
        }

        // Total allowed retries after initial call = $maxRetryCount
        // e.g. attempt 1 fails -> 1 <= maxRetryCount (e.g. 2) -> retry #1 (attempt 2)
        // attempt 3 fails -> 3 > 2 -> stop
        $enum = $outcome instanceof CampaignOutcomeEnum
            ? $outcome
            : CampaignOutcomeEnum::tryFrom($outcome);

        if (! $enum) {
            return false;
        }

        return $enum->isRetryable();
    }

    /**
     * Calculate the next retry timestamp based on the campaign's retry_interval_minutes array.
     */
    public function calculateNextAttemptAt(
        Campaign $campaign,
        int $currentAttemptCount,
        ?CarbonInterface $from = null
    ): CarbonInterface {
        $base = ($from ? $from->copy() : now());
        $intervals = $campaign->retry_interval_minutes;

        if (! is_array($intervals) || count($intervals) === 0) {
            $intervals = [15, 60, 180];
        }

        $index = max(0, min($currentAttemptCount - 1, count($intervals) - 1));
        $minutes = max(1, (int) $intervals[$index]);

        return $base->addMinutes($minutes);
    }
}
