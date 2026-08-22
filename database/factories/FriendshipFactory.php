<?php

namespace Database\Factories;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Friendship>
 */
class FriendshipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'addressee_id' => User::factory(),
            'status' => FriendshipStatus::Pending,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FriendshipStatus::Accepted,
        ]);
    }
}
