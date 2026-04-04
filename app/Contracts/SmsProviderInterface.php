<?php

namespace App\Contracts;

interface SmsProviderInterface
{
    public function isAvailable(): bool;

    public function providerName(): string;

    public function send(string $phoneNumber, string $message, array $options = []): array;

    public function sendBulk(array $messages, array $options = []): array;
}
