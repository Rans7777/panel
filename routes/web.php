<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Pages\Auth\Login;

Route::get('/', function () {
    return;
});

Route::post('/admin/login', [Login::class, 'authenticate'])->name('filament.admin.auth.login');
Route::redirect('/','/admin/login');
Route::get('/order', fn () => view('order'))->name('order');
Route::get('/menu', fn () => view('menu'))->name('menu');
Route::get('/order-history', fn () => view('order-history'))->name('order-history');
