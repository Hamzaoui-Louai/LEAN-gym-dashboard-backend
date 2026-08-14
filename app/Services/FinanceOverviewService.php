<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\PurchaseBill;
use App\Models\RepairBill;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinanceOverviewService
{
    private const PERIOD_MONTHS = [
        'this_week' => 1,
        'this_month' => 1,
        'last_3_months' => 3,
        'last_6_months' => 6,
        'this_year' => 12,
    ];

    /**
     * Build a monthly series ending at the current month, shaped like the
     * frontend mock in `frontend/src/lib/finances.js`.
     */
    public function monthlyOverview(Gym $gym, ?string $period): array
    {
        $monthCount = self::PERIOD_MONTHS[$period ?? ''] ?? 12;

        $months = collect(range($monthCount - 1, 0))
            ->map(fn (int $i) => now()->startOfMonth()->subMonths($i));

        $start = $months->first()->copy()->startOfMonth();
        $end = $months->last()->copy()->endOfMonth();

        $revenueByMonth = $this->revenueByMonth($gym, $start, $end);
        $salariesByMonth = $this->salariesByMonth($gym, $start, $end);
        $repairsByMonth = $this->repairsByMonth($gym, $start, $end);
        $purchasesByMonth = $this->purchasesByMonth($gym, $start, $end);
        $newSubscriptionsByMonth = $this->newSubscriptionsByMonth($gym, $start, $end);
        $renewalsByMonth = $this->renewalsByMonth($gym, $start, $end);

        return $months->map(function (Carbon $month) use (
            $revenueByMonth,
            $salariesByMonth,
            $repairsByMonth,
            $purchasesByMonth,
            $newSubscriptionsByMonth,
            $renewalsByMonth,
        ): array {
            $key = $month->format('Y-m');

            $revenue = $revenueByMonth->get($key, collect());

            return [
                'key' => $key,
                'label' => $month->format('M'),
                'revenue' => $revenue->sum('amount'),
                'memberships' => [
                    'Monthly' => $this->planRevenue($revenue, 'Monthly'),
                    'Quarterly' => $this->planRevenue($revenue, 'Quarterly'),
                    'Annual' => $this->planRevenue($revenue, 'Annual'),
                    'Pay-as-you-go' => $this->planRevenue($revenue, 'Pay-as-you-go'),
                ],
                'expenses' => [
                    'staff_salaries' => $salariesByMonth->get($key, 0),
                    'equipment_repairs' => $repairsByMonth->get($key, 0),
                    'equipment_purchases' => $purchasesByMonth->get($key, 0),
                    'other' => 0,
                ],
                'new_subscriptions' => $newSubscriptionsByMonth->get($key, 0),
                'renewals' => $renewalsByMonth->get($key, 0),
            ];
        })->all();
    }

    private function revenueByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        $memberIds = $gym->members()->pluck('id');

        if ($memberIds->isEmpty()) {
            return collect();
        }

        return Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$start, $end])
            ->whereHas('memberSubscription', fn ($query) => $query->whereIn('member_id', $memberIds))
            ->with('memberSubscription')
            ->get(['id', 'amount', 'paid_at', 'member_subscription_id'])
            ->groupBy(fn (Payment $payment) => $payment->paid_at->format('Y-m'))
            ->map(fn (Collection $rows) => $rows->map(fn (Payment $payment) => [
                'amount' => (float) $payment->amount,
                'plan' => $payment->memberSubscription?->plan_name,
            ]));
    }

    private function planRevenue(Collection $rows, string $plan): int
    {
        return (int) round($rows->where('plan', $plan)->sum('amount'));
    }

    private function salariesByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        $staffIds = $gym->staff()->pluck('id');

        if ($staffIds->isEmpty()) {
            return collect();
        }

        return Payslip::query()
            ->whereIn('staff_id', $staffIds)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'amount', 'paid_at'])
            ->groupBy(fn (Payslip $payslip) => $payslip->paid_at->format('Y-m'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
    }

    private function repairsByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        $equipmentIds = $gym->equipment()->pluck('id');

        if ($equipmentIds->isEmpty()) {
            return collect();
        }

        return RepairBill::query()
            ->whereIn('equipment_id', $equipmentIds)
            ->whereBetween('repair_date', [$start, $end])
            ->get(['id', 'amount', 'repair_date'])
            ->groupBy(fn (RepairBill $bill) => $bill->repair_date->format('Y-m'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
    }

    private function purchasesByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        $equipmentIds = $gym->equipment()->pluck('id');

        if ($equipmentIds->isEmpty()) {
            return collect();
        }

        return PurchaseBill::query()
            ->whereIn('equipment_id', $equipmentIds)
            ->whereBetween('purchase_date', [$start, $end])
            ->get(['id', 'amount', 'purchase_date'])
            ->groupBy(fn (PurchaseBill $bill) => $bill->purchase_date->format('Y-m'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
    }

    private function newSubscriptionsByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        $memberIds = $gym->members()->pluck('id');

        if ($memberIds->isEmpty()) {
            return collect();
        }

        return MemberSubscription::query()
            ->whereIn('member_id', $memberIds)
            ->whereBetween('starts_at', [$start, $end])
            ->get(['id', 'starts_at'])
            ->groupBy(fn (MemberSubscription $subscription) => $subscription->starts_at->format('Y-m'))
            ->map->count();
    }

    private function renewalsByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        $memberIds = $gym->members()->pluck('id');

        if ($memberIds->isEmpty()) {
            return collect();
        }

        return Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$start, $end])
            ->whereHas('memberSubscription', fn ($query) => $query->whereIn('member_id', $memberIds))
            ->with('memberSubscription')
            ->get(['id', 'paid_at', 'member_subscription_id'])
            ->filter(fn (Payment $payment) => $payment->memberSubscription
                && $payment->paid_at->startOfMonth()->gt($payment->memberSubscription->starts_at->startOfMonth()))
            ->groupBy(fn (Payment $payment) => $payment->paid_at->format('Y-m'))
            ->map->count();
    }
}
