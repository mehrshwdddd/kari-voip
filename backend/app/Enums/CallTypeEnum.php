<?php

namespace App\Enums;

enum CallTypeEnum: string
{
    case MANUAL = 'manual';
    case CAMPAIGN = 'campaign';
    case IVR = 'ivr';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'مستقیم / منشی',
            self::CAMPAIGN => 'کمپین صوتی',
            self::IVR => 'تلفن گویا (IVR)',
        };
    }
}
