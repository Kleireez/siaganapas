<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UdaraController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/udara', [UdaraController::class, 'udara']);
