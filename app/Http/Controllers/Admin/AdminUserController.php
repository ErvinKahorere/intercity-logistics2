<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function page()
    {
        return Inertia::render('Admin/Users/Index');
    }

    public function index(Request $request)
    {
        $users = User::orderByDesc('id')
            ->get()
            ->map(function ($user) {
                return [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role'  => $user->role,
                    'photo' => $user->profile_photo_url,
                    'designation' => null,
                    'about' => null,
                ];
            });

        return response()->json($users, 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::min(8)],
            'role' => ['required', Rule::in(['user', 'Driver', 'admin'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'profile_photo_path' => $request->file('photo')?->store('profiles', 'public'),
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'User' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'photo' => $user->profile_photo_url,
                'designation' => null,
                'about' => null,
            ],
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(8)],
            'role' => ['required', Rule::in(['user', 'Driver', 'admin'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            $user->profile_photo_path = $request->file('photo')->store('profiles', 'public');
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'User' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'photo' => $user->profile_photo_url,
                'designation' => null,
                'about' => null,
            ],
        ]);
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account from this screen.'], 422);
        }

        $user->delete();

        return response()->noContent();
    }
}
