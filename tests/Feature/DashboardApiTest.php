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
use Carbon\Carbon;
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
        foreach (['/api/members', '/api/staff', '/api/equipment', '/api/equipment/repairs', '/api/checkins', '/api/finances/overview', '/api/gym', '/api/dashboard/overview', '/api/dashboard/insights', '/api/dashboard/operations', '/api/dashboard/finances'] as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }

        $this->postJson('/api/checkins', ['member_id' => 1])->assertUnauthorized();
        $this->putJson('/api/gym', ['name' => 'Renamed'])->assertUnauthorized();

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

        $staffPayload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role' => 'Coach',
            'status' => 'active',
            'salary' => 2500,
            'joined_at' => '2026-08-01',
        ];

        $this->postJson('/api/staff', $staffPayload)->assertUnauthorized();
        $this->putJson('/api/staff/1', $staffPayload)->assertUnauthorized();
        $this->postJson('/api/staff/1/payslips', ['date' => '2026-08-01', 'amount' => 2500, 'status' => 'paid'])->assertUnauthorized();

        $equipmentPayload = [
            'name' => 'Treadmill X1',
            'category' => 'Cardio',
            'state' => 'operational',
            'purchased_at' => '2026-08-01',
            'price' => 1200,
        ];

        $this->postJson('/api/equipment', $equipmentPayload)->assertUnauthorized();
        $this->putJson('/api/equipment/1', $equipmentPayload)->assertUnauthorized();
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
            'membership' => [
                'plan' => 'Monthly',
                'price' => 45,
            ],
        ], $this->authHeader($token));

        $today = now()->toDateString();

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Alice Wonder')
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.joined_at', $today)
            ->assertJsonPath('data.membership.plan', 'Monthly')
            ->assertJsonPath('data.membership.price', 45)
            ->assertJsonPath('data.membership.started_at', $today)
            ->assertJsonPath('data.payments', []);

        $memberId = $response->json('data.id');

        $this->assertDatabaseHas('members', ['id' => $memberId, 'email' => 'alice@example.com', 'status' => 'active']);
        $this->assertDatabaseHas('member_subscriptions', ['member_id' => $memberId, 'plan_name' => 'Monthly']);
    }

    public function test_member_store_rejects_invalid_plan(): void
    {
        [, , $token] = $this->createOwner();

        $this->postJson('/api/members', [
            'name' => 'Bad Member',
            'email' => 'bad@example.com',
            'membership' => [
                'plan' => 'Weekly',
                'price' => 45,
            ],
        ], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['membership.plan']);
    }

    public function test_member_update_edits_profile_only(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym);
        $this->createSubscription($member);

        $this->putJson("/api/members/{$member->id}", [
            'name' => 'John Smith Jr',
            'email' => 'johnjr@example.com',
            'phone' => '+15551230000',
        ], $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'John Smith Jr')
            ->assertJsonPath('data.email', 'johnjr@example.com')
            ->assertJsonPath('data.phone', '+15551230000');

        $this->assertDatabaseHas('members', ['id' => $member->id, 'first_name' => 'John', 'last_name' => 'Smith Jr']);
    }

    public function test_member_subscribe_creates_subscription_and_sets_active(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym, ['status' => 'expired']);
        MemberSubscription::create([
            'member_id' => $member->id,
            'plan_name' => 'Monthly',
            'price' => 45,
            'starts_at' => now()->subMonthsNoOverflow(2),
            'ends_at' => now()->subMonthNoOverflow(),
        ]);

        $this->postJson("/api/members/{$member->id}/subscribe", [
            'plan' => 'Quarterly',
            'price' => 120,
        ], $this->authHeader($token))
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.membership.plan', 'Quarterly');

        $this->assertDatabaseHas('members', ['id' => $member->id, 'status' => 'active']);
        $this->assertDatabaseHas('member_subscriptions', [
            'member_id' => $member->id,
            'plan_name' => 'Quarterly',
            'price' => 120,
        ]);
    }

    public function test_member_subscribe_rejects_already_active(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym, ['status' => 'active']);
        $this->createSubscription($member);

        $this->postJson("/api/members/{$member->id}/subscribe", [
            'plan' => 'Monthly',
            'price' => 45,
        ], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Member already has an active subscription.');
    }

    public function test_member_freeze_sets_frozen_and_nulls_ends_at(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym, ['status' => 'active']);
        $sub = $this->createSubscription($member, [
            'starts_at' => now()->subMonthNoOverflow(),
            'ends_at' => now()->addMonthNoOverflow(),
        ]);

        $this->postJson("/api/members/{$member->id}/freeze", [], $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'frozen');

        $this->assertDatabaseHas('members', ['id' => $member->id, 'status' => 'frozen']);
        $sub->refresh();
        $this->assertNotNull($sub->last_freezed_at);
        $this->assertNotNull($sub->original_ends_at);
        $this->assertNull($sub->ends_at);
    }

    public function test_member_freeze_rejects_non_active(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym, ['status' => 'expired']);

        $this->postJson("/api/members/{$member->id}/freeze", [], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only active members can be frozen.');
    }

    public function test_member_unfreeze_restores_active_and_extends_ends_at(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym, ['status' => 'frozen']);
        $originalEnd = now()->addMonthNoOverflow();
        $sub = MemberSubscription::create([
            'member_id' => $member->id,
            'plan_name' => 'Monthly',
            'price' => 45,
            'starts_at' => now()->subMonthNoOverflow(),
            'ends_at' => null,
            'last_freezed_at' => now()->subDays(10),
            'original_ends_at' => $originalEnd,
        ]);

        $this->postJson("/api/members/{$member->id}/unfreeze", [], $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('members', ['id' => $member->id, 'status' => 'active']);
        $sub->refresh();
        $this->assertNotNull($sub->last_unfreezed_at);
        $this->assertNull($sub->original_ends_at);
        $this->assertNotNull($sub->ends_at);
        $this->assertTrue(Carbon::parse($sub->ends_at)->gt($originalEnd));
    }

    public function test_member_unfreeze_rejects_non_frozen(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym, ['status' => 'active']);

        $this->postJson("/api/members/{$member->id}/unfreeze", [], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only frozen members can be unfrozen.');
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

    public function test_staff_store_creates_staff_member(): void
    {
        [, , $token] = $this->createOwner();

        $response = $this->postJson('/api/staff', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+15559876543',
            'role' => 'Manager',
            'status' => 'active',
            'salary' => 2800,
            'joined_at' => '2026-08-01',
        ], $this->authHeader($token));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.role', 'Manager')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.salary', 2800)
            ->assertJsonPath('data.joined_at', '2026-08-01')
            ->assertJsonPath('data.payslips', []);

        $this->assertDatabaseHas('staff', [
            'id' => $response->json('data.id'),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'position' => 'Manager',
        ]);
    }

    public function test_staff_store_rejects_invalid_status(): void
    {
        [, , $token] = $this->createOwner();

        $this->postJson('/api/staff', [
            'name' => 'Bad Staff',
            'email' => 'bad@example.com',
            'role' => 'Coach',
            'status' => 'fired',
            'salary' => 2500,
            'joined_at' => '2026-08-01',
        ], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_staff_update_edits_staff_member(): void
    {
        [, $gym, $token] = $this->createOwner();

        $staff = Staff::create([
            'gym_id' => $gym->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'position' => 'Coach',
            'salary' => 2500,
            'hire_date' => now()->subMonths(6),
        ]);

        $this->putJson("/api/staff/{$staff->id}", [
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'role' => 'Manager',
            'status' => 'on_leave',
            'salary' => 2900,
            'joined_at' => '2026-07-01',
        ], $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Jane Smith')
            ->assertJsonPath('data.email', 'jane.smith@example.com')
            ->assertJsonPath('data.role', 'Manager')
            ->assertJsonPath('data.status', 'on_leave')
            ->assertJsonPath('data.salary', 2900);

        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'position' => 'Manager',
            'status' => 'on_leave',
        ]);
    }

    public function test_staff_update_rejects_foreign_staff(): void
    {
        [, , $token] = $this->createOwner();

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $foreignStaff = Staff::create([
            'gym_id' => $otherGym->id,
            'first_name' => 'Stranger',
            'last_name' => 'Danger',
            'position' => 'Coach',
            'salary' => 1000,
        ]);

        $this->putJson("/api/staff/{$foreignStaff->id}", [
            'name' => 'Hijack',
            'email' => 'hijack@example.com',
            'role' => 'Manager',
            'status' => 'active',
            'salary' => 2900,
            'joined_at' => '2026-08-01',
        ], $this->authHeader($token))
            ->assertNotFound()
            ->assertJsonPath('message', 'Staff member not found.');
    }

    public function test_staff_payslip_store_creates_payslip(): void
    {
        [, $gym, $token] = $this->createOwner();

        $staff = Staff::create([
            'gym_id' => $gym->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'position' => 'Coach',
            'salary' => 2500,
        ]);

        $this->postJson("/api/staff/{$staff->id}/payslips", [
            'date' => '2026-07-01',
            'amount' => 2500,
            'method' => 'Transfer',
            'status' => 'pending',
        ], $this->authHeader($token))
            ->assertCreated()
            ->assertJsonPath('data.period', 'July 2026')
            ->assertJsonPath('data.amount', 2500)
            ->assertJsonPath('data.method', 'Transfer')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('payslips', [
            'staff_id' => $staff->id,
            'month' => 7,
            'year' => 2026,
            'amount' => 2500,
            'status' => 'pending',
        ]);
    }

    public function test_staff_payslip_store_rejects_foreign_staff(): void
    {
        [, , $token] = $this->createOwner();

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $foreignStaff = Staff::create([
            'gym_id' => $otherGym->id,
            'first_name' => 'Stranger',
            'last_name' => 'Danger',
            'position' => 'Coach',
            'salary' => 1000,
        ]);

        $this->postJson("/api/staff/{$foreignStaff->id}/payslips", [
            'date' => '2026-07-01',
            'amount' => 1000,
            'status' => 'paid',
        ], $this->authHeader($token))
            ->assertNotFound()
            ->assertJsonPath('message', 'Staff member not found.');
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

    public function test_equipment_store_creates_equipment_and_purchase_bill(): void
    {
        [, , $token] = $this->createOwner();

        $response = $this->postJson('/api/equipment', [
            'name' => 'Treadmill X2',
            'category' => 'Cardio',
            'state' => 'operational',
            'purchased_at' => '2026-08-01',
            'price' => 1200,
        ], $this->authHeader($token));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Treadmill X2')
            ->assertJsonPath('data.category', 'Cardio')
            ->assertJsonPath('data.state', 'operational')
            ->assertJsonPath('data.purchased_at', '2026-08-01')
            ->assertJsonPath('data.price', 1200);

        $equipmentId = $response->json('data.id');

        $this->assertDatabaseHas('equipment', [
            'id' => $equipmentId,
            'name' => 'Treadmill X2',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('purchase_bills', [
            'equipment_id' => $equipmentId,
            'amount' => 1200,
        ]);
    }

    public function test_equipment_store_maps_state_to_status(): void
    {
        [, , $token] = $this->createOwner();

        $response = $this->postJson('/api/equipment', [
            'name' => 'Cable Crossover',
            'category' => 'Strength',
            'state' => 'under_repair',
            'purchased_at' => '2026-08-01',
            'price' => 1450,
        ], $this->authHeader($token));

        $response->assertCreated()->assertJsonPath('data.state', 'under_repair');
        $this->assertDatabaseHas('equipment', [
            'id' => $response->json('data.id'),
            'status' => 'maintenance',
        ]);
    }

    public function test_equipment_store_rejects_invalid_state(): void
    {
        [, , $token] = $this->createOwner();

        $this->postJson('/api/equipment', [
            'name' => 'Bad Machine',
            'category' => 'Strength',
            'state' => 'on_fire',
            'purchased_at' => '2026-08-01',
            'price' => 100,
        ], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['state']);
    }

    public function test_equipment_update_edits_equipment_and_purchase_bill(): void
    {
        [, $gym, $token] = $this->createOwner();

        $equipment = Equipment::create([
            'gym_id' => $gym->id,
            'name' => 'Treadmill X1',
            'category' => 'Cardio',
            'purchase_date' => now()->subYear(),
            'status' => EquipmentStatus::Available,
        ]);

        PurchaseBill::create([
            'equipment_id' => $equipment->id,
            'amount' => 1200.00,
            'purchase_date' => now()->subYear(),
        ]);

        $this->putJson("/api/equipment/{$equipment->id}", [
            'name' => 'Treadmill X1 Pro',
            'category' => 'Cardio',
            'state' => 'out_of_order',
            'purchased_at' => '2026-08-01',
            'price' => 1300,
        ], $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Treadmill X1 Pro')
            ->assertJsonPath('data.state', 'out_of_order')
            ->assertJsonPath('data.purchased_at', '2026-08-01')
            ->assertJsonPath('data.price', 1300);

        $this->assertDatabaseHas('equipment', [
            'id' => $equipment->id,
            'name' => 'Treadmill X1 Pro',
            'status' => 'broken',
        ]);
        $this->assertDatabaseHas('purchase_bills', [
            'equipment_id' => $equipment->id,
            'amount' => 1300,
        ]);
    }

    public function test_equipment_update_rejects_foreign_equipment(): void
    {
        [, , $token] = $this->createOwner();

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $foreignEquipment = Equipment::create([
            'gym_id' => $otherGym->id,
            'name' => 'Rower',
            'category' => 'Cardio',
            'status' => EquipmentStatus::Available,
        ]);

        $this->putJson("/api/equipment/{$foreignEquipment->id}", [
            'name' => 'Hijacked',
            'category' => 'Cardio',
            'state' => 'operational',
            'purchased_at' => '2026-08-01',
            'price' => 100,
        ], $this->authHeader($token))
            ->assertNotFound()
            ->assertJsonPath('message', 'Equipment not found.');
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

    public function test_dashboard_overview_returns_counts_for_the_owners_gym_only(): void
    {
        [, $gym, $token] = $this->createOwner();

        $activeMember = $this->createMember($gym, ['status' => 'active']);
        $this->createSubscription($activeMember, ['ends_at' => today()->addDays(10)]);
        $expiredMember = $this->createMember($gym, ['status' => 'expired']);
        $this->createSubscription($expiredMember, ['ends_at' => now()->subDay()]);

        $activeMember->checkins()->create(['date' => today(), 'check_in' => '07:00']);
        $activeMember->checkins()->create(['date' => today(), 'check_in' => '08:00', 'check_out' => '09:00']);

        Staff::create([
            'gym_id' => $gym->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+15559876543',
            'position' => 'Coach',
            'salary' => 2500.00,
            'hire_date' => now()->subMonths(6),
        ]);

        Equipment::create(['gym_id' => $gym->id, 'name' => 'Treadmill X1', 'status' => EquipmentStatus::Available]);
        Equipment::create(['gym_id' => $gym->id, 'name' => 'Smith Machine', 'status' => EquipmentStatus::Maintenance]);
        Equipment::create(['gym_id' => $gym->id, 'name' => 'Leg Press', 'status' => EquipmentStatus::Broken]);

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $this->createMember($otherGym, ['status' => 'active']);

        $this->getJson('/api/dashboard/overview', $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('totalMembers', 2)
            ->assertJsonPath('activeMembers', 1)
            ->assertJsonPath('expiringMemberships', 1)
            ->assertJsonPath('expiredMembers', 1)
            ->assertJsonPath('totalStaff', 1)
            ->assertJsonPath('activeStaff', 1)
            ->assertJsonPath('totalEquipment', 3)
            ->assertJsonPath('availableEquipment', 1)
            ->assertJsonPath('maintenanceEquipment', 1)
            ->assertJsonPath('brokenEquipment', 1)
            ->assertJsonPath('todayCheckins', 2)
            ->assertJsonPath('insideNow', 1);
    }

    public function test_dashboard_insights_returns_revenue_roster_and_recent_checkins(): void
    {
        [, $gym, $token] = $this->createOwner();

        $monthly = $this->createMember($gym, ['status' => 'active']);
        $subscription = $this->createSubscription($monthly, ['starts_at' => now()]);
        Payment::create([
            'member_subscription_id' => $subscription->id,
            'amount' => 49.99,
            'paid_at' => now(),
            'status' => 'paid',
        ]);

        $annual = $this->createMember($gym, ['status' => 'active']);
        $this->createSubscription($annual, ['plan_name' => 'Annual']);

        $monthly->checkins()->create(['date' => today(), 'check_in' => '07:30']);

        $otherUser = User::create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
        ]);
        $otherGym = Gym::create(['user_id' => $otherUser->id, 'name' => 'Other Gym']);
        $otherMember = $this->createMember($otherGym);
        $otherMember->checkins()->create(['date' => today(), 'check_in' => '07:00']);

        $response = $this->getJson('/api/dashboard/insights', $this->authHeader($token));

        $response->assertOk()
            ->assertJsonPath('activeMembers', 2)
            ->assertJsonPath('todayCheckins', 1)
            ->assertJsonPath('insideNow', 1)
            ->assertJsonPath('monthRevenue', 49.99)
            ->assertJsonPath('recentCheckins.0.member', 'John Smith')
            ->assertJsonPath('recentCheckins.0.check_out', null);

        $roster = collect($response->json('rosterDonut'))->keyBy('label');
        $this->assertSame(1, $roster['Monthly']['value']);
        $this->assertSame(1, $roster['Annual']['value']);

        $membershipRevenue = collect($response->json('membershipRevenue'))->keyBy('plan');
        $this->assertSame(50, $membershipRevenue['Monthly']['value']);
    }

    public function test_dashboard_operations_returns_payroll_and_recent_activity(): void
    {
        [, $gym, $token] = $this->createOwner();

        $coach = Staff::create([
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
            'staff_id' => $coach->id,
            'month' => now()->month,
            'year' => now()->year,
            'amount' => 2500.00,
            'paid_at' => now(),
        ]);

        Staff::create([
            'gym_id' => $gym->id,
            'first_name' => 'On',
            'last_name' => 'Leave',
            'email' => 'onleave@example.com',
            'position' => 'Front Desk',
            'salary' => 2000.00,
            'hire_date' => now()->subMonths(2),
            'status' => 'on_leave',
        ]);

        $treadmill = Equipment::create([
            'gym_id' => $gym->id,
            'name' => 'Treadmill X1',
            'status' => EquipmentStatus::Available,
        ]);

        RepairBill::create([
            'equipment_id' => $treadmill->id,
            'amount' => 85.50,
            'repair_date' => now()->subMonth(),
            'description' => 'Replaced cable',
        ]);

        $this->getJson('/api/dashboard/operations', $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('totalStaff', 2)
            ->assertJsonPath('activeStaff', 1)
            ->assertJsonPath('monthlyPayroll', 2500)
            ->assertJsonPath('recentPayslips.0.name', 'Jane Doe')
            ->assertJsonPath('recentPayslips.0.period', now()->format('F Y'))
            ->assertJsonPath('recentPayslips.0.amount', 2500)
            ->assertJsonPath('totalEquipment', 1)
            ->assertJsonPath('availableEquipment', 1)
            ->assertJsonPath('recentRepairs.0.equipment', 'Treadmill X1')
            ->assertJsonPath('recentRepairs.0.cost', 85.5);
    }

    public function test_dashboard_finances_returns_revenue_expense_trend_for_own_gym(): void
    {
        [, $gym, $token] = $this->createOwner();

        $member = $this->createMember($gym);
        $subscription = $this->createSubscription($member, ['starts_at' => now()]);
        Payment::create([
            'member_subscription_id' => $subscription->id,
            'amount' => 49.99,
            'paid_at' => now(),
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
            'status' => 'paid',
        ]);

        $response = $this->getJson('/api/dashboard/finances', $this->authHeader($token));

        $response->assertOk()
            ->assertJsonPath('monthRevenue', 49.99)
            ->assertJsonPath('monthExpenses', 0)
            ->assertJsonPath('monthNet', 49.99)
            ->assertJsonCount(6, 'revExpData');

        $last = $response->json('revExpData')[5];
        $this->assertSame(49.99, $last['revenue']);
    }

    public function test_gym_show_returns_shaped_gym_for_the_owners_account(): void
    {
        [, $gym, $token] = $this->createOwner();

        $response = $this->getJson('/api/gym', $this->authHeader($token));

        $response->assertOk()
            ->assertJsonPath('data.id', $gym->id)
            ->assertJsonPath('data.name', 'LEAN Downtown')
            ->assertJsonPath('data.opens_at', '06:00')
            ->assertJsonPath('data.closes_at', '22:00')
            ->assertJsonPath('data.days_open', [])
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.registered_at', $gym->created_at->toDateString());
    }

    public function test_gym_show_returns_description_and_days_open_when_set(): void
    {
        [, $gym, $token] = $this->createOwner();

        $gym->update([
            'description' => 'A 24/7 strength and cardio gym.',
            'days_open' => ['mon', 'wed', 'fri'],
        ]);

        $this->getJson('/api/gym', $this->authHeader($token))
            ->assertOk()
            ->assertJsonPath('data.description', 'A 24/7 strength and cardio gym.')
            ->assertJsonPath('data.days_open', ['mon', 'wed', 'fri']);
    }

    public function test_gym_update_updates_the_owners_gym(): void
    {
        [, $gym, $token] = $this->createOwner();

        $payload = [
            'name' => 'LEAN Uptown',
            'description' => 'Rebranded facility.',
            'address' => '99 High Street',
            'email' => 'uptown@lean.example',
            'phone' => '+1 (555) 010-9999',
            'opens_at' => '07:30',
            'closes_at' => '21:45',
            'days_open' => ['tue', 'thu'],
            'status' => 'inactive',
        ];

        $response = $this->putJson('/api/gym', $payload, $this->authHeader($token));

        $response->assertOk()
            ->assertJsonPath('data.id', $gym->id)
            ->assertJsonPath('data.name', 'LEAN Uptown')
            ->assertJsonPath('data.description', 'Rebranded facility.')
            ->assertJsonPath('data.address', '99 High Street')
            ->assertJsonPath('data.email', 'uptown@lean.example')
            ->assertJsonPath('data.phone', '+1 (555) 010-9999')
            ->assertJsonPath('data.opens_at', '07:30')
            ->assertJsonPath('data.closes_at', '21:45')
            ->assertJsonPath('data.days_open', ['tue', 'thu'])
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSame('07:30', $gym->fresh()->opening_time);
        $this->assertSame('21:45', $gym->fresh()->closing_time);
        $this->assertSame(['tue', 'thu'], $gym->fresh()->days_open);
        $this->assertSame('inactive', $gym->fresh()->status->value);
    }

    public function test_gym_update_rejects_invalid_days_and_status(): void
    {
        [, , $token] = $this->createOwner();

        $this->putJson('/api/gym', [
            'name' => 'LEAN Downtown',
            'status' => 'nope',
        ], $this->authHeader($token))->assertUnprocessable();

        $this->putJson('/api/gym', [
            'name' => 'LEAN Downtown',
            'status' => 'active',
            'days_open' => ['someday'],
        ], $this->authHeader($token))->assertUnprocessable();
    }
}
