<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class AvailabilityEngine
{
    /**
     * Check if a staff member is available for a given service starting at $startsAt.
     *
     * @return array{available: bool, reason: ?string}
     */
    public function checkSlot(
        Staff $staff,
        Service $service,
        CarbonInterface $startsAt,
        ?int $ignoreAppointmentId = null
    ): array {
        $start = $startsAt->copy();
        $duration = max(5, $service->duration_minutes ?: 30);
        $buffer = max(0, $service->buffer_minutes ?? 0);
        $end = $start->copy()->addMinutes($duration);
        $dateString = $start->toDateString();
        $dayName = strtolower($start->englishDayOfWeek);

        // 1. Check Holidays
        if (Holiday::whereDate('date', $dateString)->exists()) {
            return [
                'available' => false,
                'reason' => 'این تاریخ در تقویم تعطیلات رسمی ثبت شده است.',
            ];
        }

        // 2. Check Staff Schedule for day of week
        $schedule = $staff->schedules()->where('day_of_week', $dayName)->first();
        if ($schedule) {
            if (! $schedule->is_working) {
                return [
                    'available' => false,
                    'reason' => 'کارشناس در این روز هفته ساعت کاری فعال ندارد.',
                ];
            }

            $schedStart = Carbon::parse("{$dateString} {$schedule->start_time}", $start->getTimezone());
            $schedEnd = Carbon::parse("{$dateString} {$schedule->end_time}", $start->getTimezone());

            if ($start->lt($schedStart) || $end->gt($schedEnd)) {
                return [
                    'available' => false,
                    'reason' => 'زمان انتخاب‌شده خارج از ساعت کاری کارشناس است.',
                ];
            }
        }

        // 3. Check Staff Breaks
        $breaks = $staff->breaks()->where('day_of_week', $dayName)->get();
        foreach ($breaks as $brk) {
            $breakStart = Carbon::parse("{$dateString} {$brk->start_time}", $start->getTimezone());
            $breakEnd = Carbon::parse("{$dateString} {$brk->end_time}", $start->getTimezone());

            if ($start->lt($breakEnd) && $end->gt($breakStart)) {
                return [
                    'available' => false,
                    'reason' => 'زمان انتخاب‌شده با ساعت استراحت کارشناس تداخل دارد.',
                ];
            }
        }

        // 4. Check Staff Time Offs
        $timeOffs = $staff->timeOffs()->whereDate('date', $dateString)->get();
        foreach ($timeOffs as $off) {
            if (empty($off->start_time) || empty($off->end_time)) {
                return [
                    'available' => false,
                    'reason' => 'کارشناس در این تاریخ مرخصی کامل دارد.',
                ];
            }

            $offStart = Carbon::parse("{$dateString} {$off->start_time}", $start->getTimezone());
            $offEnd = Carbon::parse("{$dateString} {$off->end_time}", $start->getTimezone());

            if ($start->lt($offEnd) && $end->gt($offStart)) {
                return [
                    'available' => false,
                    'reason' => 'زمان انتخاب‌شده با مرخصی ساعتی کارشناس تداخل دارد.',
                ];
            }
        }

        // 5. Check Overlapping Appointments (including buffer time)
        $endWithBuffer = $end->copy()->addMinutes($buffer);
        $startMinusBuffer = $start->copy()->subMinutes($buffer);

        $conflictQuery = Appointment::where('staff_id', $staff->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($query) use ($startMinusBuffer, $endWithBuffer) {
                $query->where('starts_at', '<', $endWithBuffer)
                    ->where('ends_at', '>', $startMinusBuffer);
            });

        if ($ignoreAppointmentId) {
            $conflictQuery->where('id', '!=', $ignoreAppointmentId);
        }

        if ($conflictQuery->exists()) {
            return [
                'available' => false,
                'reason' => 'این بازه زمانی (یا زمان تنفس بین دو نوبت) قبلاً رزرو شده است.',
            ];
        }

        return [
            'available' => true,
            'reason' => null,
        ];
    }

    /**
     * Generate time slots for a given date, staff, and service.
     *
     * @return array<int, array{start_time: string, end_time: string, starts_at: string, available: bool, reason: ?string}>
     */
    public function getAvailableSlots(Staff $staff, Service $service, string $dateString): array
    {
        $date = Carbon::parse($dateString, 'Asia/Tehran');
        $dayName = strtolower($date->englishDayOfWeek);
        $schedule = $staff->schedules()->where('day_of_week', $dayName)->first();

        $startTimeStr = $schedule ? $schedule->start_time : '09:00';
        $endTimeStr = $schedule ? $schedule->end_time : '17:00';

        if ($schedule && ! $schedule->is_working) {
            return [];
        }

        $cursor = Carbon::parse("{$dateString} {$startTimeStr}", 'Asia/Tehran');
        $dayEnd = Carbon::parse("{$dateString} {$endTimeStr}", 'Asia/Tehran');
        $duration = max(15, $service->duration_minutes ?: 30);
        $stepMinutes = max(15, $duration + ($service->buffer_minutes ?? 0));

        $slots = [];
        while ($cursor->copy()->addMinutes($duration)->lte($dayEnd)) {
            $slotEnd = $cursor->copy()->addMinutes($duration);
            $check = $this->checkSlot($staff, $service, $cursor);

            $slots[] = [
                'start_time' => $cursor->format('H:i'),
                'end_time' => $slotEnd->format('H:i'),
                'starts_at' => $cursor->toIso8601String(),
                'available' => $check['available'],
                'reason' => $check['reason'],
            ];

            $cursor->addMinutes($stepMinutes);
        }

        return $slots;
    }
}
