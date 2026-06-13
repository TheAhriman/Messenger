<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Providers\EmailProviderInterface;
use App\Services\Providers\Fake\FakeEmailProvider;
use App\Services\Providers\Fake\FakeSmsProvider;
use App\Services\Providers\SmsProviderInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsProviderInterface::class, FakeSmsProvider::class);
        $this->app->singleton(EmailProviderInterface::class, FakeEmailProvider::class);
    }

    public function boot(): void
    {
        RateLimiter::for(
            'provider-gateway',
            static fn (): Limit => Limit::perSecond(config()->integer('notifications.rate_limit_per_second')),
        );
    }
}
