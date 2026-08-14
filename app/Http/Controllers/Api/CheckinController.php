<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckinResource;
use App\Models\Checkin;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckinController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $gym = $request->user()->gym;

        $checkins = $gym
            ? Checkin::query()
                ->whereHas('member', fn ($query) => $query->where('gym_id', $gym->id))
                ->orderByDesc('date')
                ->orderByDesc('check_in')
                ->limit(300)
                ->get()
            : collect();

        return CheckinResource::collection($checkins);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer'],
        ]);

        $gym = $request->user()->gym;
        $member = Member::find($data['member_id']);

        if (! $gym || ! $member || $member->gym_id !== $gym->id) {
            return response()->json(['message' => 'Member not found.'], 403);
        }

        if (! $this->hasActiveSubscription($member)) {
            return response()->json(['message' => 'Member does not have an active subscription.'], 422);
        }

        $open = $member->checkins()
            ->whereDate('date', today())
            ->whereNull('check_out')
            ->exists();

        if ($open) {
            return response()->json(['message' => 'Member already has an open check-in.'], 422);
        }

        $checkin = Checkin::create([
            'member_id' => $member->id,
            'date' => today()->toDateString(),
            'check_in' => now()->format('H:i'),
        ]);

        return CheckinResource::make($checkin)->response()->setStatusCode(201);
    }

    public function checkOut(Request $request, int $id): JsonResponse
    {
        $gym = $request->user()->gym;

        $checkin = $gym
            ? Checkin::whereHas('member', fn ($query) => $query->where('gym_id', $gym->id))->find($id)
            : null;

        if (! $checkin) {
            return response()->json(['message' => 'Check-in not found.'], 404);
        }

        if ($checkin->check_out !== null) {
            return response()->json(['message' => 'Check-in is already checked out.'], 422);
        }

        $checkin->update(['check_out' => now()->format('H:i')]);

        return CheckinResource::make($checkin->fresh())->response();
    }

    private function hasActiveSubscription(Member $member): bool
    {
        $subscription = $member->subscriptions()->orderByDesc('starts_at')->first();

        if (! $subscription || $subscription->starts_at->startOfDay()->gt(today())) {
            return false;
        }

        return $subscription->ends_at === null || $subscription->ends_at->endOfDay()->gte(today());
    }
}
