<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRecipientNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationHistoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class RecipientNotificationController extends Controller
{
    #[OA\Get(
        path: '/recipients/{recipientId}/notifications',
        operationId: 'recipientNotifications',
        summary: 'История и статусы уведомлений подписчика',
        tags: ['Notifications'],
        parameters: [
            new OA\PathParameter(name: 'recipientId', required: true, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'status', required: false, schema: new OA\Schema(type: 'string', enum: ['queued', 'sent', 'delivered', 'failed'])),
            new OA\QueryParameter(name: 'channel', required: false, schema: new OA\Schema(type: 'string', enum: ['sms', 'email'])),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1)),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Страница истории уведомлений, новые первыми',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/NotificationMessage')),
                    new OA\Property(property: 'links', type: 'object'),
                    new OA\Property(property: 'meta', type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ],
    )]
    public function index(
        ListRecipientNotificationsRequest $request,
        NotificationHistoryService $history,
        string $recipientId,
    ): AnonymousResourceCollection {
        return NotificationResource::collection(
            $history->forRecipient($recipientId, $request->toFilters()),
        );
    }
}
