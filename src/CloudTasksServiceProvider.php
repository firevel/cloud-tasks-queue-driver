<?php

namespace Firevel\CloudTasksQueueDriver;

use Firevel\CloudTasksQueueDriver\Http\Controllers\CloudTasksController;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Illuminate\Queue\QueueManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class CloudTasksServiceProvider extends ServiceProvider
{
    /**
     * Boot service provider.
     *
     * @param QueueManager $queue
     * @param Router $router
     * @return void
     */
    public function boot(QueueManager $queue, Router $router)
    {
        $this->registerCloudTasksClient();
        $this->registerCloudTasksConnector($queue);
        $this->registerRoutes($router);
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
     * @param QueueManager $queue
     * @return void
     */
    private function registerCloudTasksConnector(QueueManager $queue)
    {
        $queue->addConnector('cloudtasks', function () {
            return new CloudTasksConnector;
        });
    }

    private function registerRoutes(Router $router)
    {
        $connector = config('queue.default');
        $route = config("queue.connections.{$connector}.route", config("queue.connections.cloudtasks.route"));
        $router->post($route, [CloudTasksController::class, 'handle']);
    }
}
