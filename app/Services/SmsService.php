<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use App\Jobs\SendSmsJob;
use App\Models\SmsNotificationLog;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SmsService
{
    public function __construct(
        private SmsProviderInterface $provider,
        private SmsTemplateService $templateService,
        private PhoneNumberService $phoneNumberService,
    ) {
    }

    public function queueTemplate(?User $user, string $templateKey, array $context = [], array $options = []): ?SmsNotificationLog
    {
        if (! $user) {
            return null;
        }

        $preferenceState = $this->preferenceState($user, Arr::get($options, 'preference_key'));
        $message = $this->templateService->render($templateKey, $context);

        if (! $preferenceState['enabled']) {
            return $this->createSkippedLog($user, [
                'template_key' => $templateKey,
                'event_type' => Arr::get($options, 'event_type', $templateKey),
                'parcel_request_id' => Arr::get($options, 'parcel_request_id'),
                'meta' => array_merge(Arr::get($options, 'meta', []), ['reason' => $preferenceState['reason']]),
            ], $message);
        }

        return $this->queueMessage(
            $user,
            $message,
            array_merge($options, [
                'template_key' => $templateKey,
                'event_type' => Arr::get($options, 'event_type', $templateKey),
            ])
        );
    }

    public function queueMessage(?User $user, string $message, array $options = []): ?SmsNotificationLog
    {
        if (! $user) {
            return null;
        }

        $preferenceState = $this->preferenceState($user, Arr::get($options, 'preference_key'));
        if (! $preferenceState['enabled']) {
            return $this->createSkippedLog($user, [
                ...$options,
                'meta' => array_merge(Arr::get($options, 'meta', []), ['reason' => $preferenceState['reason']]),
            ], $message);
        }

        if (! $this->provider->isAvailable()) {
            Log::warning('SMS provider is unavailable. Skipping SMS dispatch.', [
                'provider' => $this->provider->providerName(),
                'user_id' => $user->id,
                'event_type' => Arr::get($options, 'event_type', 'general'),
            ]);

            return $this->createSkippedLog($user, [
                ...$options,
                'meta' => array_merge(Arr::get($options, 'meta', []), ['reason' => 'provider_unavailable']),
            ], $message);
        }

        $phoneNumber = Arr::get($options, 'phone') ?: $user->phone_e164 ?: $user->phone;
        $normalizedPhone = $this->phoneNumberService->safelyNormalize($phoneNumber);

        if (! $normalizedPhone) {
            return $this->createSkippedLog($user, [
                ...$options,
                'meta' => array_merge(Arr::get($options, 'meta', []), ['reason' => 'invalid_phone']),
            ], $message, $phoneNumber);
        }

        if (blank($user->phone_e164)) {
            $user->forceFill(['phone_e164' => $normalizedPhone])->save();
        }

        if ($this->isDuplicate($normalizedPhone, (string) Arr::get($options, 'event_type', 'general'), Arr::get($options, 'parcel_request_id'))) {
            return $this->createSkippedLog($user, [
                ...$options,
                'meta' => array_merge(Arr::get($options, 'meta', []), ['reason' => 'cooldown']),
                'normalized_phone' => $normalizedPhone,
            ], $message, $phoneNumber);
        }

        $log = SmsNotificationLog::create([
            'user_id' => $user->id,
            'parcel_request_id' => Arr::get($options, 'parcel_request_id'),
            'event_type' => Arr::get($options, 'event_type', 'general'),
            'template_key' => Arr::get($options, 'template_key'),
            'provider' => $this->provider->providerName(),
            'recipient_name' => $user->name,
            'recipient_phone' => $phoneNumber,
            'normalized_phone' => $normalizedPhone,
            'message' => $message,
            'status' => 'queued',
            'meta' => Arr::get($options, 'meta'),
            'queued_at' => now(),
        ]);

        SendSmsJob::dispatch($log->id)->afterCommit();

        return $log;
    }

    public function deliverQueuedMessage(int $smsLogId): SmsNotificationLog
    {
        $log = SmsNotificationLog::query()->findOrFail($smsLogId);

        if (! in_array($log->status, ['queued', 'failed'], true)) {
            return $log;
        }

        try {
            $response = $this->provider->send($log->normalized_phone, $log->message, [
                'sender_id' => config('sms.sender_id'),
                'from' => config('sms.providers.twilio.from'),
                'messaging_service_sid' => config('sms.providers.twilio.messaging_service_sid'),
                'status_callback' => config('sms.providers.twilio.status_callback'),
            ]);

            $normalizedStatus = $this->normalizeProviderStatus($response['status'] ?? 'sent');
            $nextStatus = $this->preferStatus($log->status, $normalizedStatus);

            $log->forceFill([
                'status' => $nextStatus,
                'provider' => $response['provider'] ?? config('sms.default', 'log'),
                'provider_message_id' => $response['message_id'] ?? null,
                'provider_response' => array_merge($log->provider_response ?? [], [
                    'send' => $response['response'] ?? $response,
                    'last_status' => $normalizedStatus,
                ]),
                'attempts' => (int) $log->attempts + 1,
                'queued_at' => $log->queued_at ?: now(),
                'sent_at' => in_array($nextStatus, ['sent', 'delivered'], true) ? ($log->sent_at ?: now()) : $log->sent_at,
                'failed_at' => in_array($nextStatus, ['failed', 'undelivered'], true) ? now() : null,
                'error_message' => in_array($nextStatus, ['failed', 'undelivered'], true) ? ($response['error_message'] ?? $log->error_message) : null,
            ])->save();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => $this->preferStatus($log->status, 'failed'),
                'attempts' => (int) $log->attempts + 1,
                'error_message' => $exception->getMessage(),
                'failed_at' => now(),
                'provider_response' => array_merge($log->provider_response ?? [], [
                    'failure' => [
                        'message' => $exception->getMessage(),
                        'time' => now()->toIso8601String(),
                    ],
                ]),
            ])->save();

            Log::warning('SMS delivery failed', [
                'sms_log_id' => $log->id,
                'recipient' => $log->normalized_phone,
                'event_type' => $log->event_type,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }

        return $log->fresh();
    }

    public function markJobFailure(int $smsLogId, string $message): void
    {
        $log = SmsNotificationLog::query()->find($smsLogId);
        if (! $log) {
            return;
        }

        $log->forceFill([
            'status' => $this->preferStatus($log->status, 'failed'),
            'failed_at' => now(),
            'error_message' => $message,
            'provider_response' => array_merge($log->provider_response ?? [], [
                'job_failure' => [
                    'message' => $message,
                    'time' => now()->toIso8601String(),
                ],
            ]),
        ])->save();
    }

    public function canSendToUser(User $user, ?string $preferenceKey = null): bool
    {
        return $this->preferenceState($user, $preferenceKey)['enabled'];
    }

    private function isDuplicate(string $normalizedPhone, string $eventType, ?int $parcelRequestId = null): bool
    {
        $cooldown = max(0, (int) config('sms.cooldown_seconds', 120));
        if ($cooldown === 0) {
            return false;
        }

        return SmsNotificationLog::query()
            ->where('normalized_phone', $normalizedPhone)
            ->where('event_type', $eventType)
            ->when($parcelRequestId, fn ($query) => $query->where('parcel_request_id', $parcelRequestId))
            ->whereIn('status', ['queued', 'sent', 'delivered'])
            ->where('created_at', '>=', now()->subSeconds($cooldown))
            ->exists();
    }

    private function preferenceState(User $user, ?string $preferenceKey = null): array
    {
        if (! $user->sms_notifications_enabled) {
            return ['enabled' => false, 'reason' => 'sms_disabled'];
        }

        if (! $preferenceKey) {
            return ['enabled' => true, 'reason' => null];
        }

        $preferences = array_merge(
            config('sms.preferences', []),
            is_array($user->sms_notification_preferences) ? $user->sms_notification_preferences : []
        );

        return [
            'enabled' => (bool) ($preferences[$preferenceKey] ?? true),
            'reason' => ($preferences[$preferenceKey] ?? true) ? null : 'preference_disabled',
        ];
    }

    private function createSkippedLog(User $user, array $options, string $message, ?string $phoneNumber = null): SmsNotificationLog
    {
        return SmsNotificationLog::create([
            'user_id' => $user->id,
            'parcel_request_id' => Arr::get($options, 'parcel_request_id'),
            'event_type' => Arr::get($options, 'event_type', 'general'),
            'template_key' => Arr::get($options, 'template_key'),
            'provider' => $this->provider->providerName(),
            'recipient_name' => $user->name,
            'recipient_phone' => $phoneNumber ?: ($user->phone_e164 ?: $user->phone),
            'normalized_phone' => Arr::get($options, 'normalized_phone'),
            'message' => $message,
            'status' => 'skipped',
            'meta' => Arr::get($options, 'meta'),
            'queued_at' => now(),
        ]);
    }

    private function normalizeProviderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'accepted', 'queued', 'sending' => 'queued',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'undelivered' => 'undelivered',
            'failed' => 'failed',
            default => strtolower($status),
        };
    }

    private function preferStatus(?string $currentStatus, string $incomingStatus): string
    {
        if (! $currentStatus) {
            return $incomingStatus;
        }

        return $this->statusWeight($incomingStatus) >= $this->statusWeight($currentStatus)
            ? $incomingStatus
            : $currentStatus;
    }

    private function statusWeight(string $status): int
    {
        return match ($status) {
            'queued' => 10,
            'sent' => 20,
            'failed', 'undelivered' => 30,
            'delivered' => 40,
            default => 0,
        };
    }
}
