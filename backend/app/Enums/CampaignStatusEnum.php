<?php

namespace App\Enums;

enum CampaignStatusEnum: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'پیش‌نویس',
            self::SCHEDULED => 'زمان‌بندی شده',
            self::RUNNING => 'در حال اجرا',
            self::PAUSED => 'متوقف موقت',
            self::COMPLETED => 'تکمیل شده',
            self::CANCELLED => 'لغو شده',
        };
    }
}
