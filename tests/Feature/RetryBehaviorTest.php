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
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;

class RetryBehaviorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Replace the Cloud Tasks client with a mock capturing created tasks.
     *
     * @param  int  $createdTasks  Expected number of created tasks.
     * @param  mixed  $capturedTask  Receives the last created task.
     * @return void
     */
    protected function mockClient($createdTasks, &$capturedTask = null)
    {
        $client = Mockery::mock(CloudTasksClient::class);
        $client->shouldReceive('queueName')->andReturnUsing(function ($project, $location, $queue) {
            return "projects/{$project}/locations/{$location}/queues/{$queue}";
        });

        $expectation = $client->shouldReceive('createTask')->times($createdTasks);

        if ($createdTasks > 0) {
            $expectation->with(Mockery::any(), Mockery::capture($capturedTask));
        }

        $this->app->instance(CloudTasksClient::class, $client);
    }

    /**
     * Build a payload that already carries attempts made before a release.
     *
     * @param  object  $job
     * @param  int  $attempts
     * @return string
     */
    protected function releasedJobPayload($job, $attempts)
    {
        return json_encode(json_decode($this->jobPayload($job), true) + ['attempts' => $attempts]);
    }

    public function test_failing_job_is_released_once_and_not_redelivered_by_cloud_tasks()
    {
        Event::fake([JobFailed::class]);
        $this->mockClient(1, $capturedTask);

        $payload = $this->jobPayload(new FailingJob);

        $response = $this->postTask($payload, SignatureService::sign($payload), [
            'X-CloudTasks-QueueName' => 'default',
        ]);

        $response->assertOk()->assertJson(['status' => 'released']);
        Event::assertNotDispatched(JobFailed::class);

        $newPayload = json_decode($capturedTask->getHttpRequest()->getBody(), true);
        $this->assertSame(1, $newPayload['attempts']);
        $this->assertSame(json_decode($payload, true)['uuid'], $newPayload['uuid']);
        $this->assertEqualsWithDelta(time() + 60, $capturedTask->getScheduleTime()->getSeconds(), 5);
    }

    public function test_released_copy_counts_previous_attempts()
    {
        Event::fake([JobFailed::class]);
        $this->mockClient(1, $capturedTask);

        $payload = $this->releasedJobPayload(new FailingJob, 1);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertOk()->assertJson(['status' => 'released']);
        Event::assertNotDispatched(JobFailed::class);
        $this->assertSame(2, json_decode($capturedTask->getHttpRequest()->getBody(), true)['attempts']);
    }

    public function test_failing_job_on_its_last_attempt_is_failed_and_not_released()
    {
        Event::fake([JobFailed::class]);
        $this->mockClient(0);

        $payload = $this->releasedJobPayload(new FailingJob, 2);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertOk()->assertJson(['status' => 'failed']);
        Event::assertDispatched(JobFailed::class);
    }

    public function test_job_exceeding_its_tries_is_failed_and_not_redelivered()
    {
        Event::fake([JobFailed::class]);
        $this->mockClient(0);

        $payload = $this->jobPayload(new FailingJobWithTries);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertOk()->assertJson(['status' => 'failed']);
        Event::assertDispatched(JobFailed::class);
    }

    public function test_job_below_its_tries_is_released()
    {
        Event::fake([JobFailed::class]);
        $this->mockClient(1);

        $job = new FailingJobWithTries;
        $job->tries = 3;
        $payload = $this->jobPayload($job);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertOk()->assertJson(['status' => 'released']);
        Event::assertNotDispatched(JobFailed::class);
    }

    public function test_connection_config_sets_default_tries_and_backoff()
    {
        config()->set('queue.connections.cloudtasks.tries', 1);
        config()->set('queue.connections.cloudtasks.backoff', 5);
        Event::fake([JobFailed::class]);

        $this->mockClient(0);
        $payload = $this->jobPayload(new FailingJob);
        $this->postTask($payload, SignatureService::sign($payload))->assertJson(['status' => 'failed']);
        Event::assertDispatched(JobFailed::class);

        config()->set('queue.connections.cloudtasks.tries', 2);
        $this->mockClient(1, $capturedTask);
        $payload = $this->jobPayload(new FailingJob);
        $this->postTask($payload, SignatureService::sign($payload))->assertJson(['status' => 'released']);
        $this->assertEqualsWithDelta(time() + 5, $capturedTask->getScheduleTime()->getSeconds(), 5);
    }

    public function test_failed_job_is_logged_to_the_failed_job_provider()
    {
        $failer = Mockery::mock(FailedJobProviderInterface::class);
        $failer->shouldReceive('log')
            ->once()
            ->with('cloudtasks', 'default', Mockery::type('string'), Mockery::type(RuntimeException::class));
        $this->app->instance('queue.failer', $failer);
        $this->mockClient(0);

        $payload = $this->jobPayload(new FailingJobWithTries);

        $this->postTask($payload, SignatureService::sign($payload), [
            'X-CloudTasks-QueueName' => 'default',
        ])->assertOk();
    }

    public function test_release_that_cannot_create_a_task_returns_500_so_cloud_tasks_redelivers()
    {
        Event::fake([JobFailed::class]);

        $client = Mockery::mock(CloudTasksClient::class);
        $client->shouldReceive('queueName')->andReturn('projects/test-project/locations/us-central1/queues/default');
        $client->shouldReceive('createTask')->once()->andThrow(new RuntimeException('Cloud Tasks unavailable.'));
        $this->app->instance(CloudTasksClient::class, $client);

        $payload = $this->jobPayload(new FailingJob);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertStatus(500);
        Event::assertNotDispatched(JobFailed::class);
    }

    public function test_released_job_is_pushed_back_as_new_task()
    {
        $this->mockClient(1, $capturedTask);

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
        $this->mockClient(1, $capturedTask);

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
