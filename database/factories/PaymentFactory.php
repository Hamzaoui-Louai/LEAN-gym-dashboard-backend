<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MemberSubscription;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_subscription_id' => MemberSubscription::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'paid_at' => now(),
            'method' => fake()->randomElement(['Card', 'Cash', 'Transfer']),
            'status' => PaymentStatus::Paid,
        ];
    }
}
