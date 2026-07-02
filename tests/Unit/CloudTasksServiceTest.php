<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Unit;

use Firevel\CloudTasksQueueDriver\Services\CloudTasksService;
use Firevel\CloudTasksQueueDriver\Services\SignatureService;
use Firevel\CloudTasksQueueDriver\Tests\TestCase;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\Task;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class CloudTasksServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_app_engine_http_request_is_signed_and_routed()
    {
        $service = new CloudTasksService([]);
        $payload = '{"foo":"bar"}';

        $request = $service->createAppEngineHttpRequest('api', 'v2', '/_cloudtasks', $payload);

        $this->assertSame('/_cloudtasks', $request->getRelativeUri());
        $this->assertSame(HttpMethod::POST, $request->getHttpMethod());
        $this->assertSame($payload, $request->getBody());
        $this->assertSame(SignatureService::sign($payload), $request->getHeaders()['x-signature']);
        $this->assertSame('api', $request->getAppEngineRouting()->getService());
        $this->assertSame('v2', $request->getAppEngineRouting()->getVersion());
    }

    public function test_app_engine_http_request_skips_empty_service_and_version()
    {
        $service = new CloudTasksService([]);

        $request = $service->createAppEngineHttpRequest(null, null, '/_cloudtasks', '{}');

        $this->assertSame('', $request->getAppEngineRouting()->getService());
        $this->assertSame('', $request->getAppEngineRouting()->getVersion());
    }

    public function test_http_request_is_signed_and_targets_url_with_route()
    {
        $service = new CloudTasksService([]);
        $payload = '{"foo":"bar"}';

        $request = $service->createHttpRequest('https://example.com', '/_cloudtasks', $payload);

        $this->assertSame('https://example.com/_cloudtasks', $request->getUrl());
        $this->assertSame(HttpMethod::POST, $request->getHttpMethod());
        $this->assertSame($payload, $request->getBody());
        $this->assertSame(SignatureService::sign($payload), $request->getHeaders()['x-signature']);
    }

    public function test_push_task_uses_http_request_when_no_service_configured()
    {
        $task = $this->pushTask([
            'route' => '/_cloudtasks',
            'project' => 'test-project',
            'location' => 'us-central1',
            'url' => 'https://example.com',
        ], '{"foo":"bar"}');

        $this->assertNotNull($task->getHttpRequest());
        $this->assertSame('https://example.com/_cloudtasks', $task->getHttpRequest()->getUrl());
        $this->assertNull($task->getAppEngineHttpRequest());
        $this->assertNull($task->getScheduleTime());
    }

    public function test_push_task_uses_app_engine_request_when_service_configured()
    {
        $task = $this->pushTask([
            'route' => '/_cloudtasks',
            'project' => 'test-project',
            'location' => 'us-central1',
            'service' => 'api',
            'version' => 'v2',
        ], '{"foo":"bar"}');

        $this->assertNotNull($task->getAppEngineHttpRequest());
        $this->assertSame('/_cloudtasks', $task->getAppEngineHttpRequest()->getRelativeUri());
        $this->assertSame('api', $task->getAppEngineHttpRequest()->getAppEngineRouting()->getService());
        $this->assertSame('v2', $task->getAppEngineHttpRequest()->getAppEngineRouting()->getVersion());
        $this->assertNull($task->getHttpRequest());
    }

    public function test_push_task_with_delay_sets_schedule_time()
    {
        $task = $this->pushTask([
            'route' => '/_cloudtasks',
            'project' => 'test-project',
            'location' => 'us-central1',
            'url' => 'https://example.com',
        ], '{"foo":"bar"}', 60);

        $this->assertNotNull($task->getScheduleTime());
        $this->assertEqualsWithDelta(time() + 60, $task->getScheduleTime()->getSeconds(), 5);
    }

    /**
     * Push a task through the service with a mocked client and capture it.
     *
     * @param  array  $config
     * @param  string  $payload
     * @param  int  $delay
     * @return Task
     */
    private function pushTask(array $config, $payload, $delay = 0)
    {
        $capturedTask = null;

        $client = Mockery::mock(CloudTasksClient::class);
        $client->shouldReceive('queueName')->andReturnUsing(function ($project, $location, $queue) {
            return "projects/{$project}/locations/{$location}/queues/{$queue}";
        });
        $client->shouldReceive('createTask')
            ->once()
            ->with(
                "projects/{$config['project']}/locations/{$config['location']}/queues/default",
                Mockery::capture($capturedTask)
            );

        $this->app->instance(CloudTasksClient::class, $client);

        (new CloudTasksService($config))->pushTaskToQueue('default', $payload, $delay);

        $this->assertInstanceOf(Task::class, $capturedTask);

        return $capturedTask;
    }
}
