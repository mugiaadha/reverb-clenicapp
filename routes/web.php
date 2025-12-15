<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\QueueController;

Route::get('/', function () {
    return view('welcome');
});

// Simple chat routes
Route::get('/chat', [ChatController::class, 'index']);
Route::post('/chat/send', [ChatController::class, 'send']);

// Queue display
Route::get('/queue', [QueueController::class, 'index']);
// Dynamic channel URL, e.g. /tabaro/queue will set channel='tabaro'
Route::get('/{channel}/queue', [QueueController::class, 'index']);
