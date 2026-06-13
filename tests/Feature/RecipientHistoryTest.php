<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Models\NotificationBatch;
use App\Models\NotificationMessage;

it('returns the history of a recipient with statuses, newest first', function (): void {
    $delivered = NotificationMessage::factory()->delivered()
        ->for(NotificationBatch::factory()->state(['channel' => Channel::Sms]), 'batch')
        ->create(['recipient_id' => '42', 'created_at' => now()->subHour()]);
    $queued = NotificationMessage::factory()
        ->for(NotificationBatch::factory()->state(['channel' => Channel::Sms]), 'batch')
        ->create(['recipient_id' => '42', 'created_at' => now()]);
    NotificationMessage::factory()->create(['recipient_id' => '999']);

    $this->getJson('/api/v1/recipients/42/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $queued->id)
        ->assertJsonPath('data.0.status', 'queued')
        ->assertJsonPath('data.1.id', $delivered->id)
        ->assertJsonPath('data.1.status', 'delivered')
        ->assertJsonPath('data.1.channel', 'sms');
});

it('filters the history by status and channel', function (): void {
    NotificationMessage::factory()->delivered()
        ->for(NotificationBatch::factory()->state(['channel' => Channel::Sms]), 'batch')
        ->create(['recipient_id' => '42']);
    NotificationMessage::factory()
        ->for(NotificationBatch::factory()->state(['channel' => Channel::Email]), 'batch')
        ->create(['recipient_id' => '42']);

    $this->getJson('/api/v1/recipients/42/notifications?status=delivered')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'delivered');

    $this->getJson('/api/v1/recipients/42/notifications?channel=email')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.channel', 'email');
});

it('paginates the history', function (): void {
    NotificationMessage::factory()->count(3)->create(['recipient_id' => '42']);

    $this->getJson('/api/v1/recipients/42/notifications?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);
});

it('validates filter values', function (): void {
    $this->getJson('/api/v1/recipients/42/notifications?status=unknown&channel=pigeon&per_page=1000')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'channel', 'per_page']);
});
