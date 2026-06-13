<?php

declare(strict_types=1);

namespace App\DTO;

use App\Models\NotificationBatch;

final readonly class BatchCreationResult
{
    public function __construct(
        public NotificationBatch $batch,
        public bool $wasDuplicate,
    ) {}
}
