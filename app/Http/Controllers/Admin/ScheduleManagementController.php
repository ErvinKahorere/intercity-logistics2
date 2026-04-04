<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverMessage;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ScheduleManagementController extends Controller
{
    public function page()
    {
        return Inertia::render('Admin/Schedules/Index');
    }

    public function index(Request $request)
    {
        $search = strtolower(trim((string) $request->string('search')));

        $rows = Schedule::query()
            ->with(['driver.user'])
            ->latest()
            ->get()
            ->map(function (Schedule $schedule) {
                $driverName = $schedule->driver?->user?->name ?? 'Unknown driver';

                return [
                    'id' => $schedule->id,
                    'driver_id' => $schedule->driver_id,
                    'Driver' => $driverName,
                    'Email' => $schedule->driver?->user?->email ?? '',
                    'Days' => collect($schedule->day_of_week ?? [])->implode(', '),
                    'Start' => $schedule->start_time,
                    'End' => $schedule->end_time,
                    'Slots' => $schedule->slot_minutes,
                    'Capacity' => $schedule->max_users_per_day,
                    'Fee' => $schedule->fee,
                ];
            });

        if ($search !== '') {
            $rows = $rows->filter(function ($row) use ($search) {
                return str_contains(strtolower($row['Driver']), $search)
                    || str_contains(strtolower($row['Email']), $search);
            })->values();
        }

        return response()->json($rows);
    }

    public function mention(Request $request)
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'exists:schedules,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if (! Schema::hasTable('driver_messages')) {
            return response()->json([
                'message' => 'Driver messaging is not ready yet. Please run the latest migrations and try again.',
            ], 503);
        }

        DriverMessage::create([
            'schedule_id' => $data['schedule_id'],
            'driver_id' => $data['driver_id'],
            'admin_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        return response()->json(['message' => 'Message sent successfully.']);
    }
}
