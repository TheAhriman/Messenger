<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryReportRequest;
use App\Services\DeliveryReportService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DeliveryWebhookController extends Controller
{
    #[OA\Post(
        path: '/webhooks/delivery',
        operationId: 'deliveryWebhook',
        summary: 'Webhook отчёта о доставке от провайдера (DLR)',
        description: 'Идемпотентен: допускается только переход sent → delivered/failed, повторные и устаревшие отчёты игнорируются.',
        tags: ['Webhooks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['provider_message_id', 'status'],
                properties: [
                    new OA\Property(property: 'provider_message_id', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['delivered', 'failed']),
                    new OA\Property(property: 'reason', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Отчёт обработан; applied=false, если уведомление не найдено или уже финализировано',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'applied', type: 'boolean'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ],
    )]
    public function store(DeliveryReportRequest $request, DeliveryReportService $reports): JsonResponse
    {
        $applied = $reports->apply(
            (string) $request->validated('provider_message_id'),
            $request->status(),
            $request->validated('reason'),
        );

        return new JsonResponse(['applied' => $applied]);
    }
}
