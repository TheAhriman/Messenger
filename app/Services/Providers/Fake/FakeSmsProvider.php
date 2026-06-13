<?php

declare(strict_types=1);

namespace App\Services\Providers\Fake;

use App\Enums\Channel;
use App\Services\Providers\SmsProviderInterface;

class FakeSmsProvider extends FakeProvider implements SmsProviderInterface
{
    protected function channel(): Channel
    {
        return Channel::Sms;
    }
}
