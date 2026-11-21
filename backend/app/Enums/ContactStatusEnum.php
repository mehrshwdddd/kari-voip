<?php

namespace App\Enums;

enum ContactStatusEnum: string
{
    case ACTIVE = 'active';
    case VIP = 'vip';
    case BLOCKED = 'blocked';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'عادی',
            self::VIP => 'مشتری ویژه (VIP)',
            self::BLOCKED => 'مسدود شده',
            self::INACTIVE => 'غیرفعال',
        };
    }
}
