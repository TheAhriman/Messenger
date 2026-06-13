<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\NotificationPriority;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationBatch extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationBatchFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'idempotency_key',
        'channel',
        'priority',
        'text',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'priority' => NotificationPriority::class,
    ];

    /**
     * @return HasMany<NotificationMessage, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationMessage::class, 'batch_id');
    }
}
