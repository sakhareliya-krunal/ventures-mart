<?php

namespace App\Jobs;

use App\Services\MetaConversionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMetaConversionEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * @param  array<string, mixed>  $customData
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $eventName,
        public readonly string $eventId,
        public readonly array $customData,
        public readonly array $context,
    ) {
    }

    public function handle(MetaConversionsService $meta): void
    {
        $meta->send($this->eventName, $this->eventId, $this->customData, $this->context);
    }
}
