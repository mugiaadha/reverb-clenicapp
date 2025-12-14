<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\QueueCalled;

class QueueController extends Controller
{
    // Return the simple display page
    public function index($channel = null)
    {
        return view('queue', ['channel' => $channel]);
    }

    // API endpoint to call a queue number and broadcast it
    public function call(Request $request)
    {
        $data = $request->validate([
            'prefix' => 'nullable|string|max:3',
            'number' => 'required|integer|min:0',
            'loket' => 'required|integer|min:0',
            'channel' => 'nullable|string|max:64',
        ]);

        $prefix = $data['prefix'] ?? 'A';
        $number = (int) $data['number'];
        $loket = (int) $data['loket'];

        // Dispatch broadcast event on optional channel
        $channel = $data['channel'] ?? null;
        event(new QueueCalled($prefix, $number, $loket, $channel));

        return response()->json(['ok' => true]);
    }
}
