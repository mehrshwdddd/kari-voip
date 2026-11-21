<?php

namespace App\Enums;

enum CampaignOutcomeEnum: string
{
    case ANSWERED = 'answered';
    case NO_ANSWER = 'no_answer';
    case BUSY = 'busy';
    case FAILED = 'failed';
    case INVALID_NUMBER = 'invalid_number';
    case VOICEMAIL = 'voicemail';
    case HUMAN_TRANSFER_REQUESTED = 'human_transfer_requested';
    case DTMF_SELECTED = 'dtmf_selected';
    case OPTED_OUT = 'opted_out';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::ANSWERED => 'پاسخ داده شده',
            self::NO_ANSWER => 'بی‌پاسخ',
            self::BUSY => 'مشغول',
            self::FAILED => 'خطای شبکه / ناموفق',
            self::INVALID_NUMBER => 'شماره نامعتبر',
            self::VOICEMAIL => 'پیغام‌گیر صوتی',
            self::HUMAN_TRANSFER_REQUESTED => 'درخواست اتصال به اپراتور',
            self::DTMF_SELECTED => 'انتخاب کلید (DTMF)',
            self::OPTED_OUT => 'لغو اشتراک (DNC)',
            self::COMPLETED => 'پخش کامل و موفق',
        };
    }

    public function isSuccess(): bool
    {
        return in_array($this, [
            self::ANSWERED,
            self::HUMAN_TRANSFER_REQUESTED,
            self::DTMF_SELECTED,
            self::COMPLETED,
        ], true);
    }

    public function isRetryable(): bool
    {
        return in_array($this, [
            self::NO_ANSWER,
            self::BUSY,
            self::FAILED,
        ], true);
    }
}
