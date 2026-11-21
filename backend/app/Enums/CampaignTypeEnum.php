<?php

namespace App\Enums;

enum CampaignTypeEnum: string
{
    case AUDIO = 'audio';
    case TTS = 'tts';
    case IVR = 'ivr';

    public function label(): string
    {
        return match ($this) {
            self::AUDIO => 'پخش فایل صوتی',
            self::TTS => 'تبدیل متن به گفتار (TTS)',
            self::IVR => 'تعاملی کلیدمحور (DTMF IVR)',
        };
    }
}
