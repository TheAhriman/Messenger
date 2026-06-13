<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Notification Service API',
    description: 'Микросервис массовой рассылки SMS/Email-уведомлений: приоритезация трафика, '
        .'идемпотентность запросов, статусы доставки и webhook для отчётов провайдера.',
)]
#[OA\Server(url: '/api/v1', description: 'API v1')]
abstract class Controller {}
