<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\StaffController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail']);

    Route::get('/members', [MemberController::class, 'index']);
    Route::post('/members', [MemberController::class, 'store']);
    Route::put('/members/{id}', [MemberController::class, 'update']);
    Route::delete('/members/{id}', [MemberController::class, 'destroy']);
    Route::get('/staff', [StaffController::class, 'index']);
    Route::get('/equipment', [EquipmentController::class, 'index']);
    Route::get('/equipment/repairs', [EquipmentController::class, 'repairs']);
    Route::get('/checkins', [CheckinController::class, 'index']);
    Route::post('/checkins', [CheckinController::class, 'store']);
    Route::post('/checkins/{id}/check-out', [CheckinController::class, 'checkOut']);
    Route::get('/finances/overview', [FinanceController::class, 'overview']);
});
