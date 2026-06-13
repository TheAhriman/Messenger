<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotificationBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin NotificationBatch
 */
#[OA\Schema(
    schema: 'NotificationBatch',
    description: 'Принятая массовая рассылка',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email']),
        new OA\Property(property: 'priority', type: 'string', enum: ['transactional', 'marketing']),
        new OA\Property(property: 'text', type: 'string'),
        new OA\Property(property: 'notifications_count', type: 'integer', description: 'Количество получателей в рассылке'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
class NotificationBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->label(),
            'priority' => $this->priority->label(),
            'text' => $this->text,
            'notifications_count' => $this->whenCounted('notifications'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
