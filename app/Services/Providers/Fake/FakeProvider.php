<?php

declare(strict_types=1);

namespace App\Services\Providers\Fake;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Exceptions\ProviderRejectedException;
use App\Exceptions\ProviderTemporarilyUnavailableException;
use App\Jobs\SimulateProviderCallbackJob;
use App\Models\NotificationMessage;
use App\Services\Providers\NotificationProviderInterface;
use App\Services\Providers\ProviderResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class FakeProvider implements NotificationProviderInterface
{
    private const ACCEPTED_TTL = 86400;

    abstract protected function channel(): Channel;

    public function send(NotificationMessage $notification): ProviderResponse
    {
        $recipient = $notification->recipient_id;

        if (str_starts_with($recipient, 'fail-')) {
            throw new ProviderRejectedException("Recipient [{$recipient}] does not exist");
        }

        if (str_starts_with($recipient, 'flaky-')) {
            $attempt = (int) Cache::increment("provider:flaky:{$notification->id}");

            if ($attempt < 3) {
                throw new ProviderTemporarilyUnavailableException('Gateway is temporarily unavailable');
            }
        }

        $acceptedKey = "provider:accepted:{$notification->id}";
        $providerMessageId = $this->channel()->label().'-'.Str::uuid();

        if (! Cache::add($acceptedKey, $providerMessageId, self::ACCEPTED_TTL)) {
            return new ProviderResponse((string) Cache::get($acceptedKey), wasDuplicate: true);
        }

        Log::info('Fake provider accepted a message', [
            'channel' => $this->channel()->label(),
            'notification_id' => $notification->id,
            'recipient_id' => $recipient,
            'provider_message_id' => $providerMessageId,
        ]);

        $this->scheduleDeliveryReport($notification, $providerMessageId);

        return new ProviderResponse($providerMessageId);
    }

    private function scheduleDeliveryReport(NotificationMessage $notification, string $providerMessageId): void
    {
        $delivered = ! str_starts_with($notification->recipient_id, 'undeliverable-');

        SimulateProviderCallbackJob::dispatch(
            $providerMessageId,
            $delivered ? NotificationStatus::Delivered : NotificationStatus::Failed,
            $delivered ? null : 'Recipient unreachable: delivery report timed out',
        )
            ->onQueue($notification->batch->priority->queue())
            ->delay(now()->addSeconds(config()->integer('notifications.delivery_callback_delay')));
    }
}
