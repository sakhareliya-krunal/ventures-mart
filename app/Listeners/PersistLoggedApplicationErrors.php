<?php

namespace App\Listeners;

use App\Services\ApplicationErrorRecorder;
use Illuminate\Log\Events\MessageLogged;

class PersistLoggedApplicationErrors
{
    public function __construct(private readonly ApplicationErrorRecorder $recorder)
    {
    }

    public function handle(MessageLogged $event): void
    {
        $this->recorder->recordLog($event->level, (string) $event->message, $event->context ?? []);
    }
}
