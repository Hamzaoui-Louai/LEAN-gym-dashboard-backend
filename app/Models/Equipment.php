<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['gym_id', 'name', 'category', 'purchase_date', 'status'])]
class Equipment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'status' => EquipmentStatus::class,
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function purchaseBills(): HasMany
    {
        return $this->hasMany(PurchaseBill::class);
    }

    public function repairBills(): HasMany
    {
        return $this->hasMany(RepairBill::class);
    }
}
