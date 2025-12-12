<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Simple chat routes
Route::get('/chat', [\App\Http\Controllers\ChatController::class, 'index']);
Route::post('/chat/send', [\App\Http\Controllers\ChatController::class, 'send']);

// Temporary API endpoint for messages (keeps `/api/messages` working even if
// RouteServiceProvider for api routes is not present)
Route::get('/api/messages', function () {
    return \App\Models\Message::orderBy('created_at', 'asc')->get();
});
