<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Simple chat routes
Route::get('/chat', [\App\Http\Controllers\ChatController::class, 'index']);
Route::post('/chat/send', [\App\Http\Controllers\ChatController::class, 'send']);

// Queue display
Route::get('/queue', [\App\Http\Controllers\QueueController::class, 'index']);
// Dynamic channel URL, e.g. /tabaro/queue will set channel='tabaro'
Route::get('/{channel}/queue', [\App\Http\Controllers\QueueController::class, 'index']);
