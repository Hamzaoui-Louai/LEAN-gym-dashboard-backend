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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    private function createOwner(): array
    {
        $user = User::create([
            'name' => 'Gym Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        UserSubscription::create([
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

        $token = $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ])->json('access_token');

        return [$user, $gym, $token];
    }

    private function createMember(Gym $gym, array $attributes = []): Member
    {
        return Member::create(array_merge([
            'gym_id' => $gym->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john@example.com',
            'phone' => '+15551234567',
            'joined_at' => now()->subMonths(3),
        ], $attributes));
    }

    private function createSubscription(Member $member, array $attributes = []): MemberSubscription
    {
        return MemberSubscription::create(array_merge([
            'member_id' => $member->id,
            'plan_name' => 'Monthly',
            'price' => 49.99,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ], $attributes));
    }

    public function test_dashboard_endpoints_require_authentication(): void
    {
        foreach (['/api/members', '/api/staff', '/api/equipment', '/api/equipment/repairs', '/api/checkins', '/api/finances/overview'] as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }

        $this->postJson('/api/checkins', ['member_id' => 1])->assertUnauthorized();

        $memberPayload = [
            'name' => 'Alice Wonder',
            'email' => 'alice@example.com',
            'status' => 'active',
            'joined_at' => '2026-08-01',
            'membership' => ['plan' => 'Monthly', 'price' => 45, 'started_at' => '2026-08-01'],
        ];

        $this->postJson('/api/members', $memberPayload)->assertUnauthorized();
        $this->putJson('/api/members/1', $memberPayload)->assertUnauthorized();
        $this->deleteJson('/api/members/1')->assertUnauthorized();
    }

    public function test_members_index_returns_shaped_members_for_the_owners_gym_only(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym, ['first_name' => 'John', 'last_name' => 'Smith']);
        $subscription = $this->createSubscription($member);

        Payment::create([
            'member_subscription_id' => $subscription->id,
            'amount' => 49.99,
            'paid_at' => now(),
        ]);

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $this->createMember($otherGym, ['first_name' => 'Stranger', 'last_name' => 'Danger']);

        $response = $this->getJson('/api/members', $this->authHeader($token));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'John Smith')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.membership.plan', 'Monthly')
            ->assertJsonPath('data.0.membership.price', 49.99)
            ->assertJsonPath('data.0.payments.0.amount', 49.99)
            ->assertJsonPath('data.0.payments.0.method', 'Card')
            ->assertJsonPath('data.0.payments.0.status', 'paid');
    }

    public function test_member_store_creates_member_and_subscription(): void
    {
        [, , $token] = $this->createOwner();

        $response = $this->postJson('/api/members', [
            'name' => 'Alice Wonder',
            'email' => 'alice@example.com',
            'phone' => '+15550001111',
            'status' => 'active',
            'joined_at' => '2026-08-01',
            'membership' => [
                'plan' => 'Monthly',
                'price' => 45,
                'started_at' => '2026-08-01',
                'ends_at' => '2026-08-31',
            ],
        ], $this->authHeader($token));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Alice Wonder')
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.membership.plan', 'Monthly')
            ->assertJsonPath('data.membership.price', 45)
            ->assertJsonPath('data.membership.started_at', '2026-08-01')
            ->assertJsonPath('data.membership.ends_at', '2026-08-31')
            ->assertJsonPath('data.payments', []);

        $memberId = $response->json('data.id');

        $this->assertDatabaseHas('members', ['id' => $memberId, 'email' => 'alice@example.com', 'status' => 'active']);
        $this->assertDatabaseHas('member_subscriptions', ['member_id' => $memberId, 'plan_name' => 'Monthly']);
    }

    public function test_member_store_rejects_invalid_status_and_plan(): void
    {
        [, , $token] = $this->createOwner();

        $this->postJson('/api/members', [
            'name' => 'Bad Member',
            'email' => 'bad@example.com',
            'status' => 'gold',
            'joined_at' => '2026-08-01',
            'membership' => [
                'plan' => 'Weekly',
                'price' => 45,
                'started_at' => '2026-08-01',
            ],
        ], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'membership.plan']);
    }

    public function test_member_update_edits_member_and_membership(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym);
        $this->createSubscription($member);

        $this->putJson("/api/members/{$member->id}", [
            'name' => 'John Smith Jr',
            'email' => 'johnjr@example.com',
            'phone' => '+15551230000',
            'status' => 'frozen',
            'joined_at' => '2026-07-01',
            'membership' => [
                'plan' => 'Quarterly',
                'price' => 120,
                'started_at' => '2026-07-01',
                'ends_at' => '2026-09-30',
            ],
        ], $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'John Smith Jr')
            ->assertJsonPath('data.email', 'johnjr@example.com')
            ->assertJsonPath('data.status', 'frozen')
            ->assertJsonPath('data.joined_at', '2026-07-01')
            ->assertJsonPath('data.membership.plan', 'Quarterly')
            ->assertJsonPath('data.membership.price', 120)
            ->assertJsonPath('data.membership.started_at', '2026-07-01')
            ->assertJsonPath('data.membership.ends_at', '2026-09-30');

        $this->assertDatabaseHas('members', ['id' => $member->id, 'first_name' => 'John', 'last_name' => 'Smith Jr', 'status' => 'frozen']);
        $this->assertDatabaseHas('member_subscriptions', ['member_id' => $member->id, 'plan_name' => 'Quarterly']);
    }

    public function test_member_update_and_delete_reject_foreign_members(): void
    {
        [, , $token] = $this->createOwner();

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $foreignMember = $this->createMember($otherGym);
        $this->createSubscription($foreignMember);

        $this->putJson("/api/members/{$foreignMember->id}", [
            'name' => 'Hijack',
            'email' => 'hijack@example.com',
            'status' => 'active',
            'joined_at' => '2026-08-01',
            'membership' => ['plan' => 'Monthly', 'price' => 45, 'started_at' => '2026-08-01'],
        ], $this->authHeader($token))
            ->assertNotFound()
            ->assertJsonPath('message', 'Member not found.');

        $this->deleteJson("/api/members/{$foreignMember->id}", [], $this->authHeader($token))
            ->assertNotFound()
            ->assertJsonPath('message', 'Member not found.');

        $this->assertDatabaseHas('members', ['id' => $foreignMember->id]);
    }

    public function test_member_delete_cascades_to_subscriptions_and_checkins(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym);
        $subscription = $this->createSubscription($member);
        $member->checkins()->create([
            'date' => today(),
            'check_in' => '07:00',
        ]);

        $this->deleteJson("/api/members/{$member->id}", [], $this->authHeader($token))
            ->assertNoContent();

        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseMissing('member_subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseCount('checkins', 0);
    }

    public function test_staff_index_returns_shaped_staff(): void
    {
        [, $gym, $token] = $this->createOwner();

        $staff = Staff::create([
            'gym_id' => $gym->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+15559876543',
            'position' => 'Coach',
            'salary' => 2500.00,
            'hire_date' => now()->subMonths(6),
        ]);

        Payslip::create([
            'staff_id' => $staff->id,
            'month' => now()->month,
            'year' => now()->year,
            'amount' => 2500.00,
            'paid_at' => now(),
        ]);

        $this->getJson('/api/staff', $this->authHeader($token))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jane Doe')
            ->assertJsonPath('data.0.role', 'Coach')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.salary', 2500)
            ->assertJsonPath('data.0.payslips.0.period', now()->format('F Y'))
            ->assertJsonPath('data.0.payslips.0.status', 'paid');
    }

    public function test_equipment_index_maps_status_to_state_and_price(): void
    {
        [, $gym, $token] = $this->createOwner();

        $treadmill = Equipment::create([
            'gym_id' => $gym->id,
            'name' => 'Treadmill X1',
            'category' => 'Cardio',
            'purchase_date' => now()->subYear(),
            'status' => EquipmentStatus::Available,
        ]);

        PurchaseBill::create([
            'equipment_id' => $treadmill->id,
            'amount' => 1200.00,
            'purchase_date' => now()->subYear(),
            'vendor' => 'Iron Palace',
        ]);

        Equipment::create([
            'gym_id' => $gym->id,
            'name' => 'Smith Machine',
            'category' => 'Strength',
            'status' => EquipmentStatus::Maintenance,
        ]);

        Equipment::create([
            'gym_id' => $gym->id,
            'name' => 'Leg Press',
            'category' => 'Strength',
            'status' => EquipmentStatus::Broken,
        ]);

        $this->getJson('/api/equipment', $this->authHeader($token))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.state', 'operational')
            ->assertJsonPath('data.0.price', 1200)
            ->assertJsonPath('data.1.state', 'under_repair')
            ->assertJsonPath('data.2.state', 'out_of_order');
    }

    public function test_equipment_repairs_returns_paid_repairs_for_the_owners_equipment(): void
    {
        [, $gym, $token] = $this->createOwner();

        $equipment = Equipment::create([
            'gym_id' => $gym->id,
            'name' => 'Treadmill X1',
            'status' => EquipmentStatus::Available,
        ]);

        RepairBill::create([
            'equipment_id' => $equipment->id,
            'amount' => 85.50,
            'repair_date' => now()->subMonth(),
            'description' => 'Replaced cable',
        ]);

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $otherEquipment = Equipment::create([
            'gym_id' => $otherGym->id,
            'name' => 'Rower',
            'status' => EquipmentStatus::Available,
        ]);
        RepairBill::create([
            'equipment_id' => $otherEquipment->id,
            'amount' => 999.00,
            'repair_date' => now()->subMonth(),
            'description' => 'Other gym repair',
        ]);

        $this->getJson('/api/equipment/repairs', $this->authHeader($token))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.equipment', 'Treadmill X1')
            ->assertJsonPath('data.0.issue', 'Replaced cable')
            ->assertJsonPath('data.0.cost', 85.5)
            ->assertJsonPath('data.0.status', 'paid');
    }

    public function test_checkins_index_returns_shaped_checkins(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym);
        $this->createSubscription($member);

        $member->checkins()->create([
            'date' => today(),
            'check_in' => '07:30',
            'check_out' => '09:15',
        ]);

        $this->getJson('/api/checkins', $this->authHeader($token))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.member_id', $member->id)
            ->assertJsonPath('data.0.date', today()->toDateString())
            ->assertJsonPath('data.0.check_in', '07:30')
            ->assertJsonPath('data.0.check_out', '09:15');
    }

    public function test_checkin_store_requires_an_active_subscription(): void
    {
        [, $gym, $token] = $this->createOwner();

        $withoutSubscription = $this->createMember($gym, ['first_name' => 'No', 'last_name' => 'Subscription']);

        $this->postJson('/api/checkins', ['member_id' => $withoutSubscription->id], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Member does not have an active subscription.');

        $expired = $this->createMember($gym, ['first_name' => 'Expired', 'last_name' => 'Member']);
        $this->createSubscription($expired, ['ends_at' => now()->subDay()]);

        $this->postJson('/api/checkins', ['member_id' => $expired->id], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Member does not have an active subscription.');
    }

    public function test_checkin_store_accepts_an_active_subscription_and_rejects_an_open_checkin(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym);
        $this->createSubscription($member, ['ends_at' => null]);

        $this->postJson('/api/checkins', ['member_id' => $member->id], $this->authHeader($token))
            ->assertCreated()
            ->assertJsonPath('data.member_id', $member->id)
            ->assertJsonPath('data.check_out', null);

        $this->postJson('/api/checkins', ['member_id' => $member->id], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Member already has an open check-in.');
    }

    public function test_checkin_store_rejects_a_member_from_another_gym(): void
    {
        [, , $token] = $this->createOwner();

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $otherMember = $this->createMember($otherGym);
        $this->createSubscription($otherMember);

        $this->postJson('/api/checkins', ['member_id' => $otherMember->id], $this->authHeader($token))
            ->assertForbidden()
            ->assertJsonPath('message', 'Member not found.');
    }

    public function test_checkout_closes_an_open_checkin_and_rejects_foreign_or_closed_ones(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym);
        $this->createSubscription($member);

        $open = $member->checkins()->create([
            'date' => today(),
            'check_in' => '07:30',
        ]);

        $this->postJson("/api/checkins/{$open->id}/check-out", [], $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('data.check_out', now()->format('H:i'));

        $this->postJson("/api/checkins/{$open->id}/check-out", [], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Check-in is already checked out.');

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $foreignMember = $this->createMember($otherGym);
        $foreignCheckin = $foreignMember->checkins()->create([
            'date' => today(),
            'check_in' => '08:00',
        ]);

        $this->postJson("/api/checkins/{$foreignCheckin->id}/check-out", [], $this->authHeader($token))
            ->assertNotFound()
            ->assertJsonPath('message', 'Check-in not found.');
    }

    public function test_finances_overview_returns_monthly_series_ending_current_month_for_own_gym(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym);
        $subscription = $this->createSubscription($member, ['starts_at' => now()]);

        Payment::create([
            'member_subscription_id' => $subscription->id,
            'amount' => 49.99,
            'paid_at' => now(),
            'method' => 'Cash',
            'status' => 'paid',
        ]);

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $otherMember = $this->createMember($otherGym);
        $otherSubscription = $this->createSubscription($otherMember);
        Payment::create([
            'member_subscription_id' => $otherSubscription->id,
            'amount' => 999.00,
            'paid_at' => now(),
        ]);

        $response = $this->getJson('/api/finances/overview', $this->authHeader($token));

        $response->assertOk()
            ->assertJsonCount(12);

        $data = $response->json();
        $last = $data[count($data) - 1];

        $this->assertSame(now()->format('Y-m'), $last['key']);
        $this->assertSame(now()->format('M'), $last['label']);
        $this->assertSame(49.99, $last['revenue']);
        $this->assertSame(50, $last['memberships']['Monthly']);
        $this->assertSame(0, $last['expenses']['staff_salaries']);
        $this->assertSame(0, $last['expenses']['other']);
        $this->assertSame(1, $last['new_subscriptions']);
        $this->assertSame(0, $last['renewals']);

        $this->getJson('/api/finances/overview?period=this_month', $this->authHeader($token))
            ->assertOk()
            ->assertJsonCount(1);
    }
}
