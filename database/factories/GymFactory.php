<?php

namespace Database\Factories;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gym>
 */
class GymFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'LEAN '.fake()->city(),
            'description' => fake()->sentence(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'logo' => null,
            'opening_time' => '06:00',
            'closing_time' => '22:00',
            'days_open' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'status' => GymStatus::Active,
        ];
    }
}
