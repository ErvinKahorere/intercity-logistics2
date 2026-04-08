<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Failed to send email verification notification.', [
                'user_id' => $request->user()?->id,
                'email' => $request->user()?->email,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('error', 'We could not send the verification email right now. Please try again shortly.');
        }

        return back()->with('status', 'verification-link-sent');
    }
}
