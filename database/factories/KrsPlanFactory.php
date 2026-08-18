<?php

namespace Database\Factories;

use App\Enums\KrsPlanStatus;
use App\Models\CourseOffering;
use App\Models\KrsPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KrsPlan>
 */
class KrsPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_offering_id' => CourseOffering::factory(),
            'name' => 'Rencana KRS',
            'status' => KrsPlanStatus::Draft,
        ];
    }
}
