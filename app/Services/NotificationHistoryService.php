<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\NotificationHistoryFilters;
use App\Models\NotificationMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class NotificationHistoryService
{
    /**
     * @return LengthAwarePaginator<int, NotificationMessage>
     */
    public function forRecipient(string $recipientId, NotificationHistoryFilters $filters): LengthAwarePaginator
    {
        return NotificationMessage::query()
            ->with('batch')
            ->where('recipient_id', $recipientId)
            ->when(
                $filters->status !== null,
                static fn (Builder $query) => $query->where('status', $filters->status),
            )
            ->when(
                $filters->channel !== null,
                static fn (Builder $query) => $query->whereHas(
                    'batch',
                    static fn (Builder $batch) => $batch->where('channel', $filters->channel),
                ),
            )
            ->latest('created_at')
            ->paginate($filters->perPage);
    }
}
