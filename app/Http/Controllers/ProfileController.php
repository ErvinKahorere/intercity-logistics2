<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail,
            'status' => session('status'),
            'user' => auth()->user(),
        ]);
    }

 /*   public function update(Request $request)
    {
        $validated = $request->validate([
            'name'  => ['required','string','max:255'],
            'email' => ['required','email','max:255'],
            'phone' => ['required','string', 'max:15'],
            'profile_photo' => ['required', 'image', 'max:1024'], // max 1MB
        ]);

        $user = $request->user();
        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
        }


        $user->save();

        return Redirect::route('user.profile')->with('success', 'Profile updated.');
    }
*/

    public function update(ProfileUpdateRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();
        $removePhoto = (bool) ($validated['remove_profile_photo'] ?? false);
        unset($validated['remove_profile_photo']);

        if ($removePhoto && $user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->profile_photo_path = null;
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->profile_photo_path = $path;
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $user->refresh();

        $redirectRoute = $user->hasRole('Driver')
            ? 'driver.profile'
            : 'profile.edit';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => Str::headline(($user->hasRole('Driver') ? 'driver' : 'profile') . ' updated.'),
                'user' => $user,
            ]);
        }

        return Redirect::route($redirectRoute)->with('success', Str::headline(($user->hasRole('Driver') ? 'driver' : 'profile') . ' updated.'));
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required','current_password'],
        ]);

        $user = $request->user();
        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
