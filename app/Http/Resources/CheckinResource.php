<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckinResource extends JsonResource
{
    /**
     * Shape matches the frontend mock in `frontend/src/lib/checkins.js`.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'date' => $this->date?->toDateString(),
            'check_in' => $this->formatTime($this->check_in),
            'check_out' => $this->check_out ? $this->formatTime($this->check_out) : null,
        ];
    }

    private function formatTime(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }
}
