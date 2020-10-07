<?php

namespace Firevel\CloudTasksQueueDriver\Http\Controllers;

use Firevel\CloudTasksQueueDriver\Http\Requests\CloudTasksRequest;
use Firevel\CloudTasksQueueDriver\Services\CloudTasksService;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Routing\Controller;

class CloudTasksController extends Controller
{
    /**
     * Handle job.
     *
     * @param CloudTasksRequest $request
     * @return void
     */
    public function handle(CloudTasksRequest $request)
    {
        $job = CloudTasksService::makeJobFromRequest($request);

        $this
            ->getWorker()
            ->process('cloudtasks', $job, new WorkerOptions());
    }

    /**
     * Get worker instance.
     *
     * @return Illuminate\Queue\Worker
     */
    public function getWorker()
    {
        return app('queue.worker');
    }
}
