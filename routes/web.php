<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify/{id}/{hash}', EmailVerificationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'services' => [
            'database' => DB::connection()->getPdo() !== null,
        ],
    ]);
});
