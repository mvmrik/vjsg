<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Events\Event;

class EventController extends Controller
{
    // Return currently active event (or null)
    public function current(Request $request)
    {
        $event = Event::where('is_active', true)->orderBy('started_at', 'desc')->first();
        return response()->json(['success' => true, 'event' => $event]);
    }
}
