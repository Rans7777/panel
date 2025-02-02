<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Pages\Auth\Login;

Route::get('/', function () {
    return;
});

Route::get('/admin/login', Login::class)
    ->name('filament.admin.auth.login');

/*
将来的な実装かもしれない
Route::get('/order', function () {
    return view('filament.pages.order-page');
});
*/
