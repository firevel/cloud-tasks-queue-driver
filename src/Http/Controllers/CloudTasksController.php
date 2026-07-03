<?php

namespace Firevel\CloudTasksQueueDriver\Http\Controllers;

use Firevel\CloudTasksQueueDriver\Http\Requests\CloudTasksRequest;
use Firevel\CloudTasksQueueDriver\Services\CloudTasksService;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Routing\Controller;
use Throwable;

class CloudTasksController extends Controller
{
    /**
     * Handle job.
     *
     * @param  CloudTasksRequest  $request
     * @return void
     */
    public function handle(CloudTasksRequest $request)
    {
        $connection = $this->getConnectionName();

        $job = CloudTasksService::makeJobFromRequest($request);
        $job->setConnectionName($connection);

        try {
            // Retries are governed by the Cloud Tasks queue configuration
            // (maxTries: 0), unless the job itself defines $tries.
            $this
                ->getWorker()
                ->process($connection, $job, new WorkerOptions(maxTries: 0));
        } catch (Throwable $e) {
            if ($job->isDeleted() || $job->hasFailed()) {
                // The job was failed or deleted permanently; respond with a
                // success status so Cloud Tasks does not redeliver it.
                return response()->json(['status' => 'failed']);
            }

            // Let Cloud Tasks retry according to the queue configuration.
            throw $e;
        }
    }

    /**
     * Get the queue connection name handled by this endpoint.
     *
     * @return string
     */
    public function getConnectionName()
    {
        $default = config('queue.default');

        if (config("queue.connections.{$default}.driver") === 'cloudtasks') {
            return $default;
        }

        return 'cloudtasks';
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
