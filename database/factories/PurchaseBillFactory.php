<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\PurchaseBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseBill>
 */
class PurchaseBillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'amount' => fake()->randomFloat(2, 100, 3000),
            'purchase_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'vendor' => fake()->company(),
        ];
    }
}
