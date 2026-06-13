<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryReportRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'provider_message_id' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_map(
                static fn (NotificationStatus $status): string => $status->label(),
                NotificationStatus::finalStatuses(),
            ))],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }

    public function status(): NotificationStatus
    {
        return NotificationStatus::fromLabel((string) $this->validated('status'));
    }
}
