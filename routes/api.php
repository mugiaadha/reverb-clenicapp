<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Message;

Route::get('/messages', function (Request $request) {
    return Message::orderBy('created_at', 'asc')->get();
});

Route::post('/chat/send', [\App\Http\Controllers\ChatController::class, 'send']);
// API to trigger queue call (broadcasts via Reverb)
Route::post('/queue/call', [\App\Http\Controllers\QueueController::class, 'call']);
