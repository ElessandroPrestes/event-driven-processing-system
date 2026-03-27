<?php

namespace App\Application\Events\DataTransferObjects;

use App\Domain\Events\DataTransferObjects\StoredEventData;

final readonly class ProcessEventResult
{
    public const string SKIP_REASON_NOT_FOUND = 'not_found';

    public const string SKIP_REASON_TERMINAL_STATUS = 'terminal_status';

    public function __construct(
        public ?StoredEventData $event,
        public bool $shouldRetry,
        public bool $skipped,
        public ?string $skipReason = null,
    ) {}

    public static function processed(StoredEventData $event): self
    {
        return new self($event, false, false, null);
    }

    public static function retry(StoredEventData $event): self
    {
        return new self($event, true, false, null);
    }

    public static function failed(StoredEventData $event): self
    {
        return new self($event, false, false, null);
    }

    public static function skipped(?string $reason = null): self
    {
        return new self(null, false, true, $reason);
    }
}
