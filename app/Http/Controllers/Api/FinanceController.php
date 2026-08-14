<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinanceOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(private readonly FinanceOverviewService $finances) {}

    public function overview(Request $request): JsonResponse
    {
        $gym = $request->user()->gym;

        if (! $gym) {
            return response()->json([]);
        }

        return response()->json(
            $this->finances->monthlyOverview($gym, $request->query('period')),
        );
    }
}
