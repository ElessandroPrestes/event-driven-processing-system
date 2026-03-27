<?php

namespace App\Application\Events\DataTransferObjects;

final readonly class QuarantineInspectionData
{
    /**
     * @param  array<int, QuarantinedMessageData>  $messages
     */
    public function __construct(
        public int $depth,
        public int $limit,
        public array $messages,
    ) {}
}
