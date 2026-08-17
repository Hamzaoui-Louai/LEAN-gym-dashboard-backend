<?php

namespace App\Http\Controllers\Api;

use App\Enums\EquipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EquipmentResource;
use App\Http\Resources\RepairResource;
use App\Models\Equipment;
use App\Models\RepairBill;
use App\Services\FinanceOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class EquipmentController extends Controller
{
    private const STATES = ['operational', 'in_use', 'under_repair', 'out_of_order'];

    public function __construct(private readonly FinanceOverviewService $finances) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $equipment = $request->user()->gym?->equipment()
            ->with(['purchaseBills', 'repairBills'])
            ->latest('created_at')
            ->get() ?? collect();

        return EquipmentResource::collection($equipment);
    }

    public function store(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json(['message' => 'No gym found for this account.'], 403);
        }

        $data = $this->validateData($request);

        $equipment = Equipment::create([
            'gym_id' => $gym->id,
            'name' => $data['name'],
            'category' => $data['category'],
            'purchase_date' => $data['purchased_at'],
            'status' => $this->statusFor($data['state']),
        ]);

        $this->upsertPurchaseBill($equipment, $data);

        $this->finances->flush($gym);

        return EquipmentResource::make($equipment->load(['purchaseBills', 'repairBills']))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $equipment = $this->equipmentFor($request, $id);

        if (! $equipment) {
            return response()->json(['message' => 'Equipment not found.'], 404);
        }

        $data = $this->validateData($request);

        $equipment->update([
            'name' => $data['name'],
            'category' => $data['category'],
            'purchase_date' => $data['purchased_at'],
            'status' => $this->statusFor($data['state']),
        ]);

        $this->upsertPurchaseBill($equipment, $data);

        if ($gym = $request->user()->gym) {
            $this->finances->flush($gym);
        }

        return EquipmentResource::make($equipment->load(['purchaseBills', 'repairBills']))->response();
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

    private function equipmentFor(Request $request, int $id): ?Equipment
    {
        return $request->user()->gym?->equipment()->find($id);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'state' => ['required', Rule::in(self::STATES)],
            'purchased_at' => ['required', 'date'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function statusFor(string $state): EquipmentStatus
    {
        return match ($state) {
            'under_repair' => EquipmentStatus::Maintenance,
            'out_of_order' => EquipmentStatus::Broken,
            default => EquipmentStatus::Available,
        };
    }

    private function upsertPurchaseBill(Equipment $equipment, array $data): void
    {
        $bill = $equipment->purchaseBills()->orderByDesc('purchase_date')->first();

        $attributes = [
            'amount' => $data['price'],
            'purchase_date' => $data['purchased_at'],
        ];

        if ($bill) {
            $bill->update($attributes);

            return;
        }

        $equipment->purchaseBills()->create($attributes);
    }
}
