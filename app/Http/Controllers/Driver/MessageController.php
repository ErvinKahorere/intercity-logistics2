<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('Driver/Messages');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $driver = $user?->driver;

        if (! $user || ! $driver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! Schema::hasTable('driver_messages')) {
            return response()->json([], 200);
        }

        $messages = DriverMessage::where('driver_id', $driver->id)
            ->with(['admin:id,name,email', 'schedule:id,day_of_week,start_time,end_time'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($msg) {
                $days = collect($msg->schedule?->day_of_week ?? [])->implode(', ');

                return [
                    'id' => $msg->id,
                    'message' => $msg->message,
                    'admin_name' => $msg->admin?->name ?? 'Admin',
                    'schedule_info' => $msg->schedule ? "Schedule: {$days} {$msg->schedule->start_time}-{$msg->schedule->end_time}" : null,
                    'created_at' => $msg->created_at->format('M d, Y H:i'),
                ];
            });

        return response()->json($messages, 200);
    }
}
