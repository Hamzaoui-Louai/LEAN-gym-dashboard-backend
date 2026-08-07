<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'services' => [
            'database' => DB::connection()->getPdo() !== null,
        ],
    ]);
});
