<?php

use App\Http\Controllers\API\V1\WaterBillingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group(['prefix' => 'v1'], function () {

    Route::group(['prefix' => 'billing'], function () {

        // Water billing
        Route::group(['prefix' => 'water'], function () {

            Route::post('siapa', [WaterBillingController::class, 'siapaServices']);
            //Route::post('siapa/{id}', [WaterBillingController::class, 'siapaBill']);

            Route::post('sadm', [WaterBillingController::class, 'sadmServices']);
            //Route::post('sadm/{id}', [WaterBillingController::class, 'sadmBill']);

        });

    });

});
