<?php

namespace App\Services\Campaign;

use App\Models\CampaignContact;
use App\Models\DoNotCall;

final class DNCChecker
{
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if (str_starts_with($digits, '0098') && strlen($digits) === 14) {
            return '0'.substr($digits, 4);
        }

        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }

    /**
     * Check if a phone number is on the Do Not Call (DNC) list.
     * MUST be checked BEFORE dispatching any campaign call job.
     */
    public function isBlocked(string $phoneNumber): bool
    {
        $normalized = self::normalizePhone($phoneNumber);

        return DoNotCall::whereIn('phone_number', [$phoneNumber, $normalized])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Record an opt-out (e.g. via DTMF '9' or manual request) and cancel pending campaign calls for this number.
     */
    public function optOut(
        string $phoneNumber,
        string $source = 'dtmf_opt_out',
        ?string $reason = 'لغو اشتراک توسط مخاطب',
        ?int $addedBy = null
    ): DoNotCall {
        $normalized = self::normalizePhone($phoneNumber);

        $dnc = DoNotCall::updateOrCreate(
            ['phone_number' => $normalized],
            [
                'reason' => $reason,
                'source' => $source,
                'added_by' => $addedBy,
                'added_at' => now(),
                'expires_at' => null,
            ]
        );

        // Immediately cancel any pending/queued campaign contacts for this phone number
        CampaignContact::whereIn('phone_number', [$phoneNumber, $normalized])
            ->whereIn('status', ['pending', 'queued'])
            ->update([
                'status' => 'opted_out',
                'final_disposition' => 'opted_out',
                'opted_out_at' => now(),
            ]);

        return $dnc;
    }
}
