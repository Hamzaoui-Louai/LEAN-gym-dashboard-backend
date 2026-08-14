<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipmentResource;
use App\Http\Resources\RepairResource;
use App\Models\RepairBill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $equipment = $request->user()->gym?->equipment()
            ->with(['purchaseBills', 'repairBills'])
            ->latest('created_at')
            ->get() ?? collect();

        return EquipmentResource::collection($equipment);
    }

    public function repairs(Request $request): AnonymousResourceCollection
    {
        $gym = $request->user()->gym;

        $repairs = $gym
            ? RepairBill::query()
                ->whereHas('equipment', fn ($query) => $query->where('gym_id', $gym->id))
                ->with('equipment')
                ->orderByDesc('repair_date')
                ->get()
            : collect();

        return RepairResource::collection($repairs);
    }
}
