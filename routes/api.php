<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\OrderHistoryController;
use App\Http\Controllers\API\AccessToken;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/order-history', [OrderHistoryController::class, 'index']);
    Route::get('/create-access-token', [AccessToken::class, 'index'])->middleware('throttle:3,1');
});
