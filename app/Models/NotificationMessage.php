<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationMessage extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationMessageFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'batch_id',
        'recipient_id',
        'status',
        'attempts',
        'provider_message_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'status' => NotificationStatus::class,
        'attempts' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<NotificationBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }
}
