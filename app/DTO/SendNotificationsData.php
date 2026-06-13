<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\Channel;
use App\Enums\NotificationPriority;

final readonly class SendNotificationsData
{
    /**
     * @param  list<string>  $recipientIds
     */
    public function __construct(
        public string $idempotencyKey,
        public Channel $channel,
        public NotificationPriority $priority,
        public string $text,
        public array $recipientIds,
    ) {}
}
