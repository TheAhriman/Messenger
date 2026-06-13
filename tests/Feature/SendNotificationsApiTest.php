<?php

declare(strict_types=1);

use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @param  list<string>  $recipients
 * @return array<string, mixed>
 */
function sendPayload(array $recipients = ['42', '108'], string $priority = 'transactional'): array
{
    return [
        'channel' => 'sms',
        'priority' => $priority,
        'text' => 'Your access code: 4821',
        'recipient_ids' => $recipients,
    ];
}

it('accepts a batch and routes jobs to the high priority queue', function (): void {
    Bus::fake([SendNotificationJob::class]);

    $this->postJson('/api/v1/notifications', sendPayload(), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertAccepted()
        ->assertJsonPath('duplicate', false)
        ->assertJsonPath('data.priority', 'transactional')
        ->assertJsonPath('data.notifications_count', 2);

    $this->assertDatabaseCount('notification_messages', 2);
    $this->assertDatabaseHas('notification_messages', [
        'recipient_id' => '42',
        'status' => NotificationStatus::Queued->value,
    ]);

    Bus::assertDispatchedTimes(SendNotificationJob::class, 2);
    Bus::assertDispatched(
        SendNotificationJob::class,
        static fn (SendNotificationJob $job): bool => $job->queue === config('notifications.queues.high'),
    );
});

it('routes marketing batches to the low priority queue', function (): void {
    Bus::fake([SendNotificationJob::class]);

    $this->postJson('/api/v1/notifications', sendPayload(priority: 'marketing'), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertAccepted();

    Bus::assertDispatched(
        SendNotificationJob::class,
        static fn (SendNotificationJob $job): bool => $job->queue === config('notifications.queues.low'),
    );
});

it('returns the same batch for a duplicate request without dispatching again', function (): void {
    Bus::fake([SendNotificationJob::class]);
    $key = (string) Str::uuid();

    $first = $this->postJson('/api/v1/notifications', sendPayload(), ['Idempotency-Key' => $key]);
    $second = $this->postJson('/api/v1/notifications', sendPayload(), ['Idempotency-Key' => $key]);

    $first->assertAccepted();
    $second->assertOk()
        ->assertJsonPath('duplicate', true)
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(NotificationBatch::count())->toBe(1);
    $this->assertDatabaseCount('notification_messages', 2);
    Bus::assertDispatchedTimes(SendNotificationJob::class, 2);
});

it('keeps deduplicating after a redis flush thanks to the database constraint', function (): void {
    Bus::fake([SendNotificationJob::class]);
    $key = (string) Str::uuid();

    $this->postJson('/api/v1/notifications', sendPayload(), ['Idempotency-Key' => $key])->assertAccepted();

    Cache::flush();

    $this->postJson('/api/v1/notifications', sendPayload(), ['Idempotency-Key' => $key])
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    expect(NotificationBatch::count())->toBe(1);
});

it('collapses repeated recipients into a single notification', function (): void {
    Bus::fake([SendNotificationJob::class]);

    $this->postJson('/api/v1/notifications', sendPayload(['42', '42', '108']), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertAccepted();

    $this->assertDatabaseCount('notification_messages', 2);
});

it('rejects a request without the Idempotency-Key header', function (): void {
    $this->postJson('/api/v1/notifications', sendPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['idempotency_key']);
});

it('validates required fields', function (): void {
    $this->postJson('/api/v1/notifications', [], ['Idempotency-Key' => 'k'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['channel', 'text', 'recipient_ids']);
});

it('rejects unknown channel and priority labels', function (): void {
    $this->postJson(
        '/api/v1/notifications',
        ['channel' => 'pigeon', 'priority' => 'urgent', 'text' => 'hi', 'recipient_ids' => ['1']],
        ['Idempotency-Key' => 'k'],
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['channel', 'priority']);
});
