<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;
use App\Events\MessageSent;

use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $message = Message::create([
            'user' => $data['user'],
            'message' => $data['message'],
        ]);

        event(new MessageSent($message));

        return response()->json($message, 201);
    }
}
