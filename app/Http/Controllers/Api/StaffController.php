<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Payslip;
use App\Models\Staff;
use App\Services\FinanceOverviewService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StaffController extends Controller
{
    private const STATUSES = ['active', 'on_leave', 'departed'];

    private const PAYSLIP_STATUSES = ['paid', 'pending', 'failed'];

    public function __construct(private readonly FinanceOverviewService $finances) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $staff = $request->user()->gym?->staff()
            ->with('payslips')
            ->latest('created_at')
            ->get() ?? collect();

        return StaffResource::collection($staff);
    }

    public function store(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json(['message' => 'No gym found for this account.'], 403);
        }

        $data = $this->validateData($request);

        $staff = Staff::create([
            'gym_id' => $gym->id,
            'first_name' => $this->firstName($data['name']),
            'last_name' => $this->lastName($data['name']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'position' => $data['role'],
            'salary' => $data['salary'],
            'hire_date' => $data['joined_at'],
            'status' => $data['status'],
        ]);

        $this->finances->flush($gym);

        return StaffResource::make($staff->load('payslips'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $staff = $this->staffFor($request, $id);

        if (! $staff) {
            return response()->json(['message' => 'Staff member not found.'], 404);
        }

        $data = $this->validateData($request);

        $staff->update([
            'first_name' => $this->firstName($data['name']),
            'last_name' => $this->lastName($data['name']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'position' => $data['role'],
            'salary' => $data['salary'],
            'hire_date' => $data['joined_at'],
            'status' => $data['status'],
        ]);

        if ($gym = $request->user()->gym) {
            $this->finances->flush($gym);
        }

        return StaffResource::make($staff->load('payslips'))->response();
    }

    public function storePayslip(Request $request, int $id): JsonResponse
    {
        $staff = $this->staffFor($request, $id);

        if (! $staff) {
            return response()->json(['message' => 'Staff member not found.'], 404);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['nullable', 'string', 'max:32'],
            'status' => ['required', Rule::in(self::PAYSLIP_STATUSES)],
        ]);

        $paidAt = Carbon::parse($data['date']);

        $payslip = Payslip::create([
            'staff_id' => $staff->id,
            'month' => $paidAt->month,
            'year' => $paidAt->year,
            'amount' => $data['amount'],
            'paid_at' => $paidAt,
            'method' => $data['method'] ?? 'Card',
            'status' => $data['status'],
        ]);

        if ($gym = $request->user()->gym) {
            $this->finances->flush($gym);
        }

        return response()->json([
            'data' => [
                'id' => $payslip->id,
                'date' => $payslip->date,
                'period' => $payslip->period,
                'amount' => (float) $payslip->amount,
                'method' => $payslip->method,
                'status' => $payslip->status->value,
            ],
        ], Response::HTTP_CREATED);
    }

    private function staffFor(Request $request, int $id): ?Staff
    {
        return $request->user()->gym?->staff()->find($id);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'salary' => ['required', 'numeric', 'min:0'],
            'joined_at' => ['required', 'date'],
        ]);
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
