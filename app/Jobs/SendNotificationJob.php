<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\ProviderRejectedException;
use App\Models\NotificationMessage;
use App\Services\Providers\ProviderRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(private readonly string $notificationId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30, 60];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('provider-gateway'),
            (new WithoutOverlapping($this->notificationId))->releaseAfter(5)->expireAfter(60),
        ];
    }

    public function handle(ProviderRegistry $providers): void
    {
        $notification = NotificationMessage::with('batch')->find($this->notificationId);

        if ($notification === null) {
            Log::warning('SendNotificationJob: notification not found', [
                'notification_id' => $this->notificationId,
            ]);

            return;
        }

        if ($notification->status !== NotificationStatus::Queued) {
            Log::info('SendNotificationJob: skipped, notification already processed', [
                'notification_id' => $notification->id,
                'status' => $notification->status->label(),
            ]);

            return;
        }

        try {
            $response = $providers->for($notification->batch->channel)->send($notification);
        } catch (ProviderRejectedException $e) {
            Log::warning('SendNotificationJob: provider rejected the message', [
                'notification_id' => $notification->id,
                'recipient_id' => $notification->recipient_id,
                'channel' => $notification->batch->channel->label(),
                'attempt' => $this->attempts(),
                'reason' => $e->getMessage(),
            ]);

            $this->fail($e);

            return;
        } catch (Throwable $e) {
            Log::warning('SendNotificationJob: transient gateway failure, will retry', [
                'notification_id' => $notification->id,
                'recipient_id' => $notification->recipient_id,
                'channel' => $notification->batch->channel->label(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
                'reason' => $e->getMessage(),
            ]);

            $notification->forceFill([
                'attempts' => $this->attempts(),
                'error_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        NotificationMessage::query()
            ->whereKey($notification->id)
            ->where('status', NotificationStatus::Queued)
            ->update([
                'status' => NotificationStatus::Sent,
                'provider_message_id' => $response->providerMessageId,
                'sent_at' => now(),
                'attempts' => $this->attempts(),
                'error_message' => null,
            ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendNotificationJob: notification permanently failed', [
            'notification_id' => $this->notificationId,
            'attempts' => $this->attempts(),
            'reason' => $exception->getMessage(),
        ]);

        NotificationMessage::query()
            ->whereKey($this->notificationId)
            ->where('status', NotificationStatus::Queued)
            ->update([
                'status' => NotificationStatus::Failed,
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);
    }
}
