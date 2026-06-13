<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\Channel;
use App\Enums\NotificationStatus;

final readonly class NotificationHistoryFilters
{
    public function __construct(
        public ?NotificationStatus $status = null,
        public ?Channel $channel = null,
        public int $perPage = 15,
    ) {}
}
