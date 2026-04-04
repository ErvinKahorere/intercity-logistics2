<?php

namespace App\Services;

use InvalidArgumentException;

class PhoneNumberService
{
    public function __construct(
        private ?string $defaultCountryCode = null,
    ) {
    }

    public function normalize(?string $phoneNumber, ?string $countryCode = null): string
    {
        $raw = trim((string) $phoneNumber);

        if ($raw === '') {
            throw new InvalidArgumentException('A phone number is required for SMS notifications.');
        }

        $raw = preg_replace('/(?:ext\.?|x)\s*\d+$/i', '', $raw) ?? $raw;
        $sanitized = preg_replace('/(?!^\+)[^\d]/', '', $raw) ?? '';
        $defaultCountryCode = ltrim((string) ($countryCode ?: $this->defaultCountryCode ?: config('sms.default_country_code', '264')), '+');

        if (str_starts_with($sanitized, '0+') || substr_count($sanitized, '+') > 1 || (str_contains($sanitized, '+') && ! str_starts_with($sanitized, '+'))) {
            throw new InvalidArgumentException('Invalid phone number format for SMS notifications.');
        }

        if (str_starts_with($sanitized, '00')) {
            $sanitized = '+' . substr($sanitized, 2);
        } elseif (preg_match('/^' . preg_quote($defaultCountryCode, '/') . '\d{7,12}$/', $sanitized)) {
            $sanitized = '+' . $sanitized;
        } elseif (! str_starts_with($sanitized, '+')) {
            $sanitized = ltrim($sanitized, '0');
            $sanitized = '+' . $defaultCountryCode . $sanitized;
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $sanitized)) {
            throw new InvalidArgumentException('Invalid phone number format for SMS notifications.');
        }

        return $sanitized;
    }

    public function isValid(?string $phoneNumber, ?string $countryCode = null): bool
    {
        try {
            $this->normalize($phoneNumber, $countryCode);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function safelyNormalize(?string $phoneNumber, ?string $countryCode = null): ?string
    {
        try {
            return $this->normalize($phoneNumber, $countryCode);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
