<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GymController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\StaffController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user', [AuthController::class, 'update']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::delete('/user', [AuthController::class, 'destroy']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail']);

    Route::get('/members', [MemberController::class, 'index']);
    Route::post('/members', [MemberController::class, 'store']);
    Route::put('/members/{id}', [MemberController::class, 'update']);
    Route::delete('/members/{id}', [MemberController::class, 'destroy']);
    Route::post('/members/{id}/subscribe', [MemberController::class, 'subscribe']);
    Route::post('/members/{id}/freeze', [MemberController::class, 'freeze']);
    Route::post('/members/{id}/unfreeze', [MemberController::class, 'unfreeze']);
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::put('/staff/{id}', [StaffController::class, 'update']);
    Route::post('/staff/{id}/payslips', [StaffController::class, 'storePayslip']);
    Route::get('/equipment', [EquipmentController::class, 'index']);
    Route::post('/equipment', [EquipmentController::class, 'store']);
    Route::get('/equipment/purchases', [EquipmentController::class, 'purchases']);
    Route::get('/equipment/repairs', [EquipmentController::class, 'repairs']);
    Route::put('/equipment/{id}', [EquipmentController::class, 'update']);
    Route::post('/equipment/{id}/repair', [EquipmentController::class, 'markUnderRepair']);
    Route::post('/equipment/{id}/repaired', [EquipmentController::class, 'markRepaired']);
    Route::post('/equipment/{id}/out-of-order', [EquipmentController::class, 'markOutOfOrder']);
    Route::get('/checkins', [CheckinController::class, 'index']);
    Route::post('/checkins', [CheckinController::class, 'store']);
    Route::post('/checkins/{id}/check-out', [CheckinController::class, 'checkOut']);
    Route::get('/finances/overview', [FinanceController::class, 'overview']);

    Route::get('/gym', [GymController::class, 'show']);
    Route::put('/gym', [GymController::class, 'update']);

    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('/dashboard/insights', [DashboardController::class, 'insights']);
    Route::get('/dashboard/operations', [DashboardController::class, 'operations']);
    Route::get('/dashboard/finances', [DashboardController::class, 'finances']);
});
