<?php

namespace App\Services\Sms\Providers;

use App\Contracts\SmsProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioSmsProvider implements SmsProviderInterface
{
    public function isAvailable(): bool
    {
        $sid = (string) config('sms.providers.twilio.sid');
        $token = (string) config('sms.providers.twilio.token');
        $from = (string) config('sms.providers.twilio.from');
        $messagingServiceSid = (string) config('sms.providers.twilio.messaging_service_sid');

        return (bool) config('sms.providers.twilio.enabled', false)
            && filled($sid)
            && filled($token)
            && (filled($from) || filled($messagingServiceSid));
    }

    public function providerName(): string
    {
        return 'twilio';
    }

    public function send(string $phoneNumber, string $message, array $options = []): array
    {
        if (! config('sms.providers.twilio.enabled', false)) {
            throw new RuntimeException('Twilio trial SMS is disabled.');
        }

        $sid = (string) config('sms.providers.twilio.sid');
        $token = (string) config('sms.providers.twilio.token');
        $from = $options['from'] ?? config('sms.providers.twilio.from');
        $messagingServiceSid = $options['messaging_service_sid'] ?? config('sms.providers.twilio.messaging_service_sid');
        $statusCallback = $options['status_callback'] ?? config('sms.providers.twilio.status_callback');

        if (blank($sid) || blank($token) || (blank($from) && blank($messagingServiceSid))) {
            throw new RuntimeException('Twilio SMS provider is not configured.');
        }

        $payload = array_filter([
            'To' => $phoneNumber,
            'Body' => $message,
            'From' => $from,
            'MessagingServiceSid' => $messagingServiceSid,
            'StatusCallback' => $statusCallback,
        ]);

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->retry(2, 250)
                ->withBasicAuth($sid, $token)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload)
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Twilio SMS request timed out or could not connect.', previous: $exception);
        } catch (RequestException $exception) {
            throw new RuntimeException($exception->response?->json('message') ?: $exception->getMessage(), previous: $exception);
        }

        $json = $response->json();

        return [
            'status' => $json['status'] ?? 'queued',
            'provider' => 'twilio',
            'message_id' => $json['sid'] ?? null,
            'response' => $json,
        ];
    }

    public function sendBulk(array $messages, array $options = []): array
    {
        return collect($messages)
            ->map(fn (array $message) => $this->send($message['to'], $message['message'], array_merge($options, $message['options'] ?? [])))
            ->all();
    }
}
