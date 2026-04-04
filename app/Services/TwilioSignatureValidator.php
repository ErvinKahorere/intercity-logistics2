<?php

namespace App\Services;

use Illuminate\Http\Request;

class TwilioSignatureValidator
{
    public function isValid(Request $request): bool
    {
        if (! config('sms.providers.twilio.validate_signature', true)) {
            return true;
        }

        $signature = (string) $request->header('X-Twilio-Signature', '');
        $token = (string) config('sms.providers.twilio.token', '');

        if ($signature === '' || $token === '') {
            return false;
        }

        foreach ($this->candidateUrls($request) as $url) {
            $payload = $url;
            $params = $request->post();
            ksort($params);

            foreach ($params as $key => $value) {
                $payload .= $key . $value;
            }

            $expected = base64_encode(hash_hmac('sha1', $payload, $token, true));
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function candidateUrls(Request $request): array
    {
        $configured = (string) config('sms.providers.twilio.status_callback', '');
        $urls = array_filter(array_unique([
            $configured,
            $request->fullUrl(),
            $request->url(),
            preg_replace('/^http:/i', 'https:', $request->fullUrl()),
            preg_replace('/^https:/i', 'http:', $request->fullUrl()),
            preg_replace('/^http:/i', 'https:', $request->url()),
            preg_replace('/^https:/i', 'http:', $request->url()),
        ]));

        return array_values($urls);
    }
}
