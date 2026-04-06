<?php

namespace App\Services;

use App\Jobs\SendChannelNotificationJob;
use App\Models\AppNotification;
use App\Models\NotificationLog;
use App\Models\ParcelRequest;
use App\Models\User;
use Illuminate\Support\Arr;

class NotificationService
{
    public function __construct(
        private SmsService $smsService,
        private WhatsAppService $whatsAppService,
        private EmailNotificationService $emailNotificationService,
    ) {
    }

    public function send(User $user, ?ParcelRequest $parcelRequest, array $payload): AppNotification
    {
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'parcel_request_id' => $parcelRequest?->id,
            'title' => $payload['title'],
            'message' => $payload['message'],
            'badge' => $payload['badge'] ?? null,
            'tone' => $payload['tone'] ?? 'info',
            'event_type' => $payload['event_type'] ?? 'general',
            'meta' => $payload['meta'] ?? [],
        ]);

        NotificationLog::create([
            'user_id' => $user->id,
            'parcel_request_id' => $parcelRequest?->id,
            'channel' => 'app',
            'event_type' => $payload['event_type'] ?? 'general',
            'template_key' => $payload['template_key'] ?? null,
            'provider' => 'database',
            'recipient' => (string) $user->id,
            'subject' => $payload['title'],
            'message' => $payload['message'],
            'status' => 'sent',
            'meta' => $payload['meta'] ?? [],
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        $channels = array_values(array_unique(Arr::wrap($payload['channels'] ?? ['sms', 'whatsapp', 'email'])));

        foreach ($channels as $channel) {
            $this->dispatchChannel($channel, $user, $parcelRequest, $payload);
        }

        return $notification;
    }

    private function dispatchChannel(string $channel, User $user, ?ParcelRequest $parcelRequest, array $payload): void
    {
        $templateKey = $payload['template_key'] ?? null;
        $subject = $payload['email_subject'] ?? $payload['title'];
        $context = array_merge($payload['context'] ?? [], [
            'message' => $payload['message'],
            'tracking' => $payload['meta']['tracking_number'] ?? $payload['context']['tracking'] ?? null,
        ]);
        $common = [
            'event_type' => $payload['event_type'] ?? 'general',
            'preference_key' => $payload['preference_key'] ?? null,
            'parcel_request_id' => $parcelRequest?->id,
            'meta' => array_merge($payload['meta'] ?? [], ['title' => $payload['title']]),
            'template_key' => $templateKey,
            'subject' => $subject,
        ];

        if ($channel === 'sms') {
            if ($templateKey) {
                $this->smsService->queueTemplate($user, $templateKey, $context, $common);
            } else {
                $this->smsService->queueMessage($user, $payload['message'], $common);
            }
            return;
        }

        if ($channel === 'whatsapp') {
            $log = $templateKey
                ? $this->whatsAppService->queueTemplate($user, $templateKey, $context, $common)
                : $this->whatsAppService->queueMessage($user, $payload['message'], $common);

            if ($log && $log->status === 'queued') {
                SendChannelNotificationJob::dispatch($log->id)->afterCommit();
            }
            return;
        }

        if ($channel === 'email') {
            $log = $this->emailNotificationService->queueEvent(
                $user,
                $subject,
                $payload['title'],
                $payload['message'],
                $common
            );

            if ($log && $log->status === 'queued') {
                SendChannelNotificationJob::dispatch($log->id)->afterCommit();
            }
        }
    }
}
