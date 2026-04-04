<?php

namespace App\Providers;

use App\Contracts\SmsProviderInterface;
use App\Services\Sms\Providers\LogSmsProvider;
use App\Services\Sms\Providers\TwilioSmsProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(SmsProviderInterface::class, function ($app) {
            $providerKey = strtolower((string) config('sms.default', 'log'));

            return match ($providerKey) {
                'twilio' => $app->make(TwilioSmsProvider::class),
                'log' => $app->make(LogSmsProvider::class),
                default => tap($app->make(LogSmsProvider::class), function () use ($providerKey) {
                    Log::warning('Unknown SMS provider configured. Falling back to log provider.', [
                        'configured_provider' => $providerKey,
                    ]);
                }),
            };
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        Inertia::share('auth', function () {
            return [
                'user' => auth()->user() ? [
                    'id' => auth()->user()->id,
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                    'role' => auth()->user()->role,
                ] : null
            ];
        });
    }
}
