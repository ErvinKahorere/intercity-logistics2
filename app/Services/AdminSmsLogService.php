<?php

namespace App\Services;

use App\Models\SmsNotificationLog;

class AdminSmsLogService
{
    public function list(array $filters = []): array
    {
        return SmsNotificationLog::query()
            ->with(['user:id,name,email', 'parcelRequest:id,tracking_number'])
            ->when(($filters['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['provider'] ?? null), fn ($query, $provider) => $query->where('provider', $provider))
            ->when(($filters['search'] ?? null), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('recipient_name', 'like', "%{$search}%")
                        ->orWhere('normalized_phone', 'like', "%{$search}%")
                        ->orWhere('provider_message_id', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('error_message', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->take(200)
            ->get()
            ->map(function (SmsNotificationLog $log) {
                $providerResponse = $log->provider_response ?? [];
                $callbackHistory = is_array($providerResponse['callback_history'] ?? null) ? $providerResponse['callback_history'] : [];

                return [
                    'id' => $log->id,
                    'status' => $log->status,
                    'provider' => $log->provider,
                    'event_type' => $log->event_type,
                    'template_key' => $log->template_key,
                    'recipient_name' => $log->recipient_name,
                    'recipient_phone' => $log->normalized_phone ?: $log->recipient_phone,
                    'message' => $log->message,
                    'provider_message_id' => $log->provider_message_id,
                    'error_message' => $log->error_message,
                    'attempts' => (int) $log->attempts,
                    'tracking_number' => $log->parcelRequest?->tracking_number,
                    'skip_reason' => $log->meta['reason'] ?? null,
                    'provider_status' => $providerResponse['last_status'] ?? null,
                    'callback_history' => $callbackHistory,
                    'last_callback_status' => $callbackHistory[0]['status'] ?? null,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,
                    'queued_at' => optional($log->queued_at)->toIso8601String(),
                    'sent_at' => optional($log->sent_at)->toIso8601String(),
                    'failed_at' => optional($log->failed_at)->toIso8601String(),
                    'updated_at' => optional($log->updated_at)->toIso8601String(),
                ];
            })
            ->all();
    }
}
