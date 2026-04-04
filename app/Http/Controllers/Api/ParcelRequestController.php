<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParcelRequest;
use Illuminate\Http\Request;

class ParcelRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = ParcelRequest::query()->with(['pickupLocation', 'dropoffLocation', 'packageType', 'assignedDriver.user']);

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->latest()->get());
    }
}
