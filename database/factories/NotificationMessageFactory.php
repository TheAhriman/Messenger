<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Models\NotificationBatch;
use App\Models\NotificationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationMessage>
 */
class NotificationMessageFactory extends Factory
{
    protected $model = NotificationMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => NotificationBatch::factory(),
            'recipient_id' => (string) fake()->numberBetween(1, 100000),
            'status' => NotificationStatus::Queued,
            'attempts' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => NotificationStatus::Sent,
            'provider_message_id' => 'pm-'.fake()->uuid(),
            'sent_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function delivered(): static
    {
        return $this->sent()->state(fn (): array => [
            'status' => NotificationStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }
}
