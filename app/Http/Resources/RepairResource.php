<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepairResource extends JsonResource
{
    /**
     * Shape matches the frontend mock repairs in `frontend/src/lib/equipment.js`.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->repair_date?->toDateString(),
            'equipment' => $this->equipment?->name,
            'issue' => $this->description,
            'cost' => (float) $this->amount,
            'status' => 'paid',
        ];
    }
}
