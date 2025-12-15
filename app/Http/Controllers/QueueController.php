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
            'pasien' => 'required|string|max:255',
            'channel' => 'nullable|string|max:64',
        ]);

        $prefix = $data['prefix'] ?? 'A';
        $number = (int) $data['number'];
        $pasien = (string) $data['pasien'];

        // Dispatch broadcast event on optional channel
        $channel = $data['channel'] ?? null;
        event(new QueueCalled($prefix, $number, $pasien, $channel));

        return response()->json(['ok' => true]);
    }
}
