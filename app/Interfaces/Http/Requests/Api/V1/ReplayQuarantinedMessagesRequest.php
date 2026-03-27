<?php

namespace App\Interfaces\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ReplayQuarantinedMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'message_ids' => ['sometimes', 'array', 'min:1', 'max:50'],
            'message_ids.*' => ['string', 'filled', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('limit') || ! $this->has('message_ids')) {
                return;
            }

            $message = 'Informe limit ou message_ids, mas nao os dois ao mesmo tempo.';

            $validator->errors()->add('limit', $message);
            $validator->errors()->add('message_ids', $message);
        });
    }

    public function limit(): int
    {
        return (int) $this->validated('limit', 1);
    }

    /**
     * @return array<int, string>
     */
    public function messageIds(): array
    {
        /** @var array<int, string> $messageIds */
        $messageIds = $this->validated('message_ids', []);

        return array_values(array_unique($messageIds));
    }

    public function requestedReplayLimit(): int
    {
        $messageIds = $this->messageIds();

        return $messageIds === [] ? $this->limit() : count($messageIds);
    }
}
