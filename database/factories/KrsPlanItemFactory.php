<?php

namespace Database\Factories;

use App\Models\CourseSection;
use App\Models\KrsPlan;
use App\Models\KrsPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KrsPlanItem>
 */
class KrsPlanItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'krs_plan_id' => KrsPlan::factory(),
            'course_section_id' => CourseSection::factory(),
        ];
    }
}
