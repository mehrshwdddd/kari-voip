<?php

namespace App\Enums;

enum NoteTypeEnum: string
{
    case NOTE = 'note';
    case TASK = 'task';
    case FOLLOW_UP = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::NOTE => 'یادداشت',
            self::TASK => 'وظیفه',
            self::FOLLOW_UP => 'پیگیری بعدی',
        };
    }
}
