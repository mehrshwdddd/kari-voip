<?php

namespace App\Enums;

enum ContactSourceEnum: string
{
    case CALL = 'call';
    case WALK_IN = 'walk_in';
    case REFERRAL = 'referral';
    case CAMPAIGN = 'campaign';
    case ONLINE = 'online';

    public function label(): string
    {
        return match ($this) {
            self::CALL => 'تماس تلفنی',
            self::WALK_IN => 'مراجعه حضوری',
            self::REFERRAL => 'معرفی / ارجاع',
            self::CAMPAIGN => 'کمپین تبلیغاتی',
            self::ONLINE => 'وب‌سایت / آنلاین',
        };
    }
}
