<?php

declare(strict_types=1);

use App\Enums\NotificationStatus;
use App\Models\NotificationMessage;

it('applies a positive delivery report to a sent notification', function (): void {
    $notification = NotificationMessage::factory()->sent()->create();

    $this->postJson('/api/v1/webhooks/delivery', [
        'provider_message_id' => $notification->provider_message_id,
        'status' => 'delivered',
    ])
        ->assertOk()
        ->assertJson(['applied' => true]);

    $notification->refresh();
    expect($notification->status)->toBe(NotificationStatus::Delivered)
        ->and($notification->delivered_at)->not->toBeNull();
});

it('applies a negative delivery report with a reason', function (): void {
    $notification = NotificationMessage::factory()->sent()->create();

    $this->postJson('/api/v1/webhooks/delivery', [
        'provider_message_id' => $notification->provider_message_id,
        'status' => 'failed',
        'reason' => 'Mailbox does not exist',
    ])
        ->assertOk()
        ->assertJson(['applied' => true]);

    $notification->refresh();
    expect($notification->status)->toBe(NotificationStatus::Failed)
        ->and($notification->error_message)->toBe('Mailbox does not exist');
});

it('ignores a duplicate delivery report', function (): void {
    $notification = NotificationMessage::factory()->delivered()->create();

    $this->postJson('/api/v1/webhooks/delivery', [
        'provider_message_id' => $notification->provider_message_id,
        'status' => 'failed',
        'reason' => 'late duplicate report',
    ])
        ->assertOk()
        ->assertJson(['applied' => false]);

    expect($notification->refresh()->status)->toBe(NotificationStatus::Delivered);
});

it('answers 200 for an unknown provider message id', function (): void {
    $this->postJson('/api/v1/webhooks/delivery', [
        'provider_message_id' => 'pm-unknown',
        'status' => 'delivered',
    ])
        ->assertOk()
        ->assertJson(['applied' => false]);
});

it('rejects non-final statuses in a report', function (): void {
    $this->postJson('/api/v1/webhooks/delivery', [
        'provider_message_id' => 'pm-1',
        'status' => 'queued',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});
