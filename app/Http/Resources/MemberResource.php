<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * Shape matches the frontend mock in `frontend/src/lib/members.js`.
     */
    public function toArray(Request $request): array
    {
        $subscription = $this->subscriptions->sortByDesc('starts_at')->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'joined_at' => $this->joined_at?->toDateString(),
            'membership' => $subscription ? [
                'plan' => $subscription->plan_name,
                'price' => (float) $subscription->price,
                'started_at' => $subscription->starts_at?->toDateString(),
                'ends_at' => $subscription->ends_at?->toDateString(),
            ] : null,
            'payments' => $this->subscriptions
                ->flatMap(fn ($sub) => $sub->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'date' => $payment->paid_at?->toDateString(),
                    'plan' => $sub->plan_name,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method,
                    'status' => $payment->status->value,
                ]))
                ->values(),
        ];
    }
}
