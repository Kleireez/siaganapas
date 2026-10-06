<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\UdaraController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/udara', [UdaraController::class, 'udara']);

Route::get('/api/titik-panas', [UdaraController::class, 'titikPanas']);

Route::get('/', HomeController::class)->name('home');
