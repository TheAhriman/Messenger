<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum NotificationStatus: int
{
    use HasLabel;

    case Queued = 1;
    case Sent = 2;
    case Delivered = 3;
    case Failed = 4;

    /**
     * @return list<self>
     */
    public static function finalStatuses(): array
    {
        return [self::Delivered, self::Failed];
    }

    public function isFinal(): bool
    {
        return in_array($this, self::finalStatuses(), true);
    }
}
