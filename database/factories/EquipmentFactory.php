<?php

namespace Database\Factories;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => fake()->randomElement([
                'Squat Rack',
                'Power Rack',
                'Leg Press',
                'Treadmill',
                'Stationary Bike',
                'Kettlebell Set',
                'Rowing Machine',
                'Dumbbell Rack',
            ]),
            'category' => fake()->randomElement(['Strength', 'Cardio', 'Flexibility', 'Functional']),
            'purchase_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'status' => EquipmentStatus::Available,
        ];
    }
}
