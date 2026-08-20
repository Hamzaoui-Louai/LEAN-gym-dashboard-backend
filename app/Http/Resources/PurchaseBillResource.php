<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseBillResource extends JsonResource
{
    /**
     * Shape matches the frontend mock purchases in `frontend/src/lib/equipment.js`.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->purchase_date?->toDateString(),
            'equipment' => $this->equipment?->name,
            'amount' => (float) $this->amount,
            'method' => 'Card',
            'status' => 'paid',
        ];
    }
}
