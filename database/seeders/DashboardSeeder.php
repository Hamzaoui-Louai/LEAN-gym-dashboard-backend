<?php

namespace Database\Seeders;

use App\Enums\EquipmentStatus;
use App\Enums\GymStatus;
use App\Enums\PaymentStatus;
use App\Enums\StaffStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Gym;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Database\Factories\MemberSubscriptionFactory;
use Database\Factories\StaffFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardSeeder extends Seeder
{
    private const OWNER_EMAIL = 'gym-owner@lean.local';

    private const OWNER_PASSWORD = 'password';

    private const MEMBERS = [
        ['Sarah Chen', 'sarah.chen@example.com', '+1 (555) 014-2201', 'active', '2024-03-12', 'Monthly', 45, '2026-07-01', '2026-02-01', 6, 'pending'],
        ['Marcus Johnson', 'marcus.johnson@example.com', '+1 (555) 014-2202', 'active', '2023-11-05', 'Annual', 420, '2026-01-01', '2024-01-01', 3, 'pending'],
        ['Priya Patel', 'priya.patel@example.com', '+1 (555) 014-2203', 'active', '2025-06-18', 'Quarterly', 120, '2026-05-01', '2025-08-01', 5, 'pending'],
        ['Daniel Osei', 'daniel.osei@example.com', '+1 (555) 014-2204', 'frozen', '2024-09-30', 'Monthly', 45, '2026-07-01', '2026-02-01', 5, 'paid'],
        ['Emily Novak', 'emily.novak@example.com', '+1 (555) 014-2205', 'active', '2025-10-02', 'Pay-as-you-go', 10, '2026-08-01', '2026-05-01', 4, 'paid'],
        ['Tomás Rivera', 'tomas.rivera@example.com', '+1 (555) 014-2206', 'expired', '2024-01-22', 'Monthly', 45, '2026-06-01', '2026-01-01', 6, 'failed'],
        ['Aisha Bello', 'aisha.bello@example.com', '+1 (555) 014-2207', 'active', '2025-03-08', 'Annual', 420, '2026-01-01', '2025-01-01', 2, 'pending'],
        ['Liam Fitzgerald', 'liam.fitzgerald@example.com', '+1 (555) 014-2208', 'active', '2025-11-14', 'Quarterly', 120, '2026-08-01', '2025-11-01', 4, 'pending'],
        ['Sofia Marchetti', 'sofia.marchetti@example.com', '+1 (555) 014-2209', 'frozen', '2024-07-19', 'Monthly', 45, '2026-07-01', '2026-03-01', 4, 'paid'],
        ['Jordan Wright', 'jordan.wright@example.com', '+1 (555) 014-2210', 'active', '2025-08-27', 'Monthly', 45, '2026-08-01', '2026-02-01', 7, 'pending'],
        ['Nina Kowalski', 'nina.kowalski@example.com', '+1 (555) 014-2211', 'expired', '2023-05-11', 'Monthly', 45, '2026-05-01', '2025-12-01', 6, 'failed'],
        ['Omar Haddad', 'omar.haddad@example.com', '+1 (555) 014-2212', 'active', '2025-06-30', 'Annual', 420, '2026-06-01', '2025-06-01', 2, 'pending'],
        ['Grace Tan', 'grace.tan@example.com', '+1 (555) 014-2213', 'active', '2026-08-10', 'Pay-as-you-go', 10, '2026-08-10', null, 0, 'paid'],
    ];

    private const STAFF = [
        ['James Carter', 'james.carter@example.com', '+1 (555) 015-3101', 'Manager', 'active', '2023-02-14', 3200, '2026-03-01', 6],
        ['Lina Fischer', 'lina.fischer@example.com', '+1 (555) 015-3102', 'Personal trainer', 'active', '2024-05-20', 1800, '2026-04-01', 5],
        ['Andre Silva', 'andre.silva@example.com', '+1 (555) 015-3103', 'Strength coach', 'active', '2024-08-03', 2000, '2026-02-01', 7],
        ['Mia Patel', 'mia.patel@example.com', '+1 (555) 015-3104', 'Front desk', 'on_leave', '2025-01-11', 1400, '2026-03-01', 4],
        ['Yusuf Adebayo', 'yusuf.adebayo@example.com', '+1 (555) 015-3105', 'Personal trainer', 'active', '2024-11-27', 1700, '2026-01-01', 8],
        ['Hannah Lee', 'hannah.lee@example.com', '+1 (555) 015-3106', 'Cleaning staff', 'active', '2025-07-09', 1100, '2026-03-01', 6],
        ['Omar Khalil', 'omar.khalil@example.com', '+1 (555) 015-3107', 'Front desk', 'active', '2025-04-22', 1350, '2026-02-01', 7],
        ['Ella Mårtensson', 'ella.martensson@example.com', '+1 (555) 015-3108', 'Personal trainer', 'active', '2023-10-30', 1900, '2026-01-01', 8],
        ['Dmitri Volkov', 'dmitri.volkov@example.com', '+1 (555) 015-3109', 'Strength coach', 'departed', '2024-03-05', 2100, '2025-11-01', 6],
        ['Aria Wilson', 'aria.wilson@example.com', '+1 (555) 015-3110', 'Manager', 'active', '2025-09-16', 3400, '2026-02-01', 7],
        ['Ben Okafor', 'ben.okafor@example.com', '+1 (555) 015-3111', 'Cleaning staff', 'on_leave', '2026-01-08', 1150, '2026-03-01', 4],
        ['Chloe Nguyen', 'chloe.nguyen@example.com', '+1 (555) 015-3112', 'Front desk', 'active', '2025-12-04', 1450, null, 0],
    ];

    private const EQUIPMENT = [
        ['Squat Rack', 'Strength', 1850, 'operational', '2024-01-15'],
        ['Olympic Barbell Set', 'Strength', 620, 'in_use', '2024-02-08'],
        ['Bench Press', 'Strength', 480, 'operational', '2024-03-21'],
        ['Dumbbell Rack', 'Strength', 350, 'operational', '2024-04-02'],
        ['Cable Crossover', 'Strength', 1450, 'out_of_order', '2024-05-17'],
        ['Leg Press', 'Strength', 2100, 'operational', '2024-06-09'],
        ['Lat Pulldown', 'Strength', 890, 'in_use', '2024-07-01'],
        ['Power Rack', 'Strength', 2400, 'operational', '2024-08-14'],
        ['Treadmill', 'Cardio', 1250, 'operational', '2024-09-05'],
        ['Elliptical', 'Cardio', 1100, 'in_use', '2024-10-19'],
        ['Stationary Bike', 'Cardio', 520, 'operational', '2024-11-11'],
        ['Rowing Machine', 'Cardio', 940, 'in_use', '2024-12-01'],
        ['Stair Climber', 'Cardio', 1780, 'under_repair', '2025-01-22'],
        ['Spin Bike', 'Cardio', 480, 'in_use', '2025-02-14'],
        ['Yoga Mats (Set of 20)', 'Flexibility', 210, 'operational', '2025-03-03'],
        ['Stability Balls (Set of 10)', 'Flexibility', 260, 'operational', '2025-03-30'],
        ['Kettlebell Set', 'Functional', 540, 'operational', '2025-04-18'],
        ['Battle Ropes', 'Functional', 120, 'operational', '2025-05-06'],
        ['Plyo Boxes (Set)', 'Functional', 420, 'under_repair', '2025-06-25'],
        ['Medicine Balls (Set)', 'Functional', 300, 'operational', '2025-07-12'],
        ['Leg Curl Machine', 'Strength', 760, 'operational', '2025-08-04'],
        ['Preacher Curl Bench', 'Strength', 300, 'operational', '2025-09-15'],
        ['Smith Machine', 'Strength', 1650, 'under_repair', '2025-10-20'],
        ['Upright Bike', 'Cardio', 760, 'out_of_order', '2025-11-28'],
    ];

    private const REPAIRS = [
        ['Squat Rack', 'Replaced worn J-hooks and tightened frame bolts', 85, '2026-07-02'],
        ['Cable Crossover', 'Replaced snapped pulley cable', 240, '2026-06-18'],
        ['Smith Machine', 'Replaced worn carriage rollers', 130, '2026-06-10'],
        ['Stair Climber', 'Serviced drive belt and pedals', 175, '2026-05-28'],
        ['Leg Press', 'Fixed hydraulic piston leak', 320, '2026-05-12'],
        ['Plyo Boxes (Set)', 'Recovered tops with anti-slip coating', 90, '2026-04-20'],
        ['Treadmill', 'Calibrated belt tension and replaced rollers', 145, '2026-04-02'],
        ['Rowing Machine', 'Replaced chain and cleaned rail', 110, '2026-03-15'],
        ['Bench Press', 'Replaced safety catches', 60, '2026-03-01'],
        ['Upright Bike', 'Replaced resistance magnet assembly', 200, '2026-02-11'],
        ['Lat Pulldown', 'Re-routed cable and replaced pulley', 95, '2026-01-26'],
        ['Elliptical', 'Replaced worn pedal straps', 40, '2026-01-08'],
        ['Stationary Bike', 'Lubricated drivetrain and adjusted seat post', 55, '2025-12-19'],
        ['Spin Bike', 'Replaced handlebar grips and brake pads', 70, '2025-12-02'],
        ['Power Rack', 'Welded a cracked base joint', 180, '2025-11-14'],
        ['Cable Crossover', 'Replaced grips and tightened stack pins', 65, '2025-10-30'],
        ['Treadmill', 'Replaced console power supply', 120, '2025-10-06'],
        ['Dumbbell Rack', 'Replaced rusty plate pins', 35, '2025-09-18'],
    ];

    private const FILLER_MEMBERS = 140;

    private const FILLER_STAFF = 18;

    private const FILLER_EQUIPMENT = 20;

    private const PLAN_MONTHS = [
        'Monthly' => 1,
        'Quarterly' => 3,
        'Annual' => 12,
        'Pay-as-you-go' => 0,
    ];

    private const METHODS = ['Card', 'Cash', 'Transfer'];

    public function run(): void
    {
        $targetEmail = env('DB_SEED_USER') ?: self::OWNER_EMAIL;

        $user = User::firstOrCreate(
            ['email' => $targetEmail],
            ['name' => 'Gym Owner', 'password' => Hash::make(self::OWNER_PASSWORD), 'email_verified_at' => now()],
        );

        $userWasCreated = $user->wasRecentlyCreated;

        UserSubscription::firstOrCreate(
            ['user_id' => $user->id],
            ['plan' => SubscriptionPlan::Basic, 'valid_until' => now()->addYear()],
        );

        $gym = $user->gym()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'name' => 'Lean Fitness Club',
            'phone' => '+1 (555) 010-2000',
            'email' => 'hello@leanfitness.example',
            'address' => '48 Fitness Avenue, Springfield',
            'opening_time' => '06:00',
            'closing_time' => '23:00',
            'status' => GymStatus::Active,
        ]);

        $this->resetGymData($gym);

        Cache::flush();

        $this->seedMembers($gym);
        $this->seedStaff($gym);
        $this->seedEquipment($gym);
        $this->seedFiller($gym);

        if ($user->email !== self::OWNER_EMAIL) {
            User::where('email', self::OWNER_EMAIL)->delete();
        }

        $credentials = $userWasCreated ? ' / '.self::OWNER_PASSWORD : ' (existing account, password untouched)';
        $this->command?->info('DashboardSeeder complete. Seeded data to: '.$user->email.$credentials);
    }

    private function resetGymData(Gym $gym): void
    {
        $memberIds = DB::table('members')->where('gym_id', $gym->id)->pluck('id');

        if ($memberIds->isNotEmpty()) {
            $subscriptionIds = DB::table('member_subscriptions')->whereIn('member_id', $memberIds)->pluck('id');

            if ($subscriptionIds->isNotEmpty()) {
                DB::table('payments')->whereIn('member_subscription_id', $subscriptionIds)->delete();
                DB::table('member_subscriptions')->whereIn('member_id', $memberIds)->delete();
            }

            DB::table('checkins')->whereIn('member_id', $memberIds)->delete();
            DB::table('members')->where('gym_id', $gym->id)->delete();
        }

        $staffIds = DB::table('staff')->where('gym_id', $gym->id)->pluck('id');

        if ($staffIds->isNotEmpty()) {
            DB::table('payslips')->whereIn('staff_id', $staffIds)->delete();
            DB::table('staff')->where('gym_id', $gym->id)->delete();
        }

        $equipmentIds = DB::table('equipment')->where('gym_id', $gym->id)->pluck('id');

        if ($equipmentIds->isNotEmpty()) {
            DB::table('repair_bills')->whereIn('equipment_id', $equipmentIds)->delete();
            DB::table('purchase_bills')->whereIn('equipment_id', $equipmentIds)->delete();
            DB::table('equipment')->where('gym_id', $gym->id)->delete();
        }
    }

    private function seedMembers(Gym $gym): void
    {
        $now = now();
        $memberId = (int) DB::table('members')->max('id');
        $subscriptions = [];

        foreach (self::MEMBERS as $index => [$name, $email, $phone, $status, $joinedAt, $plan, $price, $startedAt, $paymentsStart, $paymentCount, $lastStatus]) {
            [$firstName, $lastName] = $this->splitName($name);
            $memberId += 1;

            DB::table('members')->insert([
                'id' => $memberId,
                'gym_id' => $gym->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'joined_at' => $joinedAt,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $endsAt = self::PLAN_MONTHS[$plan] > 0
                ? $this->addMonths($startedAt, self::PLAN_MONTHS[$plan])->subDay()->toDateString()
                : null;

            DB::table('member_subscriptions')->insert([
                'member_id' => $memberId,
                'plan_name' => $plan,
                'price' => $price,
                'starts_at' => $startedAt,
                'ends_at' => $endsAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $subscriptionId = DB::table('member_subscriptions')->where('member_id', $memberId)->value('id');

            if ($paymentsStart !== null && $paymentCount > 0) {
                DB::table('payments')->insert(
                    $this->paymentRows($subscriptionId, $price, $paymentsStart, $paymentCount, $lastStatus),
                );
            }

            if ($index % 3 === 0) {
                DB::table('checkins')->insert([
                    'member_id' => $memberId,
                    'date' => now()->toDateString(),
                    'check_in' => '0'.(7 + ($index % 2)).':'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT),
                    'check_out' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } elseif ($index % 3 === 1) {
                DB::table('checkins')->insert([
                    'member_id' => $memberId,
                    'date' => now()->toDateString(),
                    'check_in' => '07:'.str_pad((string) (15 + $index), 2, '0', STR_PAD_LEFT),
                    'check_out' => now()->format('H:i'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            for ($offset = 1; $offset <= 30; $offset += 1) {
                if (($offset + $index) % 3 !== 0) {
                    DB::table('checkins')->insert([
                        'member_id' => $memberId,
                        'date' => now()->subDays($offset)->toDateString(),
                        'check_in' => $this->morningTime($memberId, $index),
                        'check_out' => $this->eveningTime($memberId, $index),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function seedStaff(Gym $gym): void
    {
        $now = now();
        $staffId = (int) DB::table('staff')->max('id');

        foreach (self::STAFF as $index => [$name, $email, $phone, $position, $status, $joinedAt, $salary, $payslipsStart, $payslipCount]) {
            [$firstName, $lastName] = $this->splitName($name);
            $staffId += 1;

            DB::table('staff')->insert([
                'id' => $staffId,
                'gym_id' => $gym->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'position' => $position,
                'salary' => $salary,
                'hire_date' => $joinedAt,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($payslipsStart !== null && $payslipCount > 0) {
                DB::table('payslips')->insert($this->payslipRows($staffId, $salary, $payslipsStart, $payslipCount));
            }
        }
    }

    private function seedEquipment(Gym $gym): void
    {
        $now = now();
        $equipmentId = (int) DB::table('equipment')->max('id');
        $idsByName = [];

        foreach (self::EQUIPMENT as [$name, $category, $price, $state, $purchasedAt]) {
            $equipmentId += 1;
            $idsByName[$name] = $equipmentId;

            DB::table('equipment')->insert([
                'id' => $equipmentId,
                'gym_id' => $gym->id,
                'name' => $name,
                'category' => $category,
                'purchase_date' => $purchasedAt,
                'status' => $this->stateToStatus($state)->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('purchase_bills')->insert([
                'equipment_id' => $equipmentId,
                'amount' => $price,
                'purchase_date' => $purchasedAt,
                'vendor' => self::METHODS[$equipmentId % count(self::METHODS)],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::REPAIRS as [$name, $issue, $cost, $date]) {
            if (isset($idsByName[$name])) {
                DB::table('repair_bills')->insert([
                    'equipment_id' => $idsByName[$name],
                    'amount' => $cost,
                    'repair_date' => $date,
                    'description' => $issue,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedFiller(Gym $gym): void
    {
        $plans = array_keys(self::PLAN_MONTHS);
        $now = now();
        $memberId = (int) DB::table('members')->max('id');
        $staffId = (int) DB::table('staff')->max('id');
        $equipmentId = (int) DB::table('equipment')->max('id');

        for ($i = 0; $i < self::FILLER_MEMBERS; $i += 1) {
            $status = fake()->randomElement(['active', 'active', 'active', 'frozen', 'expired']);
            $plan = fake()->randomElement($plans);
            $price = MemberSubscriptionFactory::PLANS[$plan];
            $startsAt = now()->startOfMonth()->subMonths(fake()->numberBetween(3, 11));
            $endsAt = self::PLAN_MONTHS[$plan] > 0
                ? $startsAt->copy()->addMonths(self::PLAN_MONTHS[$plan])->subDay()
                : null;

            $memberId += 1;

            DB::table('members')->insert([
                'id' => $memberId,
                'gym_id' => $gym->id,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => fake()->phoneNumber(),
                'joined_at' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('member_subscriptions')->insert([
                'member_id' => $memberId,
                'plan_name' => $plan,
                'price' => $price,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $subscriptionId = DB::table('member_subscriptions')->where('member_id', $memberId)->value('id');

            $payments = [];

            for ($month = 0; $month <= 11; $month += 1) {
                $paidAt = $startsAt->copy()->addMonths($month)->startOfMonth();

                if ($paidAt->gt(now())) {
                    break;
                }

                $payments[] = [
                    'member_subscription_id' => $subscriptionId,
                    'amount' => $price,
                    'paid_at' => $paidAt,
                    'method' => fake()->randomElement(self::METHODS),
                    'status' => fake()->randomElement(['paid', 'paid', 'paid', 'pending', 'failed']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($payments !== []) {
                DB::table('payments')->insert($payments);
            }

            if ($i % 5 === 0) {
                DB::table('checkins')->insert([
                    'member_id' => $memberId,
                    'date' => now()->toDateString(),
                    'check_in' => sprintf('%02d:%02d', fake()->numberBetween(6, 10), fake()->numberBetween(0, 59)),
                    'check_out' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        for ($i = 0; $i < self::FILLER_STAFF; $i += 1) {
            $salary = fake()->randomFloat(2, 1100, 3400);
            $staffId += 1;

            DB::table('staff')->insert([
                'id' => $staffId,
                'gym_id' => $gym->id,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => fake()->phoneNumber(),
                'position' => fake()->randomElement(StaffFactory::ROLES),
                'salary' => $salary,
                'hire_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
                'status' => StaffStatus::Active->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $payslips = [];

            for ($month = 5; $month >= 0; $month -= 1) {
                $date = now()->subMonths($month);

                $payslips[] = [
                    'staff_id' => $staffId,
                    'month' => (int) $date->format('n'),
                    'year' => (int) $date->format('Y'),
                    'amount' => $salary,
                    'paid_at' => $date->endOfMonth(),
                    'method' => fake()->randomElement(self::METHODS),
                    'status' => $month === 0 ? PaymentStatus::Pending->value : PaymentStatus::Paid->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('payslips')->insert($payslips);
        }

        for ($i = 0; $i < self::FILLER_EQUIPMENT; $i += 1) {
            $state = fake()->randomElement(['operational', 'in_use', 'under_repair', 'out_of_order']);
            $purchasedAt = fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d');
            $price = fake()->randomFloat(2, 120, 3000);
            $equipmentId += 1;

            DB::table('equipment')->insert([
                'id' => $equipmentId,
                'gym_id' => $gym->id,
                'name' => fake()->randomElement([
                    'Squat Rack', 'Power Rack', 'Leg Press', 'Treadmill', 'Stationary Bike',
                    'Kettlebell Set', 'Rowing Machine', 'Dumbbell Rack', 'Smith Machine', 'Spin Bike',
                ]).' #'.($i + 1),
                'category' => fake()->randomElement(['Strength', 'Cardio', 'Flexibility', 'Functional']),
                'purchase_date' => $purchasedAt,
                'status' => $this->stateToStatus($state)->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('purchase_bills')->insert([
                'equipment_id' => $equipmentId,
                'amount' => $price,
                'purchase_date' => $purchasedAt,
                'vendor' => fake()->company(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($state === 'under_repair' || $state === 'out_of_order') {
                DB::table('repair_bills')->insert([
                    'equipment_id' => $equipmentId,
                    'amount' => fake()->randomFloat(2, 25, 350),
                    'repair_date' => fake()->dateTimeBetween('-6 months', 'now'),
                    'description' => fake()->sentence(4),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function paymentRows(int $subscriptionId, float $price, string $start, int $count, string $last): array
    {
        $now = now();
        $rows = [];

        for ($index = 0; $index < $count; $index += 1) {
            $rows[] = [
                'member_subscription_id' => $subscriptionId,
                'amount' => $price,
                'paid_at' => $this->addMonths($start, $index),
                'method' => self::METHODS[$index % count(self::METHODS)],
                'status' => $index === $count - 1 ? $last : PaymentStatus::Paid->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    private function payslipRows(int $staffId, float $salary, string $start, int $count): array
    {
        $now = now();
        $rows = [];

        for ($index = 0; $index < $count; $index += 1) {
            $date = $this->addMonths($start, $index);

            $rows[] = [
                'staff_id' => $staffId,
                'month' => (int) $date->format('n'),
                'year' => (int) $date->format('Y'),
                'amount' => $salary,
                'paid_at' => $date,
                'method' => self::METHODS[$index % count(self::METHODS)],
                'status' => $index === $count - 1 ? PaymentStatus::Pending->value : PaymentStatus::Paid->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    private function morningTime(int $id, int $index): string
    {
        $hour = 6 + (($id * 3) % 4);
        $minute = 30 + ($index % 25);

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function eveningTime(int $id, int $index): string
    {
        $hour = 16 + ($id % 4);
        $minute = 5 + (($id * 7) % 50);

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function stateToStatus(string $state): EquipmentStatus
    {
        return match ($state) {
            'under_repair' => EquipmentStatus::Maintenance,
            'out_of_order' => EquipmentStatus::Broken,
            default => EquipmentStatus::Available,
        };
    }

    private function addMonths(string $date, int $months): Carbon
    {
        return Carbon::parse($date.'T00:00:00')->addMonths($months);
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', $name, 2);

        return [rtrim($parts[0], ','), trim($parts[1] ?? '')];
    }
}
