<?php

namespace Database\Factories;

use App\Enums\TimePeriod;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSection>
 */
class CourseSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'group_code' => 'A11.'.fake()->numerify('####'),
            'time_period' => TimePeriod::Morning,
        ];
    }
}
