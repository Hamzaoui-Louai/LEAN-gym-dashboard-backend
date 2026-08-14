<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\RepairBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairBill>
 */
class RepairBillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'amount' => fake()->randomFloat(2, 20, 400),
            'repair_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'description' => fake()->sentence(4),
        ];
    }
}
