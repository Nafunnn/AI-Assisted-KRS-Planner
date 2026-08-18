<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\CourseSection;
use App\Models\SectionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionSchedule>
 */
class SectionScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_section_id' => CourseSection::factory(),
            'slot_number' => 1,
            'day' => DayOfWeek::Monday,
            'starts_at' => '07:00:00',
            'ends_at' => '09:30:00',
            'raw' => 'SENIN, 07:00:00 - 09:30:00',
        ];
    }
}
