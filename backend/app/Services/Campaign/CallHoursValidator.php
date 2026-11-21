<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use Carbon\CarbonInterface;

final class CallHoursValidator
{
    /**
     * Validate if the given timestamp (or current time in Asia/Tehran) falls within the campaign's allowed calling window.
     */
    public function isWithinAllowedWindow(Campaign $campaign, ?CarbonInterface $now = null): bool
    {
        $checkTime = ($now ? $now->copy() : now())->setTimezone('Asia/Tehran');

        // 1. Check scheduled start and end bounds if defined
        if ($campaign->scheduled_start_at && $checkTime->lt($campaign->scheduled_start_at)) {
            return false;
        }

        if ($campaign->scheduled_end_at && $checkTime->gt($campaign->scheduled_end_at)) {
            return false;
        }

        // 2. Check allowed days of week (e.g. ['saturday', 'sunday', ...])
        $allowedDays = $campaign->allowed_days;
        if (is_array($allowedDays) && count($allowedDays) > 0) {
            $dayName = strtolower($checkTime->englishDayOfWeek);
            $normalizedAllowed = array_map('strtolower', $allowedDays);
            if (! in_array($dayName, $normalizedAllowed, true)) {
                return false;
            }
        }

        // 3. Check allowed hours of day (e.g. '09:00' to '20:00')
        $startStr = $campaign->allowed_hours_start ?: '09:00';
        $endStr = $campaign->allowed_hours_end ?: '20:00';
        $currentHm = $checkTime->format('H:i');

        return $currentHm >= $startStr && $currentHm <= $endStr;
    }
}
