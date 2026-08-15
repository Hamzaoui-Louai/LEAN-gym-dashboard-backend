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
use Illuminate\Support\Facades\Cache;

class FinanceOverviewService
{
    private const PERIOD_MONTHS = [
        'this_week' => 1,
        'this_month' => 1,
        'last_3_months' => 3,
        'last_6_months' => 6,
        'this_year' => 12,
    ];

    private const CACHE_TTL = 300;

    /**
     * Build a monthly series ending at the current month, shaped like the
     * frontend mock in `frontend/src/lib/finances.js`. Cached per gym + month
     * count because the dashboard sections and the finances page all compute
     * the same series (each un-cached computation is 5 DB round-trips).
     */
    public function monthlyOverview(Gym $gym, ?string $period): array
    {
        $monthCount = self::PERIOD_MONTHS[$period ?? ''] ?? 12;

        return Cache::remember($this->key($gym, $monthCount), self::CACHE_TTL, function () use ($gym, $monthCount) {
            $months = collect(range($monthCount - 1, 0))
                ->map(fn (int $i) => now()->startOfMonth()->subMonths($i));

            $start = $months->first()->copy()->startOfMonth();
            $end = $months->last()->copy()->endOfMonth();

            $paymentsByMonth = $this->paymentsByMonth($gym, $start, $end);
            $salariesByMonth = $this->salariesByMonth($gym, $start, $end);
            $repairsByMonth = $this->repairsByMonth($gym, $start, $end);
            $purchasesByMonth = $this->purchasesByMonth($gym, $start, $end);
            $newSubscriptionsByMonth = $this->newSubscriptionsByMonth($gym, $start, $end);

            return $months->map(function (Carbon $month) use (
                $paymentsByMonth,
                $salariesByMonth,
                $repairsByMonth,
                $purchasesByMonth,
                $newSubscriptionsByMonth,
            ): array {
                $key = $month->format('Y-m');
                $paymentMonth = $paymentsByMonth->get($key);
                $revenue = $paymentMonth['revenue'] ?? collect();

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
                    'renewals' => $paymentMonth['renewals'] ?? 0,
                ];
            })->all();
        });
    }

    public function flush(Gym $gym): void
    {
        foreach (array_unique(array_values(self::PERIOD_MONTHS)) as $monthCount) {
            Cache::forget($this->key($gym, $monthCount));
        }
    }

    private function key(Gym $gym, int $monthCount): string
    {
        return "finance_overview:{$gym->id}:{$monthCount}";
    }

    /**
     * Payments of the gym's members in the window, grouped by month. Each group
     * carries the revenue rows (amount + plan) and the renewal count, so the
     * revenue/plan/renewal figures all come from a single joined query.
     */
    private function paymentsByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        $rows = Payment::query()
            ->join('member_subscriptions', 'payments.member_subscription_id', '=', 'member_subscriptions.id')
            ->join('members', 'member_subscriptions.member_id', '=', 'members.id')
            ->where('members.gym_id', $gym->id)
            ->where('payments.status', PaymentStatus::Paid)
            ->whereBetween('payments.paid_at', [$start, $end])
            ->get([
                'payments.id',
                'payments.amount',
                'payments.paid_at',
                'member_subscriptions.plan_name',
                'member_subscriptions.starts_at',
            ]);

        return $rows
            ->groupBy(fn (Payment $payment) => $payment->paid_at->format('Y-m'))
            ->map(fn (Collection $monthRows) => [
                'revenue' => $monthRows->map(fn (Payment $payment) => [
                    'amount' => (float) $payment->amount,
                    'plan' => $payment->plan_name,
                ]),
                'renewals' => $monthRows->filter(
                    fn (Payment $payment) => $payment->paid_at->startOfMonth()
                        ->gt(Carbon::parse($payment->starts_at)->startOfMonth())
                )->count(),
            ]);
    }

    private function planRevenue(Collection $rows, string $plan): int
    {
        return (int) round($rows->where('plan', $plan)->sum('amount'));
    }

    private function salariesByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        return Payslip::query()
            ->join('staff', 'payslips.staff_id', '=', 'staff.id')
            ->where('staff.gym_id', $gym->id)
            ->whereBetween('payslips.paid_at', [$start, $end])
            ->get(['payslips.id', 'payslips.amount', 'payslips.paid_at'])
            ->groupBy(fn (Payslip $payslip) => $payslip->paid_at->format('Y-m'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
    }

    private function repairsByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        return RepairBill::query()
            ->join('equipment', 'repair_bills.equipment_id', '=', 'equipment.id')
            ->where('equipment.gym_id', $gym->id)
            ->whereBetween('repair_bills.repair_date', [$start, $end])
            ->get(['repair_bills.id', 'repair_bills.amount', 'repair_bills.repair_date'])
            ->groupBy(fn (RepairBill $bill) => $bill->repair_date->format('Y-m'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
    }

    private function purchasesByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        return PurchaseBill::query()
            ->join('equipment', 'purchase_bills.equipment_id', '=', 'equipment.id')
            ->where('equipment.gym_id', $gym->id)
            ->whereBetween('purchase_bills.purchase_date', [$start, $end])
            ->get(['purchase_bills.id', 'purchase_bills.amount', 'purchase_bills.purchase_date'])
            ->groupBy(fn (PurchaseBill $bill) => $bill->purchase_date->format('Y-m'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
    }

    private function newSubscriptionsByMonth(Gym $gym, Carbon $start, Carbon $end): Collection
    {
        return MemberSubscription::query()
            ->join('members', 'member_subscriptions.member_id', '=', 'members.id')
            ->where('members.gym_id', $gym->id)
            ->whereBetween('member_subscriptions.starts_at', [$start, $end])
            ->get(['member_subscriptions.id', 'member_subscriptions.starts_at'])
            ->groupBy(fn (MemberSubscription $subscription) => $subscription->starts_at->format('Y-m'))
            ->map->count();
    }
}
