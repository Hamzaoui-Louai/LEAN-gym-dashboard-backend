<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Gym;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'joined_at' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'status' => MemberStatus::Active,
        ];
    }
}
