<?php

namespace App\Application\Events\Processors;

use App\Domain\Events\DataTransferObjects\StoredEventData;

final class UserCreatedProcessor extends AbstractEventProcessor
{
    public function eventName(): string
    {
        return 'user.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function process(StoredEventData $event): array
    {
        return [
            'resource' => 'user',
            'resource_id' => $this->requireString($event, 'user_id'),
            'summary' => 'Cadastro de usuario processado.',
        ];
    }
}
