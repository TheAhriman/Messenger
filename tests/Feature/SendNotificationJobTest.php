<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Exceptions\ProviderTemporarilyUnavailableException;
use App\Jobs\SendNotificationJob;
use App\Jobs\SimulateProviderCallbackJob;
use App\Models\NotificationBatch;
use App\Models\NotificationMessage;
use App\Services\DeliveryReportService;
use App\Services\Providers\ProviderRegistry;
use App\Services\Providers\ProviderResponse;
use App\Services\Providers\SmsProviderInterface;
use Illuminate\Support\Facades\Queue;

function makeNotification(string $recipientId = '42'): NotificationMessage
{
    return NotificationMessage::factory()
        ->for(NotificationBatch::factory()->state(['channel' => Channel::Sms]), 'batch')
        ->create(['recipient_id' => $recipientId]);
}

it('walks the full chain from queued to delivered', function (): void {
    Queue::fake([SimulateProviderCallbackJob::class]);
    $notification = makeNotification();

    SendNotificationJob::dispatchSync($notification->id);

    $notification->refresh();
    expect($notification->status)->toBe(NotificationStatus::Sent)
        ->and($notification->provider_message_id)->not->toBeNull()
        ->and($notification->sent_at)->not->toBeNull();

    Queue::pushed(SimulateProviderCallbackJob::class)
        ->each(static fn (SimulateProviderCallbackJob $job) => $job->handle(app(DeliveryReportService::class)));

    $notification->refresh();
    expect($notification->status)->toBe(NotificationStatus::Delivered)
        ->and($notification->delivered_at)->not->toBeNull();
});

it('marks the notification as sent and schedules a delivery report on provider acceptance', function (): void {
    Queue::fake([SimulateProviderCallbackJob::class]);
    $notification = makeNotification();

    SendNotificationJob::dispatchSync($notification->id);

    expect($notification->refresh()->status)->toBe(NotificationStatus::Sent);
    Queue::assertPushed(SimulateProviderCallbackJob::class, 1);
});

it('never calls the provider again for a redelivered job', function (): void {
    $notification = makeNotification();

    $this->mock(SmsProviderInterface::class)
        ->shouldReceive('send')
        ->once()
        ->andReturn(new ProviderResponse('pm-1'));

    SendNotificationJob::dispatchSync($notification->id);
    SendNotificationJob::dispatchSync($notification->id);

    expect($notification->refresh()->status)->toBe(NotificationStatus::Sent);
});

it('marks the notification as failed on a permanent provider rejection', function (): void {
    $notification = makeNotification('fail-13');

    SendNotificationJob::dispatchSync($notification->id);

    $notification->refresh();
    expect($notification->status)->toBe(NotificationStatus::Failed)
        ->and($notification->failed_at)->not->toBeNull()
        ->and($notification->error_message)->toContain('does not exist');
});

it('retries transient gateway errors until the provider accepts', function (): void {
    $notification = makeNotification('flaky-99');
    $registry = app(ProviderRegistry::class);
    $job = new SendNotificationJob($notification->id);

    $transientFailures = 0;

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        try {
            $job->handle($registry);
            break;
        } catch (ProviderTemporarilyUnavailableException) {
            $transientFailures++;
        }
    }

    expect($transientFailures)->toBe(2)
        ->and($notification->refresh()->status)->toBe(NotificationStatus::Sent);
});

it('marks the notification as failed when retries are exhausted', function (): void {
    $notification = makeNotification();

    (new SendNotificationJob($notification->id))
        ->failed(new ProviderTemporarilyUnavailableException('Gateway is temporarily unavailable'));

    $notification->refresh();
    expect($notification->status)->toBe(NotificationStatus::Failed)
        ->and($notification->error_message)->toBe('Gateway is temporarily unavailable');
});

it('does not let the failure callback override a final status', function (): void {
    $notification = NotificationMessage::factory()->delivered()->create();

    (new SendNotificationJob($notification->id))
        ->failed(new ProviderTemporarilyUnavailableException('too late'));

    expect($notification->refresh()->status)->toBe(NotificationStatus::Delivered);
});
