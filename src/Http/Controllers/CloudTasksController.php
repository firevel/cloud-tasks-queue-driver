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
     * Attempts a job gets when neither the job nor the connection sets a limit.
     *
     * @var int
     */
    const DEFAULT_TRIES = 3;

    /**
     * Seconds before a job that threw is attempted again, when the job does
     * not define its own backoff.
     *
     * @var int
     */
    const DEFAULT_BACKOFF = 60;

    /**
     * Handle job.
     *
     * Retries of a job that throws are owned by Laravel: the worker releases
     * the job, which creates a fresh task carrying the attempt count, and the
     * request ends with 200 so Cloud Tasks does not redeliver the original as
     * well. A job that exhausted its attempts is failed (and logged to the
     * failed job provider by the service provider's listener) and also
     * answered with 200. Only a request that could not hand the job back (the
     * release itself failed, or the process died) ends with an error status,
     * and only then does the Cloud Tasks retry policy apply.
     *
     * @param  CloudTasksRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(CloudTasksRequest $request)
    {
        $connection = $this->getConnectionName();

        $job = CloudTasksService::makeJobFromRequest($request);
        $job->setConnectionName($connection);

        try {
            $this
                ->getWorker()
                ->process($connection, $job, $this->getWorkerOptions($connection));
        } catch (Throwable $e) {
            if ($job->hasFailed() || $job->isDeleted()) {
                return response()->json(['status' => 'failed']);
            }

            if ($job->isReleased()) {
                return response()->json(['status' => 'released']);
            }

            // The job could not be released; let Cloud Tasks redeliver it.
            throw $e;
        }

        return response()->json(['status' => $job->hasFailed() ? 'failed' : 'ok']);
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

    /**
     * Worker options for the connection.
     *
     * `tries` and `backoff` on the connection config are the defaults for
     * jobs that do not define `$tries` / `$backoff` themselves.
     *
     * @param  string  $connection
     * @return WorkerOptions
     */
    protected function getWorkerOptions($connection)
    {
        $config = config("queue.connections.{$connection}", []);

        return new WorkerOptions(
            backoff: (int) ($config['backoff'] ?? static::DEFAULT_BACKOFF),
            maxTries: (int) ($config['tries'] ?? static::DEFAULT_TRIES),
        );
    }
}
