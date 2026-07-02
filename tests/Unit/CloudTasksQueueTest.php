<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Unit;

use Firevel\CloudTasksQueueDriver\CloudTasksConnector;
use Firevel\CloudTasksQueueDriver\CloudTasksQueue;
use Firevel\CloudTasksQueueDriver\Services\CloudTasksService;
use Firevel\CloudTasksQueueDriver\Tests\TestCase;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class CloudTasksQueueTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_connector_returns_cloud_tasks_queue()
    {
        $config = config('queue.connections.cloudtasks');

        $queue = (new CloudTasksConnector)->connect($config);

        $this->assertInstanceOf(CloudTasksQueue::class, $queue);
        $this->assertSame($config, $queue->getServiceConfig());
    }

    public function test_queue_manager_resolves_cloudtasks_driver()
    {
        $this->assertInstanceOf(CloudTasksQueue::class, Queue::connection('cloudtasks'));
    }

    public function test_tasks_service_is_built_from_queue_config()
    {
        $queue = Queue::connection('cloudtasks');

        $service = $queue->getTasksService();

        $this->assertInstanceOf(CloudTasksService::class, $service);
        $this->assertSame($service, $queue->getTasksService());
    }

    public function test_push_raw_uses_default_queue_name()
    {
        $client = Mockery::mock(CloudTasksClient::class);
        $client->shouldReceive('queueName')->andReturnUsing(function ($project, $location, $queue) {
            return "projects/{$project}/locations/{$location}/queues/{$queue}";
        });
        $client->shouldReceive('createTask')
            ->once()
            ->with(
                'projects/test-project/locations/us-central1/queues/default',
                Mockery::any()
            );

        $this->app->instance(CloudTasksClient::class, $client);

        Queue::connection('cloudtasks')->pushRaw('{"foo":"bar"}', 'default');
    }
}
