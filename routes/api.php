<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\OrderHistoryController;
use App\Http\Controllers\API\AccessTokenController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/order-history', [OrderHistoryController::class, 'index']);
    Route::get('/create-access-token', [AccessTokenController::class, 'index'])->middleware('throttle:5,1');
    Route::get('/access-token/{token}/validity', [AccessTokenController::class, 'expiry'])->middleware('throttle:30,1');
});
