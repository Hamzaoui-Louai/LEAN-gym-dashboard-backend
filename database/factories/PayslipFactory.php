<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payslip;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'month' => now()->month,
            'year' => now()->year,
            'amount' => fake()->randomFloat(2, 1000, 3500),
            'paid_at' => now()->endOfMonth(),
            'method' => fake()->randomElement(['Card', 'Cash', 'Transfer']),
            'status' => PaymentStatus::Paid,
        ];
    }
}
