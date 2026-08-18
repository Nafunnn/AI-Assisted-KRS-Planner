<?php

namespace App\Enums;

enum TimePeriod: string
{
    case Morning = 'P';
    case Evening = 'M';
    case MorningEvening = 'PM';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Pagi',
            self::Evening => 'Malam',
            self::MorningEvening => 'Pagi/Malam',
        };
    }
}
