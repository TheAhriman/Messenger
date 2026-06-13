<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\NotificationPriority;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationBatch>
 */
class NotificationBatchFactory extends Factory
{
    protected $model = NotificationBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'channel' => fake()->randomElement(Channel::cases()),
            'priority' => fake()->randomElement(NotificationPriority::cases()),
            'text' => fake()->sentence(),
        ];
    }
}
