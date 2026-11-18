<?php

namespace Firevel\CloudTasksQueueDriver;

use Firevel\CloudTasksQueueDriver\Http\Controllers\CloudTasksController;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\QueueManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Throwable;

class CloudTasksServiceProvider extends ServiceProvider
{
    /**
     * Boot service provider.
     *
     * @param  QueueManager  $queue
     * @param  Router  $router
     * @return void
     */
    public function boot(QueueManager $queue, Router $router)
    {
        $this->registerCloudTasksClient();
        $this->registerCloudTasksConnector($queue);
        $this->registerRoutes($router);
        $this->registerFailedJobLogging();
    }

    /**
     * Register CloudTasksClient singleton.
     *
     * @return void
     */
    private function registerCloudTasksClient()
    {
        $this->app->singleton(CloudTasksClient::class, function () {
            return new CloudTasksClient();
        });
    }

    /**
     * Register connector.
     *
     * @param  QueueManager  $queue
     * @return void
     */
    private function registerCloudTasksConnector(QueueManager $queue)
    {
        $queue->addConnector('cloudtasks', function () {
            return new CloudTasksConnector;
        });
    }

    /**
     * Store jobs that exhausted their attempts with the failed job provider,
     * like `queue:work` does for its own jobs. Skipped when no provider is
     * configured.
     *
     * @return void
     */
    private function registerFailedJobLogging()
    {
        $this->app->make(Dispatcher::class)->listen(JobFailed::class, function (JobFailed $event) {
            if (! $event->job instanceof CloudTasksJob) {
                return;
            }

            try {
                $this->app->make('queue.failer')->log(
                    $event->connectionName,
                    $event->job->getQueue(),
                    $event->job->getRawBody(),
                    $event->exception
                );
            } catch (Throwable) {
                // No failed job provider configured.
            }
        });
    }

    private function registerRoutes(Router $router)
    {
        $connector = config('queue.default');
        $route = config("queue.connections.{$connector}.route", config('queue.connections.cloudtasks.route'));
        $router->post($route, [CloudTasksController::class, 'handle']);
    }
}
