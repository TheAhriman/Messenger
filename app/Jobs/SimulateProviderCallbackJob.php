<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Services\DeliveryReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SimulateProviderCallbackJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $providerMessageId,
        private readonly NotificationStatus $status,
        private readonly ?string $reason = null,
    ) {}

    public function handle(DeliveryReportService $reports): void
    {
        $reports->apply($this->providerMessageId, $this->status, $this->reason);
    }
}
