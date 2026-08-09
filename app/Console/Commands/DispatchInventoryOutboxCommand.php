<?php

namespace App\Console\Commands;

use App\Models\InventoryOutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

class DispatchInventoryOutboxCommand extends Command
{
    protected $signature = 'inventory:dispatch-outbox {--limit=100}';

    protected $description = 'Dispatch pending transactional inventory events';

    public function handle(): int
    {
        $messages = InventoryOutboxMessage::query()
            ->whereNull('processed_at')
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($messages as $message) {
            try {
                DB::transaction(function () use ($message): void {
                    $locked = InventoryOutboxMessage::query()->lockForUpdate()->findOrFail($message->id);
                    if ($locked->processed_at) {
                        return;
                    }

                    Event::dispatch($locked->event_type, [$locked->payload]);
                    $locked->forceFill([
                        'attempts' => $locked->attempts + 1,
                        'processed_at' => now(),
                        'last_error' => null,
                    ])->save();
                });
            } catch (Throwable $exception) {
                $message->forceFill([
                    'attempts' => $message->attempts + 1,
                    'available_at' => now()->addMinutes(min(60, 2 ** min(5, $message->attempts))),
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                ])->save();
            }
        }

        $this->info("Processed {$messages->count()} outbox message(s).");

        return self::SUCCESS;
    }
}
