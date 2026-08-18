<?php

namespace Database\Factories;

use App\Enums\ClassType;
use App\Models\Course;
use App\Models\CourseOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'code' => 'A11.'.fake()->numerify('#####'),
            'name' => fake()->words(3, true),
            'sks' => fake()->randomElement([2, 3, 4]),
            'class_type' => ClassType::Theory,
        ];
    }
}
