<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Unit;

use Firevel\CloudTasksQueueDriver\CloudTasksJob;
use Firevel\CloudTasksQueueDriver\Tests\TestCase;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class CloudTasksJobTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_job_id_is_read_from_payload_uuid()
    {
        $job = $this->makeJob(['uuid' => 'test-uuid-123']);

        $this->assertSame('test-uuid-123', $job->getJobId());
    }

    public function test_raw_body_is_json_encoded_payload()
    {
        $payload = ['uuid' => 'test-uuid-123', 'displayName' => 'ExampleJob'];

        $job = $this->makeJob($payload);

        $this->assertSame(json_encode($payload), $job->getRawBody());
    }

    public function test_header_lookup_is_case_insensitive()
    {
        $job = $this->makeJob([], ['x-custom-header' => 'value']);

        $this->assertSame('value', $job->header('X-Custom-Header'));
    }

    public function test_header_unwraps_array_values()
    {
        // Request::header() returns each header as an array of values.
        $job = $this->makeJob([], ['x-custom-header' => ['value']]);

        $this->assertSame('value', $job->header('X-Custom-Header'));
    }

    public function test_header_returns_default_when_missing()
    {
        $job = $this->makeJob([], []);

        $this->assertSame('fallback', $job->header('X-Missing', 'fallback'));
    }

    public function test_first_attempt_is_one()
    {
        $job = $this->makeJob([], []);

        $this->assertSame(1, $job->attempts());
    }

    public function test_attempts_include_app_engine_retry_count()
    {
        $job = $this->makeJob([], ['x-appengine-taskretrycount' => ['3']]);

        $this->assertSame(4, $job->attempts());
    }

    public function test_attempts_include_cloud_tasks_retry_count()
    {
        $job = $this->makeJob([], ['x-cloudtasks-taskretrycount' => ['2']]);

        $this->assertSame(3, $job->attempts());
    }

    public function test_attempts_include_payload_attempts_from_released_job()
    {
        $job = $this->makeJob(['attempts' => 2], ['x-cloudtasks-taskretrycount' => ['1']]);

        $this->assertSame(4, $job->attempts());
    }

    public function test_queue_name_is_read_from_headers()
    {
        $job = $this->makeJob([], ['x-cloudtasks-queuename' => ['orders']]);

        $this->assertSame('orders', $job->getQueue());

        $job = $this->makeJob([], ['x-appengine-queuename' => ['emails']]);

        $this->assertSame('emails', $job->getQueue());
    }

    public function test_release_pushes_task_copy_with_delay_and_attempts()
    {
        $capturedTask = null;

        $client = Mockery::mock(CloudTasksClient::class);
        $client->shouldReceive('queueName')->andReturnUsing(function ($project, $location, $queue) {
            return "projects/{$project}/locations/{$location}/queues/{$queue}";
        });
        $client->shouldReceive('createTask')
            ->once()
            ->with(
                'projects/test-project/locations/us-central1/queues/orders',
                Mockery::capture($capturedTask)
            );

        $this->app->instance(CloudTasksClient::class, $client);

        $job = $this->makeJob(
            ['uuid' => 'test-uuid-123', 'displayName' => 'ExampleJob'],
            ['x-cloudtasks-queuename' => ['orders'], 'x-cloudtasks-taskretrycount' => ['1']]
        );
        $job->setConnectionName('cloudtasks');

        $job->release(60);

        $this->assertTrue($job->isReleased());

        $payload = json_decode($capturedTask->getHttpRequest()->getBody(), true);
        $this->assertSame('test-uuid-123', $payload['uuid']);
        $this->assertSame(2, $payload['attempts']);
        $this->assertEqualsWithDelta(time() + 60, $capturedTask->getScheduleTime()->getSeconds(), 5);
    }

    /**
     * Create a job instance with the given payload and headers.
     *
     * @param  array  $payload
     * @param  array  $headers
     * @return CloudTasksJob
     */
    private function makeJob(array $payload = [], array $headers = [])
    {
        return app(CloudTasksJob::class)
            ->setJob($payload)
            ->setHeaders($headers);
    }
}
