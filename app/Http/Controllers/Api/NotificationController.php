<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendNotificationsRequest;
use App\Http\Resources\NotificationBatchResource;
use App\Services\NotificationBatchService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    #[OA\Post(
        path: '/notifications',
        operationId: 'sendNotifications',
        summary: 'Запустить массовую рассылку SMS или Email',
        description: 'Принимает канал, текст и массив идентификаторов получателей. '
            .'Транзакционные уведомления уходят в выделенную очередь и обрабатываются без ожидания маркетинговых. '
            .'Повторный запрос с тем же Idempotency-Key не создаёт новую рассылку.',
        tags: ['Notifications'],
        parameters: [
            new OA\HeaderParameter(
                name: 'Idempotency-Key',
                description: 'Ключ идемпотентности запроса (любая уникальная строка, например UUID)',
                required: true,
                schema: new OA\Schema(type: 'string', maxLength: 255),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'text', 'recipient_ids'],
                properties: [
                    new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email']),
                    new OA\Property(property: 'priority', type: 'string', enum: ['transactional', 'marketing'], default: 'marketing'),
                    new OA\Property(property: 'text', type: 'string', maxLength: 5000),
                    new OA\Property(
                        property: 'recipient_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        minItems: 1,
                        maxItems: 10000,
                        example: ['42', '108', 'fail-1'],
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Рассылка принята и поставлена в очередь',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/NotificationBatch'),
                    new OA\Property(property: 'duplicate', type: 'boolean', example: false),
                ]),
            ),
            new OA\Response(
                response: 200,
                description: 'Дубликат: рассылка с этим Idempotency-Key уже принята ранее',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/NotificationBatch'),
                    new OA\Property(property: 'duplicate', type: 'boolean', example: true),
                ]),
            ),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ],
    )]
    public function store(SendNotificationsRequest $request, NotificationBatchService $batches): JsonResponse
    {
        $result = $batches->send($request->toData());

        $result->batch->loadCount('notifications');

        return NotificationBatchResource::make($result->batch)
            ->additional(['duplicate' => $result->wasDuplicate])
            ->response()
            ->setStatusCode($result->wasDuplicate ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
    }
}
