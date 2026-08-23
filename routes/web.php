<?php

use App\Http\Controllers\ParentDayController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Veli sayfasi. PUBLIC ve oturumsuzdur; yetki yalnizca token'dan gelir.
// api/v1 altinda DEGIL: bu bir web sayfasi, JSON ucu degil.
// Token tahmin edilemez (32 rastgele bayt) ama yine de kaba kuvvet
// denemelerini yavaslatmak icin hiz siniri var.
Route::get('/v/{token}', [ParentDayController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('parent.day');
