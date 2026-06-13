<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\NotificationHistoryFilters;
use App\Enums\Channel;
use App\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRecipientNotificationsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(NotificationStatus::labels())],
            'channel' => ['sometimes', Rule::in(Channel::labels())],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toFilters(): NotificationHistoryFilters
    {
        /** @var array{status?: string, channel?: string, per_page?: int|string} $validated */
        $validated = $this->validated();

        return new NotificationHistoryFilters(
            status: NotificationStatus::tryFromLabel($validated['status'] ?? null),
            channel: Channel::tryFromLabel($validated['channel'] ?? null),
            perPage: (int) ($validated['per_page'] ?? 15),
        );
    }
}
