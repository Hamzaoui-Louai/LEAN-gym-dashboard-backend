<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Models\MemberSubscription;
use App\Services\FinanceOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    private const STATUSES = ['active', 'frozen', 'expired'];

    private const PLANS = ['Monthly', 'Quarterly', 'Annual', 'Pay-as-you-go'];

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

        $data = $this->validateData($request);

        $member = Member::create([
            'gym_id' => $gym->id,
            'first_name' => $this->firstName($data['name']),
            'last_name' => $this->lastName($data['name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'joined_at' => $data['joined_at'],
            'status' => $data['status'],
        ]);

        $this->createSubscription($member, $data['membership']);

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

        $data = $this->validateData($request);

        $member->update([
            'first_name' => $this->firstName($data['name']),
            'last_name' => $this->lastName($data['name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'joined_at' => $data['joined_at'],
            'status' => $data['status'],
        ]);

        $this->updateSubscription($member, $data['membership']);

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

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'joined_at' => ['required', 'date'],
            'membership.plan' => ['required', Rule::in(self::PLANS)],
            'membership.price' => ['required', 'numeric', 'min:0'],
            'membership.started_at' => ['required', 'date'],
            'membership.ends_at' => ['nullable', 'date'],
        ]);
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

    private function updateSubscription(Member $member, array $membership): void
    {
        $subscription = $member->subscriptions()->orderByDesc('starts_at')->first();

        if ($subscription) {
            $subscription->update([
                'plan_name' => $membership['plan'],
                'price' => $membership['price'],
                'starts_at' => $membership['started_at'],
                'ends_at' => $membership['ends_at'] ?? null,
            ]);

            return;
        }

        $this->createSubscription($member, $membership);
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
