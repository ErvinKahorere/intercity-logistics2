<?php

namespace App\Http\Middleware;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => optional($request->user())->only('id', 'name', 'email', 'role', 'profile_photo_url', 'phone', 'email_verified_at'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'appNotifications' => fn () => $this->notificationsFor($request),
        ]);
    }

    private function notificationsFor(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        return AppNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (AppNotification $notification) => $this->transformNotification($notification, $request))
            ->all();
    }

    private function transformNotification(AppNotification $notification, Request $request): array
    {
        return [
            'id' => $notification->id,
            'icon' => strtoupper(substr($notification->badge ?: $notification->event_type, 0, 2)),
            'title' => $notification->title,
            'message' => $notification->message,
            'time' => $notification->created_at?->diffForHumans(),
            'badge' => $notification->badge ?: 'Update',
            'tone' => $notification->tone,
            'read' => $notification->is_read,
            'meta' => $notification->meta,
            'href' => $this->notificationHrefFor($notification, $request),
            'action_label' => $this->notificationActionLabel($notification, $request),
        ];
    }

    private function notificationHrefFor(AppNotification $notification, Request $request): string
    {
        $user = $request->user();
        $role = strtolower((string) ($user?->role ?? 'user'));
        $parcelId = $notification->parcel_request_id;
        $meta = $notification->meta ?? [];
        $paymentStatus = $meta['payment_status'] ?? null;

        if ($role === 'admin') {
            return $this->safeRoute('admin.schedules.index', '/dashboard');
        }

        if ($role === 'driver') {
            if (in_array($notification->event_type, ['driver_match', 'preferred_driver_match', 'request_taken', 'driver_delivery_update'], true)) {
                return $this->safeRoute('driver.dashboard', '/driver/dashboard');
            }

            return $this->safeRoute('driver.messages', '/driver/messages');
        }

        if ($parcelId && in_array($paymentStatus, ['ready', 'manual', 'paid'], true)) {
            return $this->safeRoute('parcel-requests.payment-ready', '/user/parcels', ['parcelRequest' => $parcelId]);
        }

        if ($parcelId) {
            return $this->safeRoute('user.parcels.index', '/user/parcels');
        }

        return $this->safeRoute('user.parcels.index', '/user/parcels');
    }

    private function notificationActionLabel(AppNotification $notification, Request $request): string
    {
        $role = strtolower((string) ($request->user()?->role ?? 'user'));
        $meta = $notification->meta ?? [];

        if ($role === 'admin') {
            return 'Open Admin';
        }

        if ($role === 'driver') {
            return in_array($notification->event_type, ['driver_match', 'preferred_driver_match', 'request_taken'], true)
                ? 'Open Dashboard'
                : 'Open Messages';
        }

        if (in_array(($meta['payment_status'] ?? null), ['ready', 'manual', 'paid'], true)) {
            return 'Open Payment';
        }

        return 'Open Parcel';
    }

    private function safeRoute(string $name, string $fallback, array $parameters = []): string
    {
        try {
            return route($name, $parameters);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
