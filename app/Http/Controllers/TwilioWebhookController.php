<?php

namespace App\Http\Controllers;

use App\Models\SmsNotificationLog;
use App\Services\TwilioSignatureValidator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwilioWebhookController extends Controller
{
    public function __construct(
        private TwilioSignatureValidator $signatureValidator,
    ) {
    }

    public function status(Request $request): Response
    {
        abort_unless($this->signatureValidator->isValid($request), 403);

        $messageSid = (string) $request->input('MessageSid');
        if ($messageSid === '') {
            return response('', 202);
        }

        $status = (string) $request->input('MessageStatus', 'sent');
        $normalizedStatus = $this->normalizeStatus($status);

        $log = SmsNotificationLog::query()
            ->where('provider', 'twilio')
            ->where('provider_message_id', $messageSid)
            ->first();

        if (! $log) {
            return response('', 202);
        }

        $providerResponse = $log->provider_response ?? [];
        $callbackHistory = is_array($providerResponse['callback_history'] ?? null) ? $providerResponse['callback_history'] : [];
        array_unshift($callbackHistory, [
            'status' => $normalizedStatus,
            'error_code' => $request->input('ErrorCode'),
            'error_message' => $request->input('ErrorMessage'),
            'time' => now()->toIso8601String(),
        ]);

        $nextStatus = $this->preferStatus($log->status, $normalizedStatus);

        $log->forceFill([
            'status' => $nextStatus,
            'provider_response' => array_merge($providerResponse, [
                'callback' => $request->all(),
                'callback_history' => array_slice($callbackHistory, 0, 10),
                'last_status' => $normalizedStatus,
            ]),
            'error_message' => $request->input('ErrorMessage') ?: $log->error_message,
            'sent_at' => in_array($nextStatus, ['sent', 'delivered'], true) && ! $log->sent_at ? now() : $log->sent_at,
            'failed_at' => in_array($nextStatus, ['failed', 'undelivered'], true) ? now() : $log->failed_at,
        ])->save();

        return response('', 204);
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'accepted', 'queued', 'sending' => 'queued',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'failed' => 'failed',
            'undelivered' => 'undelivered',
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
