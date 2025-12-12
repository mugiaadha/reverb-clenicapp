<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Message;

Route::get('/messages', function (Request $request) {
    return Message::orderBy('created_at', 'asc')->get();
});
