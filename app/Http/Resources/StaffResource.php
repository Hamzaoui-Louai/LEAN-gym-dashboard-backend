<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    /**
     * Shape matches the frontend mock in `frontend/src/lib/staff.js`.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->position,
            'status' => $this->status->value,
            'joined_at' => $this->hire_date?->toDateString(),
            'salary' => (float) $this->salary,
            'payslips' => $this->payslips
                ->sortByDesc(fn ($payslip) => $payslip->year * 12 + $payslip->month)
                ->values()
                ->map(fn ($payslip) => [
                    'id' => $payslip->id,
                    'date' => $payslip->date,
                    'period' => $payslip->period,
                    'amount' => (float) $payslip->amount,
                    'method' => $payslip->method,
                    'status' => $payslip->status->value,
                ]),
        ];
    }
}
