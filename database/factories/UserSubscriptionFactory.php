<?php

namespace Database\Factories;

use App\Enums\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSubscription>
 */
class UserSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan' => SubscriptionPlan::Basic,
            'valid_until' => now()->addMonth(),
        ];
    }
}
