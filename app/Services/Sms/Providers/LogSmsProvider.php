<?php

namespace App\Services\Sms\Providers;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsProviderInterface
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function providerName(): string
    {
        return 'log';
    }

    public function send(string $phoneNumber, string $message, array $options = []): array
    {
        $payload = [
            'provider' => 'log',
            'to' => $phoneNumber,
            'message' => $message,
            'options' => $options,
        ];

        Log::channel(config('sms.providers.log.channel', config('sms.log_channel')))->info('SMS notification', $payload);

        return [
            'status' => 'sent',
            'provider' => 'log',
            'message_id' => 'log-' . now()->format('YmdHisv'),
            'response' => $payload,
        ];
    }

    public function sendBulk(array $messages, array $options = []): array
    {
        return collect($messages)
            ->map(fn (array $message) => $this->send($message['to'], $message['message'], array_merge($options, $message['options'] ?? [])))
            ->all();
    }
}
