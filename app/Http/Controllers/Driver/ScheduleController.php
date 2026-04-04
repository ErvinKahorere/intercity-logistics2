<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('Driver/Schedules/Index');
    }

    public function index()
    {
        $driver = $this->resolveDriver();

        if (! $driver) {
            return response()->json(['message' => 'No driver profile found for this user.'], 422);
        }

        return response()->json(
            $driver->schedules()->latest()->get(),
            200
        );
    }

    public function store(Request $request)
    {
        $driver = $this->resolveDriver();

        if (! $driver) {
            return response()->json(['message' => 'No driver profile found for this user.'], 422);
        }

        $data = $this->validatedPayload($request);

        $schedule = Schedule::create([
            ...$data,
            'driver_id' => $driver->id,
        ]);

        return response()->json($schedule, 201);
    }

    public function update(Request $request, int $id)
    {
        $driver = $this->resolveDriver();

        if (! $driver) {
            return response()->json(['message' => 'No driver profile found for this user.'], 422);
        }

        $schedule = Schedule::where('driver_id', $driver->id)->findOrFail($id);
        $schedule->update($this->validatedPayload($request));

        return response()->json($schedule->refresh());
    }

    public function destroy(int $id)
    {
        $driver = $this->resolveDriver();

        if (! $driver) {
            return response()->json(['message' => 'No driver profile found for this user.'], 422);
        }

        $schedule = Schedule::where('driver_id', $driver->id)->findOrFail($id);
        $schedule->delete();

        return response()->noContent();
    }

    private function resolveDriver(): ?Driver
    {
        return Auth::user()?->driver;
    }

    private function validatedPayload(Request $request): array
    {
        $data = $request->validate([
            'day_of_week' => ['required', 'array', 'min:1'],
            'day_of_week.*' => [Rule::in(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'])],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'max_users_per_day' => ['required', 'integer', 'min:1', 'max:500'],
            'fee' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $duration = Carbon::parse($data['start_time'])
            ->diffInMinutes(Carbon::parse($data['end_time']));

        if ($duration < $data['slot_minutes']) {
            abort(response()->json([
                'message' => 'Slot minutes must be less than or equal to the total duration.',
            ], 422));
        }

        return $data;
    }
}
