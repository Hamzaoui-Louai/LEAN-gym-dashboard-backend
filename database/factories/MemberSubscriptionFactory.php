<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberSubscription>
 */
class MemberSubscriptionFactory extends Factory
{
    public const PLANS = [
        'Monthly' => 45,
        'Quarterly' => 120,
        'Annual' => 420,
        'Pay-as-you-go' => 10,
    ];

    public function definition(): array
    {
        $plan = fake()->randomElement(array_keys(self::PLANS));

        return [
            'member_id' => Member::factory(),
            'plan_name' => $plan,
            'price' => self::PLANS[$plan],
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ];
    }
}
