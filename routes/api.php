<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DeliveryWebhookController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RecipientNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(static function (): void {
    Route::post('notifications', [NotificationController::class, 'store']);
    Route::get('recipients/{recipientId}/notifications', [RecipientNotificationController::class, 'index']);
    Route::post('webhooks/delivery', [DeliveryWebhookController::class, 'store']);
});
