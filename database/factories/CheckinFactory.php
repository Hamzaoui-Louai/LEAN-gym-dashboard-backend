<?php

namespace Database\Factories;

use App\Models\Checkin;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checkin>
 */
class CheckinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'date' => today()->format('Y-m-d'),
            'check_in' => sprintf('%02d:%02d', fake()->numberBetween(6, 10), fake()->numberBetween(0, 59)),
            'check_out' => null,
        ];
    }
}
