<?php

namespace Database\Factories;

use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseOffering>
 */
class CourseOfferingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by_user_id' => User::factory()->admin(),
            'title' => fake()->sentence(3),
            'term' => '2025/2026-genap',
            'source_filename' => 'penawaran-matkul.xlsx',
            'catalog_version' => 1,
            'imported_at' => now(),
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }
}
