<?php

namespace App\Services;

use App\Mail\GenericEventMail;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailNotificationService
{
    public function queueEvent(?User $user, string $subject, string $title, string $message, array $options = []): ?NotificationLog
    {
        if (! $user) {
            return null;
        }

        $enabled = $this->preferenceState($user, Arr::get($options, 'preference_key'));
        if (! $enabled['enabled']) {
            return $this->createSkippedLog($user, $subject, $message, array_merge($options, ['reason' => $enabled['reason']]));
        }

        if (blank($user->email)) {
            return $this->createSkippedLog($user, $subject, $message, array_merge($options, ['reason' => 'missing_email']));
        }

        return NotificationLog::create([
            'user_id' => $user->id,
            'parcel_request_id' => Arr::get($options, 'parcel_request_id'),
            'channel' => 'email',
            'event_type' => Arr::get($options, 'event_type', 'general'),
            'template_key' => Arr::get($options, 'template_key'),
            'provider' => config('mail.default'),
            'recipient' => $user->email,
            'subject' => $subject,
            'message' => $message,
            'status' => 'queued',
            'meta' => array_merge(Arr::get($options, 'meta', []), ['title' => $title]),
            'queued_at' => now(),
        ]);
    }

    public function deliverLog(NotificationLog $log): NotificationLog
    {
        if (blank(config('mail.default'))) {
            throw new RuntimeException('Mail is not configured.');
        }

        Mail::to($log->recipient)->send(new GenericEventMail(
            $log->subject ?: 'InterCity Logistics update',
            $log->meta['title'] ?? ($log->subject ?: 'InterCity Logistics update'),
            (string) $log->message,
            array_merge($log->meta ?? [], ['tracking_number' => $log->meta['tracking_number'] ?? null]),
        ));

        $log->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        return $log->fresh();
    }

    private function preferenceState(User $user, ?string $preferenceKey = null): array
    {
        if (! $user->email_notifications_enabled) {
            return ['enabled' => false, 'reason' => 'email_disabled'];
        }

        if (! $preferenceKey) {
            return ['enabled' => true, 'reason' => null];
        }

        $preferences = array_merge(config('sms.preferences', []), is_array($user->email_notification_preferences) ? $user->email_notification_preferences : []);

        return [
            'enabled' => (bool) ($preferences[$preferenceKey] ?? true),
            'reason' => ($preferences[$preferenceKey] ?? true) ? null : 'preference_disabled',
        ];
    }

    private function createSkippedLog(User $user, string $subject, string $message, array $options): NotificationLog
    {
        return NotificationLog::create([
            'user_id' => $user->id,
            'parcel_request_id' => Arr::get($options, 'parcel_request_id'),
            'channel' => 'email',
            'event_type' => Arr::get($options, 'event_type', 'general'),
            'template_key' => Arr::get($options, 'template_key'),
            'provider' => config('mail.default'),
            'recipient' => $user->email,
            'subject' => $subject,
            'message' => $message,
            'status' => 'skipped',
            'meta' => array_merge(Arr::get($options, 'meta', []), ['reason' => Arr::get($options, 'reason')]),
            'queued_at' => now(),
        ]);
    }
}
