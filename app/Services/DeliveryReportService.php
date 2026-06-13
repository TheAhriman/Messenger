<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Models\NotificationMessage;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DeliveryReportService
{
    public function apply(string $providerMessageId, NotificationStatus $status, ?string $reason = null): bool
    {
        $attributes = match ($status) {
            NotificationStatus::Delivered => [
                'status' => $status,
                'delivered_at' => now(),
            ],
            NotificationStatus::Failed => [
                'status' => $status,
                'failed_at' => now(),
                'error_message' => $reason,
            ],
            default => throw new InvalidArgumentException(
                "Delivery reports may only carry final statuses, [{$status->label()}] given",
            ),
        };

        $updated = NotificationMessage::query()
            ->where('provider_message_id', $providerMessageId)
            ->where('status', NotificationStatus::Sent)
            ->update($attributes);

        if ($updated === 0) {
            Log::warning('Delivery report did not match any sent notification', [
                'provider_message_id' => $providerMessageId,
                'status' => $status->label(),
            ]);
        }

        return $updated > 0;
    }
}
