<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GymResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GymController extends Controller
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private const STATUSES = ['active', 'inactive'];

    public function show(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json(['message' => 'No gym found for this account.'], 404);
        }

        return GymResource::make($gym)->response();
    }

    public function update(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json(['message' => 'No gym found for this account.'], 404);
        }

        $data = $this->validateData($request);

        $gym->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'opening_time' => $data['opens_at'] ?? null,
            'closing_time' => $data['closes_at'] ?? null,
            'days_open' => $data['days_open'] ?? [],
            'status' => $data['status'],
        ]);

        return GymResource::make($gym->fresh())->response();
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'days_open' => ['nullable', 'array'],
            'days_open.*' => [Rule::in(self::DAYS)],
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);
    }
}
