<?php

declare(strict_types=1);

namespace App\Services\Providers\Fake;

use App\Enums\Channel;
use App\Services\Providers\EmailProviderInterface;

class FakeEmailProvider extends FakeProvider implements EmailProviderInterface
{
    protected function channel(): Channel
    {
        return Channel::Email;
    }
}
