<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotificationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin NotificationMessage
 */
#[OA\Schema(
    schema: 'NotificationMessage',
    description: 'Уведомление конкретному получателю и его статус доставки',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'batch_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'recipient_id', type: 'string'),
        new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email']),
        new OA\Property(property: 'priority', type: 'string', enum: ['transactional', 'marketing']),
        new OA\Property(property: 'text', type: 'string'),
        new OA\Property(
            property: 'status',
            description: 'queued — в очереди; sent — передано шлюзу; delivered — доставка подтверждена; failed — отброшено',
            type: 'string',
            enum: ['queued', 'sent', 'delivered', 'failed'],
        ),
        new OA\Property(property: 'attempts', type: 'integer'),
        new OA\Property(property: 'provider_message_id', type: 'string', nullable: true),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        new OA\Property(property: 'queued_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'failed_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'recipient_id' => $this->recipient_id,
            'channel' => $this->whenLoaded('batch', fn (): string => $this->batch->channel->label()),
            'priority' => $this->whenLoaded('batch', fn (): string => $this->batch->priority->label()),
            'text' => $this->whenLoaded('batch', fn (): string => $this->batch->text),
            'status' => $this->status->label(),
            'attempts' => $this->attempts,
            'provider_message_id' => $this->provider_message_id,
            'error_message' => $this->error_message,
            'queued_at' => $this->created_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
        ];
    }
}
