<?php

namespace App\Services\Krs;

use App\Enums\ClassType;
use App\Enums\DayOfWeek;
use App\Enums\TimePeriod;
use InvalidArgumentException;

class ScheduleParser
{
    /**
     * @return array{day: DayOfWeek, starts_at: string, ends_at: string}|null
     */
    public function parse(string $raw): ?array
    {
        $raw = trim($raw);

        if ($raw === '' || str_contains($raw, '-, -')) {
            return null;
        }

        if (! preg_match('/^([A-Z]+),\s*(\d{2}:\d{2}:\d{2})\s*-\s*(\d{2}:\d{2}:\d{2})$/u', $raw, $matches)) {
            throw new InvalidArgumentException("Format jadwal tidak valid: {$raw}");
        }

        $day = DayOfWeek::fromIndonesian($matches[1]);

        if ($day === null) {
            throw new InvalidArgumentException("Hari tidak dikenali: {$matches[1]}");
        }

        return [
            'day' => $day,
            'starts_at' => $matches[2],
            'ends_at' => $matches[3],
        ];
    }

    public function parseClassType(string $value): ClassType
    {
        return match (strtoupper(trim($value))) {
            'T' => ClassType::Theory,
            'P' => ClassType::Practical,
            default => throw new InvalidArgumentException("T/P tidak valid: {$value}"),
        };
    }

    public function parseTimePeriod(string $value): TimePeriod
    {
        return match (strtoupper(trim($value))) {
            'P' => TimePeriod::Morning,
            'M' => TimePeriod::Evening,
            'PM' => TimePeriod::MorningEvening,
            default => throw new InvalidArgumentException("Jam tidak valid: {$value}"),
        };
    }

    /**
     * @return list<string>
     */
    public function expectedHeaders(): array
    {
        return [
            'Kode MK',
            'Nama Mata Kuliah',
            'SKS',
            'T/P',
            'Kelp.',
            'Jadwal 1',
            'Jadwal 2',
            'Jadwal 3',
            'Jam',
        ];
    }
}
