<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Enums\Channel;
use Illuminate\Contracts\Container\Container;

class ProviderRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(Channel $channel): NotificationProviderInterface
    {
        return match ($channel) {
            Channel::Sms => $this->container->make(SmsProviderInterface::class),
            Channel::Email => $this->container->make(EmailProviderInterface::class),
        };
    }
}
