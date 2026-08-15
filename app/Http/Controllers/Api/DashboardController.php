<?php

namespace App\Http\Controllers\Api;

use App\Enums\EquipmentStatus;
use App\Enums\MemberStatus;
use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payslip;
use App\Models\RepairBill;
use App\Services\FinanceOverviewService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    private const MEMBERSHIP_TYPES = ['Monthly', 'Quarterly', 'Annual', 'Pay-as-you-go'];

    public function __construct(private readonly FinanceOverviewService $finances) {}

    public function overview(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json($this->emptyOverview());
        }

        $expiringStart = today();
        $expiringEnd = today()->addDays(30)->endOfDay();

        $members = $this->latestSubscriptions($gym);
        $staffStatuses = $gym->staff()->get(['status'])->pluck('status');
        $equipmentStatuses = $gym->equipment()->get(['status'])->pluck('status');
        $todayCheckins = $this->todayCheckins($members->pluck('id'));

        return response()->json([
            'totalMembers' => $members->count(),
            'activeMembers' => $members->where('status', MemberStatus::Active)->count(),
            'expiringMemberships' => $members->filter(fn (Member $member) => $this->isExpiring($member, $expiringStart, $expiringEnd))->count(),
            'expiredMembers' => $members->where('status', MemberStatus::Expired)->count(),
            'totalStaff' => $staffStatuses->count(),
            'activeStaff' => $staffStatuses->filter(fn (StaffStatus $status) => $status === StaffStatus::Active)->count(),
            'totalEquipment' => $equipmentStatuses->count(),
            'availableEquipment' => $equipmentStatuses->filter(fn (EquipmentStatus $status) => $status === EquipmentStatus::Available)->count(),
            'maintenanceEquipment' => $equipmentStatuses->filter(fn (EquipmentStatus $status) => $status === EquipmentStatus::Maintenance)->count(),
            'brokenEquipment' => $equipmentStatuses->filter(fn (EquipmentStatus $status) => $status === EquipmentStatus::Broken)->count(),
            'todayCheckins' => $todayCheckins->count(),
            'insideNow' => $todayCheckins->whereNull('check_out')->count(),
        ]);
    }

    public function insights(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json($this->emptyInsights());
        }

        $months = $this->finances->monthlyOverview($gym, 'this_year');
        $latest = $months ? $months[count($months) - 1] : null;
        $previous = count($months) > 1 ? $months[count($months) - 2] : $latest;

        $monthRevenue = (float) ($latest['revenue'] ?? 0);
        $previousRevenue = (float) ($previous['revenue'] ?? 0);
        $avgMonthlyRevenue = $months
            ? (int) round(collect($months)->sum('revenue') / count($months))
            : 0;
        $revenueGrowth = $previousRevenue ? (($monthRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        $expiringStart = today();
        $expiringEnd = today()->addDays(30)->endOfDay();

        $members = $this->latestSubscriptions($gym);
        $memberIds = $members->pluck('id');

        $rosterByPlan = $members->groupBy(fn (Member $member) => $member->subscriptions->first()?->plan_name);
        $rosterDonut = collect(self::MEMBERSHIP_TYPES)
            ->map(fn (string $plan) => [
                'label' => $plan,
                'value' => $rosterByPlan->get($plan, collect())->count(),
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->values()
            ->all();

        $recentCheckins = $memberIds->isEmpty()
            ? collect()
            : Checkin::query()
                ->whereIn('member_id', $memberIds)
                ->with('member:id,first_name,last_name')
                ->orderByDesc('date')
                ->orderByDesc('check_in')
                ->limit(5)
                ->get()
                ->map(fn (Checkin $checkin) => [
                    'id' => $checkin->id,
                    'member_id' => $checkin->member_id,
                    'member' => $checkin->member?->name,
                    'date' => $checkin->date?->toDateString(),
                    'check_in' => $checkin->check_in,
                    'check_out' => $checkin->check_out,
                ])
                ->values();

        $todayCheckins = $this->todayCheckins($memberIds);

        return response()->json([
            'activeMembers' => $members->where('status', MemberStatus::Active)->count(),
            'expiringMemberships' => $members->filter(fn (Member $member) => $this->isExpiring($member, $expiringStart, $expiringEnd))->count(),
            'expiredMembers' => $members->where('status', MemberStatus::Expired)->count(),
            'todayCheckins' => $todayCheckins->count(),
            'insideNow' => $todayCheckins->whereNull('check_out')->count(),
            'monthRevenue' => $monthRevenue,
            'avgMonthlyRevenue' => $avgMonthlyRevenue,
            'revenueGrowth' => $revenueGrowth,
            'membershipRevenue' => $this->membershipRevenue($latest),
            'rosterDonut' => $rosterDonut,
            'recentCheckins' => $recentCheckins,
        ]);
    }

    public function operations(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json($this->emptyOperations());
        }

        $staff = $gym->staff()->get(['id', 'salary', 'status']);
        $staffIds = $staff->pluck('id');
        $activeStaff = $staff->where('status', StaffStatus::Active);

        $recentPayslips = $staffIds->isEmpty()
            ? collect()
            : Payslip::query()
                ->whereIn('staff_id', $staffIds)
                ->with('staff:id,first_name,last_name')
                ->orderByDesc('paid_at')
                ->limit(5)
                ->get()
                ->map(fn (Payslip $payslip) => [
                    'id' => $payslip->id,
                    'name' => $payslip->staff?->name,
                    'period' => $payslip->period,
                    'date' => $payslip->date,
                    'amount' => (float) $payslip->amount,
                    'status' => $payslip->status->value,
                ])
                ->values();

        $equipment = $gym->equipment()->get(['id', 'status']);
        $equipmentIds = $equipment->pluck('id');
        $equipmentStatuses = $equipment->pluck('status');

        $recentRepairs = $equipmentIds->isEmpty()
            ? collect()
            : RepairBill::query()
                ->whereIn('equipment_id', $equipmentIds)
                ->with('equipment:id,name')
                ->orderByDesc('repair_date')
                ->limit(5)
                ->get()
                ->map(fn (RepairBill $bill) => [
                    'id' => $bill->id,
                    'date' => $bill->repair_date?->toDateString(),
                    'equipment' => $bill->equipment?->name,
                    'issue' => $bill->description,
                    'cost' => (float) $bill->amount,
                    'status' => 'paid',
                ])
                ->values();

        return response()->json([
            'totalStaff' => $staff->count(),
            'activeStaff' => $activeStaff->count(),
            'monthlyPayroll' => (float) $activeStaff->sum('salary'),
            'recentPayslips' => $recentPayslips,
            'totalEquipment' => $equipmentStatuses->count(),
            'availableEquipment' => $equipmentStatuses->filter(fn (EquipmentStatus $status) => $status === EquipmentStatus::Available)->count(),
            'maintenanceEquipment' => $equipmentStatuses->filter(fn (EquipmentStatus $status) => $status === EquipmentStatus::Maintenance)->count(),
            'brokenEquipment' => $equipmentStatuses->filter(fn (EquipmentStatus $status) => $status === EquipmentStatus::Broken)->count(),
            'recentRepairs' => $recentRepairs,
        ]);
    }

    public function finances(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json($this->emptyFinances());
        }

        $months = $this->finances->monthlyOverview($gym, 'this_year');
        $latest = $months ? $months[count($months) - 1] : null;

        $monthRevenue = (float) ($latest['revenue'] ?? 0);
        $monthExpenses = (float) ($latest ? array_sum($latest['expenses']) : 0);
        $monthNet = $monthRevenue - $monthExpenses;
        $netMargin = $monthRevenue ? ($monthNet / $monthRevenue) * 100 : 0;

        $revExpData = collect($months)
            ->slice(-6)
            ->map(fn (array $month) => [
                'label' => $month['label'],
                'revenue' => (float) $month['revenue'],
                'expenses' => (float) array_sum($month['expenses']),
            ])
            ->values()
            ->all();

        return response()->json([
            'monthRevenue' => $monthRevenue,
            'monthExpenses' => $monthExpenses,
            'monthNet' => $monthNet,
            'netMargin' => $netMargin,
            'revExpData' => $revExpData,
        ]);
    }

    private function latestSubscriptions(Gym $gym): Collection
    {
        return $gym->members()
            ->with(['subscriptions' => fn ($query) => $query->latest('starts_at')->limit(1)])
            ->get(['id', 'status']);
    }

    private function isExpiring(Member $member, Carbon $start, Carbon $end): bool
    {
        $endsAt = $member->subscriptions->first()?->ends_at;

        return $endsAt !== null && $endsAt->between($start, $end);
    }

    private function todayCheckins(Collection $memberIds): Collection
    {
        if ($memberIds->isEmpty()) {
            return collect();
        }

        return Checkin::query()
            ->whereIn('member_id', $memberIds)
            ->whereDate('date', today())
            ->get(['id', 'check_out']);
    }

    private function emptyOverview(): array
    {
        return [
            'totalMembers' => 0,
            'activeMembers' => 0,
            'expiringMemberships' => 0,
            'expiredMembers' => 0,
            'totalStaff' => 0,
            'activeStaff' => 0,
            'totalEquipment' => 0,
            'availableEquipment' => 0,
            'maintenanceEquipment' => 0,
            'brokenEquipment' => 0,
            'todayCheckins' => 0,
            'insideNow' => 0,
        ];
    }

    private function emptyInsights(): array
    {
        return [
            'activeMembers' => 0,
            'expiringMemberships' => 0,
            'expiredMembers' => 0,
            'todayCheckins' => 0,
            'insideNow' => 0,
            'monthRevenue' => 0,
            'avgMonthlyRevenue' => 0,
            'revenueGrowth' => 0,
            'membershipRevenue' => $this->membershipRevenue(null),
            'rosterDonut' => [],
            'recentCheckins' => [],
        ];
    }

    private function emptyOperations(): array
    {
        return [
            'totalStaff' => 0,
            'activeStaff' => 0,
            'monthlyPayroll' => 0,
            'recentPayslips' => [],
            'totalEquipment' => 0,
            'availableEquipment' => 0,
            'maintenanceEquipment' => 0,
            'brokenEquipment' => 0,
            'recentRepairs' => [],
        ];
    }

    private function emptyFinances(): array
    {
        return [
            'monthRevenue' => 0,
            'monthExpenses' => 0,
            'monthNet' => 0,
            'netMargin' => 0,
            'revExpData' => [],
        ];
    }

    private function membershipRevenue(?array $latest): array
    {
        return collect(self::MEMBERSHIP_TYPES)
            ->map(fn (string $plan) => [
                'plan' => $plan,
                'value' => $latest['memberships'][$plan] ?? 0,
            ])
            ->all();
    }
}
