<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Exceptions\ProviderRejectedException;
use App\Exceptions\ProviderTemporarilyUnavailableException;
use App\Models\NotificationMessage;

interface NotificationProviderInterface
{
    /**
     * @throws ProviderRejectedException постоянная ошибка, не повторять
     * @throws ProviderTemporarilyUnavailableException временный сбой, повторить с задержкой
     */
    public function send(NotificationMessage $notification): ProviderResponse;
}
