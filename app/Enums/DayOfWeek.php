<?php

namespace App\Enums;

enum DayOfWeek: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Senin',
            self::Tuesday => 'Selasa',
            self::Wednesday => 'Rabu',
            self::Thursday => 'Kamis',
            self::Friday => 'Jumat',
            self::Saturday => 'Sabtu',
            self::Sunday => 'Minggu',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Monday => 'Sen',
            self::Tuesday => 'Sel',
            self::Wednesday => 'Rab',
            self::Thursday => 'Kam',
            self::Friday => 'Jum',
            self::Saturday => 'Sab',
            self::Sunday => 'Min',
        };
    }

    public static function fromIndonesian(string $day): ?self
    {
        return match (strtoupper(trim($day))) {
            'SENIN' => self::Monday,
            'SELASA' => self::Tuesday,
            'RABU' => self::Wednesday,
            'KAMIS' => self::Thursday,
            'JUMAT' => self::Friday,
            'SABTU' => self::Saturday,
            'MINGGU' => self::Sunday,
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function weekdays(): array
    {
        return [
            self::Monday,
            self::Tuesday,
            self::Wednesday,
            self::Thursday,
            self::Friday,
        ];
    }
}
