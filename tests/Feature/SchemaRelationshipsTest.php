<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Equipment;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\PurchaseBill;
use App\Models\RepairBill;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_schema_graph_resolves(): void
    {
        $user = User::create([
            'name' => 'Gym Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Basic,
            'valid_until' => now()->addMonth(),
        ]);

        $gym = Gym::create([
            'user_id' => $user->id,
            'name' => 'LEAN Downtown',
            'opening_time' => '06:00',
            'closing_time' => '22:00',
        ]);

        $staff = Staff::create([
            'gym_id' => $gym->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'position' => 'Coach',
            'salary' => 2500.00,
            'hire_date' => now()->subMonths(6),
        ]);

        $payslip = Payslip::create([
            'staff_id' => $staff->id,
            'month' => 7,
            'year' => now()->year,
            'amount' => 2500.00,
            'paid_at' => now(),
        ]);

        $member = Member::create([
            'gym_id' => $gym->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'joined_at' => now()->subMonths(3),
        ]);

        $memberSubscription = MemberSubscription::create([
            'member_id' => $member->id,
            'plan_name' => 'Monthly',
            'price' => 49.99,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        $payment = Payment::create([
            'member_subscription_id' => $memberSubscription->id,
            'amount' => 49.99,
            'paid_at' => now(),
        ]);

        $equipment = Equipment::create([
            'gym_id' => $gym->id,
            'name' => 'Smith Machine',
            'category' => 'Strength',
            'purchase_date' => now()->subYear(),
            'status' => EquipmentStatus::Available,
        ]);

        $purchaseBill = PurchaseBill::create([
            'equipment_id' => $equipment->id,
            'amount' => 1200.00,
            'purchase_date' => now()->subYear(),
            'vendor' => 'Iron Palace',
        ]);

        $repairBill = RepairBill::create([
            'equipment_id' => $equipment->id,
            'amount' => 85.50,
            'repair_date' => now()->subMonth(),
            'description' => 'Replaced cable',
        ]);

        $this->assertTrue($user->subscription->is($subscription));
        $this->assertTrue($user->gym->is($gym));
        $this->assertSame(SubscriptionPlan::Basic, $user->subscription->plan);

        $this->assertTrue($gym->user->is($user));
        $this->assertTrue($gym->staff->contains($staff));
        $this->assertTrue($gym->members->contains($member));
        $this->assertTrue($gym->equipment->contains($equipment));

        $this->assertTrue($staff->gym->is($gym));
        $this->assertTrue($staff->payslips->contains($payslip));
        $this->assertTrue($payslip->staff->is($staff));

        $this->assertTrue($member->gym->is($gym));
        $this->assertTrue($member->subscriptions->contains($memberSubscription));
        $this->assertTrue($memberSubscription->member->is($member));
        $this->assertTrue($memberSubscription->payments->contains($payment));
        $this->assertSame('49.99', $memberSubscription->price);
        $this->assertTrue($payment->memberSubscription->is($memberSubscription));

        $this->assertTrue($equipment->gym->is($gym));
        $this->assertSame(EquipmentStatus::Available, $equipment->status);
        $this->assertTrue($equipment->purchaseBills->contains($purchaseBill));
        $this->assertTrue($equipment->repairBills->contains($repairBill));
        $this->assertTrue($purchaseBill->equipment->is($equipment));
        $this->assertTrue($repairBill->equipment->is($equipment));
    }

    public function test_deleting_a_user_cascades_to_the_whole_graph(): void
    {
        $user = User::create([
            'name' => 'Gym Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Pro,
            'valid_until' => now()->addMonth(),
        ]);

        $gym = Gym::create([
            'user_id' => $user->id,
            'name' => 'LEAN Downtown',
        ]);

        $staff = Staff::create([
            'gym_id' => $gym->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'position' => 'Coach',
        ]);

        Payslip::create([
            'staff_id' => $staff->id,
            'month' => 7,
            'year' => now()->year,
            'amount' => 2500.00,
        ]);

        $member = Member::create([
            'gym_id' => $gym->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'joined_at' => now(),
        ]);

        $memberSubscription = MemberSubscription::create([
            'member_id' => $member->id,
            'plan_name' => 'Monthly',
            'price' => 49.99,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        Payment::create([
            'member_subscription_id' => $memberSubscription->id,
            'amount' => 49.99,
            'paid_at' => now(),
        ]);

        $equipment = Equipment::create([
            'gym_id' => $gym->id,
            'name' => 'Smith Machine',
            'status' => EquipmentStatus::Available,
        ]);

        PurchaseBill::create([
            'equipment_id' => $equipment->id,
            'amount' => 1200.00,
            'purchase_date' => now(),
        ]);

        RepairBill::create([
            'equipment_id' => $equipment->id,
            'amount' => 85.50,
            'repair_date' => now(),
        ]);

        $user->delete();

        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseCount('gyms', 0);
        $this->assertDatabaseCount('staff', 0);
        $this->assertDatabaseCount('payslips', 0);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('member_subscriptions', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('equipment', 0);
        $this->assertDatabaseCount('purchase_bills', 0);
        $this->assertDatabaseCount('repair_bills', 0);
    }

    public function test_user_cannot_have_more_than_one_subscription_or_gym(): void
    {
        $user = User::create([
            'name' => 'Gym Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        UserSubscription::create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::FreeTrial,
            'valid_until' => now()->addMonth(),
        ]);
        Gym::create([
            'user_id' => $user->id,
            'name' => 'LEAN Downtown',
        ]);

        $this->assertThrows(
            fn () => UserSubscription::create([
                'user_id' => $user->id,
                'plan' => SubscriptionPlan::Basic,
                'valid_until' => now()->addMonth(),
            ]),
            QueryException::class,
        );

        $this->assertThrows(
            fn () => Gym::create([
                'user_id' => $user->id,
                'name' => 'LEAN Uptown',
            ]),
            QueryException::class,
        );
    }

    public function test_plan_status_and_payslip_month_are_constrained(): void
    {
        $user = User::create([
            'name' => 'Gym Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        $gym = Gym::create([
            'user_id' => $user->id,
            'name' => 'LEAN Downtown',
        ]);

        $staff = Staff::create([
            'gym_id' => $gym->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'position' => 'Coach',
        ]);

        $this->assertThrows(
            fn () => DB::table('user_subscriptions')->insert([
                'user_id' => $user->id,
                'plan' => 'gold',
                'valid_until' => now(),
            ]),
            QueryException::class,
        );

        $this->assertThrows(
            fn () => DB::table('equipment')->insert([
                'gym_id' => $gym->id,
                'name' => 'Smith Machine',
                'status' => 'in_use',
            ]),
            QueryException::class,
        );

        if (DB::getDriverName() === 'pgsql') {
            $this->assertThrows(
                fn () => DB::table('payslips')->insert([
                    'staff_id' => $staff->id,
                    'month' => 13,
                    'year' => now()->year,
                    'amount' => 2500.00,
                ]),
                QueryException::class,
            );
        }
    }
}
