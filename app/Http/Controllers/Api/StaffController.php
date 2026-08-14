<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StaffController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $staff = $request->user()->gym?->staff()
            ->with('payslips')
            ->latest('created_at')
            ->get() ?? collect();

        return StaffResource::collection($staff);
    }
}
