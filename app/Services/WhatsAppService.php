<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppService
{
    public function queueTemplate(?User $user, string $templateKey, array $context = [], array $options = []): ?NotificationLog
    {
        if (! $user) {
            return null;
        }

        $message = app(SmsTemplateService::class)->render($templateKey, $context);

        return $this->queueMessage($user, $message, array_merge($options, [
            'template_key' => $templateKey,
            'event_type' => Arr::get($options, 'event_type', $templateKey),
        ]));
    }

    public function queueMessage(?User $user, string $message, array $options = []): ?NotificationLog
    {
        if (! $user) {
            return null;
        }

        $phone = Arr::get($options, 'phone') ?: $user->phone_e164 ?: $user->phone;
        $normalizedPhone = app(PhoneNumberService::class)->safelyNormalize($phone);
        $enabled = $this->preferenceState($user, Arr::get($options, 'preference_key'));

        if (! $enabled['enabled']) {
            return $this->createSkippedLog($user, $message, array_merge($options, ['reason' => $enabled['reason']]), $normalizedPhone ?: $phone);
        }

        if (! $normalizedPhone) {
            return $this->createSkippedLog($user, $message, array_merge($options, ['reason' => 'invalid_phone']), $phone);
        }

        if (! $this->isAvailable()) {
            return $this->createSkippedLog($user, $message, array_merge($options, ['reason' => 'provider_unavailable']), $normalizedPhone);
        }

        return NotificationLog::create([
            'user_id' => $user->id,
            'parcel_request_id' => Arr::get($options, 'parcel_request_id'),
            'channel' => 'whatsapp',
            'event_type' => Arr::get($options, 'event_type', 'general'),
            'template_key' => Arr::get($options, 'template_key'),
            'provider' => 'twilio_whatsapp',
            'recipient' => $normalizedPhone,
            'subject' => Arr::get($options, 'subject', 'InterCity Logistics update'),
            'message' => $message,
            'status' => 'queued',
            'meta' => Arr::get($options, 'meta'),
            'queued_at' => now(),
        ]);
    }

    public function deliverLog(NotificationLog $log): NotificationLog
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('WhatsApp provider is not configured.');
        }

        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from = (string) config('services.twilio.whatsapp_from');

        $payload = array_filter([
            'To' => 'whatsapp:' . $log->recipient,
            'Body' => $log->message,
            'From' => $from,
            'StatusCallback' => config('services.twilio.whatsapp_status_callback'),
        ]);

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->retry(2, 250)
                ->withBasicAuth($sid, $token)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload)
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Twilio WhatsApp request timed out or could not connect.', previous: $exception);
        } catch (RequestException $exception) {
            throw new RuntimeException($exception->response?->json('message') ?: $exception->getMessage(), previous: $exception);
        }

        $json = $response->json();
        $status = in_array($json['status'] ?? 'queued', ['sent', 'delivered'], true) ? 'sent' : 'queued';

        $log->forceFill([
            'status' => $status,
            'provider_message_id' => $json['sid'] ?? null,
            'provider_response' => $json,
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        return $log->fresh();
    }

    public function isAvailable(): bool
    {
        return (bool) config('services.twilio.whatsapp_enabled', false)
            && filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && filled(config('services.twilio.whatsapp_from'));
    }

    private function preferenceState(User $user, ?string $preferenceKey = null): array
    {
        if (! $user->whatsapp_notifications_enabled) {
            return ['enabled' => false, 'reason' => 'whatsapp_disabled'];
        }

        if (! $preferenceKey) {
            return ['enabled' => true, 'reason' => null];
        }

        $preferences = array_merge(config('sms.preferences', []), is_array($user->whatsapp_notification_preferences) ? $user->whatsapp_notification_preferences : []);

        return [
            'enabled' => (bool) ($preferences[$preferenceKey] ?? true),
            'reason' => ($preferences[$preferenceKey] ?? true) ? null : 'preference_disabled',
        ];
    }

    private function createSkippedLog(User $user, string $message, array $options, ?string $recipient = null): NotificationLog
    {
        return NotificationLog::create([
            'user_id' => $user->id,
            'parcel_request_id' => Arr::get($options, 'parcel_request_id'),
            'channel' => 'whatsapp',
            'event_type' => Arr::get($options, 'event_type', 'general'),
            'template_key' => Arr::get($options, 'template_key'),
            'provider' => 'twilio_whatsapp',
            'recipient' => $recipient,
            'subject' => Arr::get($options, 'subject', 'InterCity Logistics update'),
            'message' => $message,
            'status' => 'skipped',
            'meta' => array_merge(Arr::get($options, 'meta', []), ['reason' => Arr::get($options, 'reason')]),
            'queued_at' => now(),
        ]);
    }
}
