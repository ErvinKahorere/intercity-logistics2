<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Location;
use App\Models\PackageType;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredDriverController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/DriverRegister', [
            'locations' => Location::orderBy('name')->get(['id', 'name']),
            'packageTypes' => PackageType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'Driver',
        ]);

        Driver::create([
            'user_id' => $user->id,
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'status' => 'active',
        ]);

        event(new Registered($user));

        return redirect()->route('login')->with('success', 'Driver account created successfully. Please sign in.');
    }
}
