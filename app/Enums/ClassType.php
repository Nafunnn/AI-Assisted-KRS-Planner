<?php

namespace App\Enums;

enum ClassType: string
{
    case Theory = 'T';
    case Practical = 'P';

    public function label(): string
    {
        return match ($this) {
            self::Theory => 'Teori',
            self::Practical => 'Praktikum',
        };
    }
}
