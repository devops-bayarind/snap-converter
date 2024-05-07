<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


//Merchant Outbound
//Convert non Snap -> Snap
Route::controller(\App\Http\Controllers\NonSnapToSnapController::class)
    ->middleware(['request.logger'])->group(function () {
        Route::post('/PaymentRegister', 'createVa');
        Route::post('/PaymentQuery', 'queryStatus');
        Route::post('/PostAuth', 'voidTransaction');
    });



//Merchant Inbound
//Convert Snap -> Non Snap
$currentVersion = env("APP_VERSION", "v1.0");
Route::middleware(['request.logger', 'snap.authentication'])
    ->prefix("$currentVersion/transfer-va")
    ->group(function () {
        Route::post('/payment', [\App\Http\Controllers\SnapToNonSnapController::class, 'payment']);
        Route::post('/inquiry', [\App\Http\Controllers\SnapToNonSnapController::class, 'inquiry']);
    });
