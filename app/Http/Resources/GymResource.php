<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GymResource extends JsonResource
{
    /**
     * Shape matches the frontend mock in `frontend/src/lib/gym.js`.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'logo' => $this->logo,
            'email' => $this->email,
            'phone' => $this->phone,
            'opens_at' => $this->opening_time ? Carbon::parse($this->opening_time)->format('H:i') : null,
            'closes_at' => $this->closing_time ? Carbon::parse($this->closing_time)->format('H:i') : null,
            'days_open' => $this->days_open ?? [],
            'status' => $this->status->value,
            'registered_at' => $this->created_at?->toDateString(),
        ];
    }
}
