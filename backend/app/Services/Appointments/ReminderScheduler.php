<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Services\Messaging\SmsManager;

final class ReminderScheduler
{
    public function __construct(
        private readonly SmsManager $smsManager
    ) {}

    /**
     * Schedule default 24h and 1h SMS reminders for a booked appointment.
     */
    public function scheduleForAppointment(Appointment $appointment): void
    {
        $appointment->loadMissing(['contact', 'service', 'staff']);
        $contactName = $appointment->contact?->full_name ?: 'مشتری گرامی';
        $serviceName = $appointment->service?->name ?: 'خدمت';
        $timeStr = $appointment->starts_at->format('H:i');

        // 1. 24-hour reminder
        AppointmentReminder::create([
            'appointment_id' => $appointment->id,
            'channel' => 'sms',
            'type' => '24h',
            'scheduled_at' => $appointment->starts_at->copy()->subHours(24),
            'status' => 'pending',
            'message' => "{$contactName} عزیز، یادآوری نوبت {$serviceName} شما برای فردا ساعت {$timeStr}. کد نوبت: {$appointment->booking_number}",
        ]);

        // 2. 1-hour reminder
        AppointmentReminder::create([
            'appointment_id' => $appointment->id,
            'channel' => 'sms',
            'type' => '1h',
            'scheduled_at' => $appointment->starts_at->copy()->subHour(),
            'status' => 'pending',
            'message' => "{$contactName} عزیز، نوبت {$serviceName} شما ۱ ساعت دیگر (ساعت {$timeStr}) برگزار می‌شود.",
        ]);
    }

    /**
     * Process and send any due pending reminders.
     */
    public function dispatchDueReminders(): int
    {
        $due = AppointmentReminder::with('appointment.contact')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->limit(50)
            ->get();

        $sentCount = 0;

        foreach ($due as $reminder) {
            $contact = $reminder->appointment?->contact;
            if (! $contact || empty($contact->phone_primary)) {
                $reminder->update(['status' => 'failed']);

                continue;
            }

            $log = $this->smsManager->sendSms(
                $contact->phone_primary,
                (string) $reminder->message,
                $contact->id,
                null,
                [
                    'reference_type' => 'appointment',
                    'reference_id' => $reminder->appointment_id,
                ]
            );

            $reminder->update([
                'status' => $log->status === 'sent' ? 'sent' : 'failed',
                'sent_at' => now(),
            ]);

            if ($log->status === 'sent') {
                $reminder->appointment?->update(['reminder_sent_at' => now()]);
                $sentCount++;
            }
        }

        return $sentCount;
    }
}
