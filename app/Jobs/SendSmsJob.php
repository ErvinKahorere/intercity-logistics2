<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 90, 180];

    public function __construct(
        public int $smsLogId,
    ) {
        $this->onQueue(config('sms.queue', 'sms'));
    }

    public function handle(SmsService $smsService): void
    {
        $smsService->deliverQueuedMessage($this->smsLogId);
    }

    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(5, 300))->backoff(60),
        ];
    }

    public function failed(?Throwable $exception): void
    {
        app(SmsService::class)->markJobFailure($this->smsLogId, $exception?->getMessage() ?: 'Queue delivery failed.');
    }
}
