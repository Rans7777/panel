<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProductOptionController;
use App\Http\Controllers\API\OrderController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/products', [ProductController::class, 'index']);
Route::get('/product-options', [ProductOptionController::class, 'index']);
Route::put('/order', [OrderController::class, 'store']);
