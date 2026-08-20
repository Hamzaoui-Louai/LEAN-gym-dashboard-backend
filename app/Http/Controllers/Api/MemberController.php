<?php

namespace App\Http\Controllers\Api;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Models\MemberSubscription;
use App\Services\FinanceOverviewService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    private const PLANS = ['Monthly', 'Quarterly', 'Annual', 'Pay-as-you-go'];

    private const PLAN_MONTHS = [
        'Monthly' => 1,
        'Quarterly' => 3,
        'Annual' => 12,
        'Pay-as-you-go' => 0,
    ];

    public function __construct(private readonly FinanceOverviewService $finances) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $members = $request->user()->gym?->members()
            ->with(['subscriptions.payments'])
            ->latest('created_at')
            ->get() ?? collect();

        return MemberResource::collection($members);
    }

    public function store(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json(['message' => 'No gym found for this account.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'membership.plan' => ['required', Rule::in(self::PLANS)],
            'membership.price' => ['required', 'numeric', 'min:0'],
        ]);

        $now = Carbon::today();
        $plan = $data['membership']['plan'];
        $months = self::PLAN_MONTHS[$plan];

        $member = Member::create([
            'gym_id' => $gym->id,
            'first_name' => $this->firstName($data['name']),
            'last_name' => $this->lastName($data['name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'joined_at' => $now,
            'status' => MemberStatus::Active,
        ]);

        $this->createSubscription($member, [
            'plan' => $plan,
            'price' => $data['membership']['price'],
            'started_at' => $now,
            'ends_at' => $months > 0 ? $now->copy()->addMonths($months)->endOfDay() : null,
        ]);

        $this->finances->flush($gym);

        return MemberResource::make($member->load(['subscriptions.payments']))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $member = $this->memberFor($request, $id);

        if (! $member) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $member->update([
            'first_name' => $this->firstName($data['name']),
            'last_name' => $this->lastName($data['name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);

        return MemberResource::make($member->load(['subscriptions.payments']))->response();
    }

    public function subscribe(Request $request, int $id): JsonResponse
    {
        $member = $this->memberFor($request, $id);

        if (! $member) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        $data = $request->validate([
            'plan' => ['required', Rule::in(self::PLANS)],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $latestSub = $member->subscriptions()->orderByDesc('starts_at')->first();
        $hasActive = $latestSub && $this->isActive($latestSub);

        if ($hasActive) {
            return response()->json(['message' => 'Member already has an active subscription.'], 422);
        }

        $now = Carbon::today();
        $months = self::PLAN_MONTHS[$data['plan']];
        $endsAt = $months > 0 ? $now->copy()->addMonths($months)->endOfDay() : null;

        $subscription = MemberSubscription::create([
            'member_id' => $member->id,
            'plan_name' => $data['plan'],
            'price' => $data['price'],
            'starts_at' => $now,
            'ends_at' => $endsAt,
        ]);

        $member->update(['status' => MemberStatus::Active]);

        if ($gym = $request->user()->gym) {
            $this->finances->flush($gym);
        }

        return MemberResource::make($member->load(['subscriptions.payments']))
            ->response()
            ->setStatusCode(201);
    }

    public function freeze(Request $request, int $id): JsonResponse
    {
        $member = $this->memberFor($request, $id);

        if (! $member) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        if ($member->status !== MemberStatus::Active) {
            return response()->json(['message' => 'Only active members can be frozen.'], 422);
        }

        $subscription = $member->subscriptions()->orderByDesc('starts_at')->first();

        if (! $subscription || ! $this->isActive($subscription)) {
            return response()->json(['message' => 'Member has no active subscription.'], 422);
        }

        $now = Carbon::now();

        if ($now->lte(Carbon::parse($subscription->starts_at))) {
            return response()->json(['message' => 'Cannot freeze before the subscription start date.'], 422);
        }

        $subscription->update([
            'original_ends_at' => $subscription->ends_at,
            'last_freezed_at' => $now,
            'last_unfreezed_at' => null,
            'ends_at' => null,
        ]);

        $member->update(['status' => MemberStatus::Frozen]);

        if ($gym = $request->user()->gym) {
            $this->finances->flush($gym);
        }

        return MemberResource::make($member->load(['subscriptions.payments']))->response();
    }

    public function unfreeze(Request $request, int $id): JsonResponse
    {
        $member = $this->memberFor($request, $id);

        if (! $member) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        if ($member->status !== MemberStatus::Frozen) {
            return response()->json(['message' => 'Only frozen members can be unfrozen.'], 422);
        }

        $subscription = $member->subscriptions()->orderByDesc('starts_at')->first();

        if (! $subscription || ! $subscription->last_freezed_at) {
            return response()->json(['message' => 'No active freeze found on this member.'], 422);
        }

        $now = Carbon::now();
        $freezeDuration = Carbon::parse($subscription->last_freezed_at)->diff($now);

        $originalEnds = $subscription->original_ends_at
            ? Carbon::parse($subscription->original_ends_at)->add($freezeDuration)
            : null;

        $subscription->update([
            'last_unfreezed_at' => $now,
            'ends_at' => $originalEnds,
            'original_ends_at' => null,
        ]);

        $member->update(['status' => MemberStatus::Active]);

        if ($gym = $request->user()->gym) {
            $this->finances->flush($gym);
        }

        return MemberResource::make($member->load(['subscriptions.payments']))->response();
    }

    public function destroy(Request $request, int $id): Response
    {
        $member = $this->memberFor($request, $id);

        if (! $member) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        $gym = $request->user()->gym;
        $member->delete();

        if ($gym) {
            $this->finances->flush($gym);
        }

        return response()->noContent();
    }

    private function memberFor(Request $request, int $id): ?Member
    {
        return $request->user()->gym?->members()->find($id);
    }

    private function createSubscription(Member $member, array $membership): MemberSubscription
    {
        return MemberSubscription::create([
            'member_id' => $member->id,
            'plan_name' => $membership['plan'],
            'price' => $membership['price'],
            'starts_at' => $membership['started_at'],
            'ends_at' => $membership['ends_at'] ?? null,
        ]);
    }

    private function isActive(MemberSubscription $subscription): bool
    {
        $now = Carbon::today();

        return $subscription->starts_at->lte($now)
            && ($subscription->ends_at === null || $subscription->ends_at->gte($now));
    }

    private function firstName(string $name): string
    {
        return trim(explode(' ', $name, 2)[0]);
    }

    private function lastName(string $name): string
    {
        $parts = explode(' ', $name, 2);

        return trim($parts[1] ?? '');
    }
}
