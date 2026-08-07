<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case FreeTrial = 'free_trial';
    case Basic = 'basic';
    case Pro = 'pro';
    case Enterprise = 'enterprise';
}
