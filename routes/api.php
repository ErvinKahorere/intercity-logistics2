<?php

use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\ParcelRequestController;
use App\Http\Controllers\Driver\DriverRoutesController;
use App\Http\Controllers\Driver\DriverProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParcelRequestController as WebParcelRequestController;
use App\Models\Location;
use App\Models\PackageType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/drivers', [DriverController::class, 'index']);
Route::get('/locations', fn () => response()->json(Location::orderBy('name')->get(['id', 'name'])));
Route::get('/package-types', fn () => response()->json(PackageType::orderBy('name')->get(['id', 'name'])));
Route::middleware('auth:sanctum')->get('/parcel-requests', [ParcelRequestController::class, 'index']);


Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/driver/dashboard', [DriverRoutesController::class, 'dashboardData']);
    Route::get('/user/parcels', [WebParcelRequestController::class, 'data']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/admin/driver-verification-queue', [DriverProfileController::class, 'adminQueue']);
});
