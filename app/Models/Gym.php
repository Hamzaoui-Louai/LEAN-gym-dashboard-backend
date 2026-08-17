<?php

namespace App\Models;

use App\Enums\GymStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'description', 'phone', 'email', 'address', 'logo', 'opening_time', 'closing_time', 'days_open', 'status'])]
class Gym extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => GymStatus::class,
            'days_open' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }
}
