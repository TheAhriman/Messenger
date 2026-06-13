<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Имена очередей по приоритетам
    |--------------------------------------------------------------------------
    |
    | Транзакционный трафик идёт в высокоприоритетную очередь с выделенным
    | воркером и никогда не блокируется маркетинговыми рассылками.
    |
    */

    'queues' => [
        'high' => env('NOTIFICATIONS_QUEUE_HIGH', 'notifications_high'),
        'low' => env('NOTIFICATIONS_QUEUE_LOW', 'notifications_low'),
    ],

    // Сколько секунд Idempotency-Key хранится в Redis.
    'idempotency_ttl' => (int) env('IDEMPOTENCY_TTL', 86400),

    // Максимум вызовов шлюза в секунду (общий лимит воркеров через Redis RateLimiter).
    'rate_limit_per_second' => (int) env('PROVIDER_RATE_LIMIT_PER_SECOND', 50),

    // Задержка перед отчётом мок-провайдера о статусе доставки (в секундах).
    'delivery_callback_delay' => (int) env('DELIVERY_CALLBACK_DELAY_SECONDS', 2),

];
