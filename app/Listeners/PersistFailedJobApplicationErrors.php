<?php

namespace App\Listeners;

use App\Services\ApplicationErrorRecorder;
use Illuminate\Queue\Events\JobFailed;

class PersistFailedJobApplicationErrors
{
    public function __construct(private readonly ApplicationErrorRecorder $recorder)
    {
    }

    public function handle(JobFailed $event): void
    {
        $jobName = method_exists($event->job, 'resolveName')
            ? $event->job->resolveName()
            : $event->job->getName();

        $this->recorder->recordJobFailure((string) $jobName, $event->exception, [
            'connection' => $event->connectionName,
            'queue' => method_exists($event->job, 'getQueue') ? $event->job->getQueue() : null,
        ]);
    }
}
