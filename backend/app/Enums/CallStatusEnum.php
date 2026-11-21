<?php

namespace App\Enums;

enum CallStatusEnum: string
{
    case RINGING = 'ringing';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case TRANSFERRED = 'transferred';
    case ENDED = 'ended';
    case MISSED = 'missed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::RINGING => 'در حال زنگ خوردن',
            self::ACTIVE => 'در حال مکالمه',
            self::ON_HOLD => 'در انتظار (Hold)',
            self::TRANSFERRED => 'منتقل شده',
            self::ENDED => 'پایان یافته',
            self::MISSED => 'تماس بی‌پاسخ',
            self::FAILED => 'ناموفق',
        };
    }
}
