<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Feature;

use Firevel\CloudTasksQueueDriver\Services\SignatureService;
use Firevel\CloudTasksQueueDriver\Tests\Fixtures\FailingJob;
use Firevel\CloudTasksQueueDriver\Tests\Fixtures\FailingJobWithTries;
use Firevel\CloudTasksQueueDriver\Tests\Fixtures\OverlappingJob;
use Firevel\CloudTasksQueueDriver\Tests\Fixtures\ReleasingJob;
use Firevel\CloudTasksQueueDriver\Tests\TestCase;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class RetryBehaviorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_failing_job_returns_500_so_cloud_tasks_retries()
    {
        $payload = $this->jobPayload(new FailingJob);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertStatus(500);
    }

    public function test_job_exceeding_its_tries_is_failed_and_not_redelivered()
    {
        Event::fake([JobFailed::class]);

        $payload = $this->jobPayload(new FailingJobWithTries);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertOk();
        Event::assertDispatched(JobFailed::class);
    }

    public function test_job_below_its_tries_returns_500_so_cloud_tasks_retries()
    {
        Event::fake([JobFailed::class]);

        $job = new FailingJobWithTries;
        $job->tries = 3;
        $payload = $this->jobPayload($job);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertStatus(500);
        Event::assertNotDispatched(JobFailed::class);
    }

    public function test_released_job_is_pushed_back_as_new_task()
    {
        $capturedTask = null;

        $client = Mockery::mock(CloudTasksClient::class);
        $client->shouldReceive('queueName')->andReturnUsing(function ($project, $location, $queue) {
            return "projects/{$project}/locations/{$location}/queues/{$queue}";
        });
        $client->shouldReceive('createTask')
            ->once()
            ->with(
                'projects/test-project/locations/us-central1/queues/default',
                Mockery::capture($capturedTask)
            );

        $this->app->instance(CloudTasksClient::class, $client);

        $payload = $this->jobPayload(new ReleasingJob);

        $response = $this->postTask($payload, SignatureService::sign($payload), [
            'X-CloudTasks-QueueName' => 'default',
        ]);

        $response->assertOk();

        $newPayload = json_decode($capturedTask->getHttpRequest()->getBody(), true);
        $this->assertSame(1, $newPayload['attempts']);
        $this->assertSame(json_decode($payload, true)['uuid'], $newPayload['uuid']);
        $this->assertEqualsWithDelta(time() + 30, $capturedTask->getScheduleTime()->getSeconds(), 5);
    }

    public function test_overlapping_job_is_released_instead_of_lost()
    {
        OverlappingJob::$handled = false;
        $capturedTask = null;

        $client = Mockery::mock(CloudTasksClient::class);
        $client->shouldReceive('queueName')->andReturnUsing(function ($project, $location, $queue) {
            return "projects/{$project}/locations/{$location}/queues/{$queue}";
        });
        $client->shouldReceive('createTask')
            ->once()
            ->with(Mockery::any(), Mockery::capture($capturedTask));

        $this->app->instance(CloudTasksClient::class, $client);

        // Simulate another instance of the job currently processing.
        $job = new OverlappingJob;
        $lockKey = $job->middleware()[0]->getLockKey($job);
        $this->assertTrue(Cache::lock($lockKey, 60)->get());

        $payload = $this->jobPayload($job);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertOk();
        $this->assertFalse(OverlappingJob::$handled);

        $newPayload = json_decode($capturedTask->getHttpRequest()->getBody(), true);
        $this->assertSame(1, $newPayload['attempts']);
        $this->assertEqualsWithDelta(time() + 15, $capturedTask->getScheduleTime()->getSeconds(), 5);
    }
}
