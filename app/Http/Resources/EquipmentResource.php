<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    /**
     * Shape matches the frontend mock in `frontend/src/lib/equipment.js`.
     * The backend 3-state `status` collapses to the mock 4-state `state`.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'state' => match ($this->status->value) {
                'maintenance' => 'under_repair',
                'broken' => 'out_of_order',
                default => 'operational',
            },
            'purchased_at' => $this->purchase_date?->toDateString(),
            'price' => (float) (
                $this->purchaseBills->sortByDesc('purchase_date')->first()?->amount ?? 0
            ),
            'image' => null,
        ];
    }
}
