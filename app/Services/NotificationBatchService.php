<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BatchCreationResult;
use App\DTO\SendNotificationsData;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationBatch;
use App\Models\NotificationMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationBatchService
{
    private const INSERT_CHUNK = 500;

    public function send(SendNotificationsData $data): BatchCreationResult
    {
        $existing = $this->findExisting($data->idempotencyKey);

        if ($existing !== null) {
            return new BatchCreationResult($existing, wasDuplicate: true);
        }

        try {
            $batch = $this->createBatch($data);
        } catch (UniqueConstraintViolationException) {
            $existing = NotificationBatch::query()
                ->where('idempotency_key', $data->idempotencyKey)
                ->firstOrFail();

            $this->rememberKey($data->idempotencyKey, $existing->id);

            return new BatchCreationResult($existing, wasDuplicate: true);
        }

        $this->rememberKey($data->idempotencyKey, $batch->id);

        return new BatchCreationResult($batch, wasDuplicate: false);
    }

    private function createBatch(SendNotificationsData $data): NotificationBatch
    {
        return DB::transaction(static function () use ($data): NotificationBatch {
            $batch = NotificationBatch::create([
                'idempotency_key' => $data->idempotencyKey,
                'channel' => $data->channel,
                'priority' => $data->priority,
                'text' => $data->text,
            ]);

            $now = now();
            $rows = array_map(static fn (string $recipientId): array => [
                'id' => (string) Str::orderedUuid(),
                'batch_id' => $batch->id,
                'recipient_id' => $recipientId,
                'status' => NotificationStatus::Queued->value,
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], $data->recipientIds);

            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                NotificationMessage::query()->insert($chunk);
            }

            foreach ($rows as $row) {
                SendNotificationJob::dispatch($row['id'])->onQueue($data->priority->queue());
            }

            return $batch;
        });
    }

    private function findExisting(string $idempotencyKey): ?NotificationBatch
    {
        $cachedId = Cache::get($this->cacheKey($idempotencyKey));

        if (is_string($cachedId)) {
            $batch = NotificationBatch::find($cachedId);

            if ($batch !== null) {
                return $batch;
            }
        }

        return NotificationBatch::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function rememberKey(string $idempotencyKey, string $batchId): void
    {
        Cache::put(
            $this->cacheKey($idempotencyKey),
            $batchId,
            config()->integer('notifications.idempotency_ttl'),
        );
    }

    private function cacheKey(string $idempotencyKey): string
    {
        return 'idempotency:'.hash('sha256', $idempotencyKey);
    }
}
