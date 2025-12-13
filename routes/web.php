<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Simple chat routes
Route::get('/chat', [\App\Http\Controllers\ChatController::class, 'index']);
Route::post('/chat/send', [\App\Http\Controllers\ChatController::class, 'send']);
