<?php

namespace App\Providers;

use App\Contracts\SmsProviderInterface;
use App\Services\Sms\Providers\LogSmsProvider;
use App\Services\Sms\Providers\TwilioSmsProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsProviderInterface::class, function ($app) {
            $providerKey = strtolower((string) config('sms.default', 'log'));

            return match ($providerKey) {
                'twilio' => $app->make(TwilioSmsProvider::class),

                'log' => $app->make(LogSmsProvider::class),

                default => tap(
                    $app->make(LogSmsProvider::class),
                    function () use ($providerKey) {
                        Log::warning('Unknown SMS provider configured. Falling back to log provider.', [
                            'configured_provider' => $providerKey,
                        ]);
                    }
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Intentionally left empty.
        // Do NOT put Inertia::share or request-based logic here.
        // Keep this worker-safe for FrankenPHP.
    }
}
