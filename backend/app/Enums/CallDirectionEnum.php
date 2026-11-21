<?php

namespace App\Enums;

enum CallDirectionEnum: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::INBOUND => 'ورودی',
            self::OUTBOUND => 'خروجی',
        };
    }
}
