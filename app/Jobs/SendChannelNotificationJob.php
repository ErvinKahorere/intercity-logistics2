<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\EmailNotificationService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendChannelNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationLogId)
    {
        $this->onQueue('notifications');
    }

    public function handle(WhatsAppService $whatsAppService, EmailNotificationService $emailNotificationService): void
    {
        $log = NotificationLog::query()->findOrFail($this->notificationLogId);

        if (! in_array($log->status, ['queued', 'failed'], true)) {
            return;
        }

        match ($log->channel) {
            'whatsapp' => $whatsAppService->deliverLog($log),
            'email' => $emailNotificationService->deliverLog($log),
            default => null,
        };
    }

    public function failed(?Throwable $exception): void
    {
        $log = NotificationLog::query()->find($this->notificationLogId);
        if (! $log) {
            return;
        }

        $log->forceFill([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $exception?->getMessage() ?: 'Notification job failed.',
        ])->save();
    }
}
