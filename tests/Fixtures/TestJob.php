<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class TestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Whether the job has been executed.
     *
     * @var bool
     */
    public static $handled = false;

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
