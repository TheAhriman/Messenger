<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum NotificationPriority: int
{
    use HasLabel;

    case Transactional = 1;
    case Marketing = 2;

    public function queue(): string
    {
        return match ($this) {
            self::Transactional => config('notifications.queues.high'),
            self::Marketing => config('notifications.queues.low'),
        };
    }
}
