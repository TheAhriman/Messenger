<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\SendNotificationsData;
use App\Enums\Channel;
use App\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);

        $recipients = $this->input('recipient_ids');

        if (is_array($recipients)) {
            $this->merge([
                'recipient_ids' => array_map(
                    static fn (mixed $id): mixed => is_int($id) ? (string) $id : $id,
                    $recipients,
                ),
            ]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(Channel::labels())],
            'priority' => ['sometimes', 'required', Rule::in(NotificationPriority::labels())],
            'text' => ['required', 'string', 'max:5000'],
            'recipient_ids' => ['required', 'array', 'min:1', 'max:10000'],
            'recipient_ids.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function toData(): SendNotificationsData
    {
        /** @var array{idempotency_key: string, channel: string, priority?: string, text: string, recipient_ids: list<string>} $validated */
        $validated = $this->validated();

        return new SendNotificationsData(
            idempotencyKey: $validated['idempotency_key'],
            channel: Channel::fromLabel($validated['channel']),
            priority: NotificationPriority::tryFromLabel($validated['priority'] ?? null) ?? NotificationPriority::Marketing,
            text: $validated['text'],
            recipientIds: array_values(array_unique($validated['recipient_ids'])),
        );
    }
}
