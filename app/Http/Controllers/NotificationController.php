<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = AppNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->take(12)
            ->get()
            ->map(fn (AppNotification $notification) => $this->transformNotification($notification, $request))
            ->values();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $notifications->where('read', false)->count(),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $query = AppNotification::query()->where('user_id', $request->user()->id);

        if (! empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        }

        $updated = $query->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['updated' => $updated]);
    }

    private function transformNotification(AppNotification $notification, Request $request): array
    {
        return [
            'id' => $notification->id,
            'icon' => strtoupper(substr($notification->badge ?: $notification->event_type, 0, 2)),
            'title' => $notification->title,
            'message' => $notification->message,
            'badge' => $notification->badge ?: 'Update',
            'tone' => $notification->tone,
            'time' => $notification->created_at?->diffForHumans(),
            'read' => $notification->is_read,
            'meta' => $notification->meta,
            'href' => $this->notificationHrefFor($notification, $request),
            'action_label' => $this->notificationActionLabel($notification, $request),
        ];
    }

    private function notificationHrefFor(AppNotification $notification, Request $request): string
    {
        $role = strtolower((string) ($request->user()?->role ?? 'user'));
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
