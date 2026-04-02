<?php

use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;
use App\Extensions\Authentication\AuthenticationController;

Route::get('test', function () {
    return ['ok'];
});

# Authorization
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthenticationController::class, 'register']);
    Route::post('login', [AuthenticationController::class, 'login']);
    Route::post('logout', [AuthenticationController::class, 'logout'])->middleware('auth');
    Route::post('password-reset-request', [AuthenticationController::class, 'passwordResetRequest']);
    Route::post('password-reset', [AuthenticationController::class, 'passwordReset']);
});

Route::middleware('auth')->group(function () {
});

Route::prefix('/v1')->group(function () {
    Route::prefix('/widgets')->group(function (){
        Route::get('/active-count', [WidgetController::class, 'activeCount']);
        Route::get('/dynamics', [WidgetController::class, 'dynamics']);
    });
});


