<?php

namespace Database\Factories;

use App\Enums\StaffStatus;
use App\Models\Gym;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    public const ROLES = [
        'Manager',
        'Personal trainer',
        'Strength coach',
        'Front desk',
        'Cleaning staff',
    ];

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'position' => fake()->randomElement(self::ROLES),
            'salary' => fake()->randomFloat(2, 1100, 3400),
            'hire_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'status' => StaffStatus::Active,
        ];
    }
}
