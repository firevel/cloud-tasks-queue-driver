<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class OverlappingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Whether the job has been executed.
     *
     * @var bool
     */
    public static $handled = false;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new WithoutOverlapping('lock-key'))->releaseAfter(15)];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        static::$handled = true;
    }
}
